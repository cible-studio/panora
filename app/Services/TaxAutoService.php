<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\Tax;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-génération des taxes communales annuelles à partir du parc de
 * panneaux installés et des taux ODP / TM saisis sur la commune.
 *
 * Règle métier CIBLE CI :
 *   - amount = nombre de panneaux internes installés dans la commune
 *              × tarif annuel saisi sur la commune (odp_rate / tm_rate)
 *   - 1 ligne Tax par (commune, year, type) — idempotent : on ne recrée
 *     pas une ligne existante (on remonte son état au previewer).
 *   - on ignore les communes sans tarif (rate = 0 ou null) : pas de
 *     ligne fantôme à 0 FCFA.
 *   - on ignore les communes sans panneau du parc interne : pas de taxe
 *     à payer pour une commune où la régie n'occupe rien.
 *
 * Réservé à la fonction `apply()` : `preview()` est sans effet de bord
 * et peut être appelée librement côté UI pour vérifier ce qui sera créé.
 */
class TaxAutoService
{
    /** @var string[] */
    public const TYPES = ['odp', 'tm', 'db'];

    /**
     * Calcule (sans persister) les lignes qui seraient créées pour
     * l'année donnée. Renvoie aussi les lignes déjà existantes pour que
     * l'admin voit l'état complet en un coup d'œil.
     *
     * @return array{
     *   year: int,
     *   rows: array<int, array{
     *     commune_id: int, commune: string, type: string,
     *     panels: int, rate: float, amount: float,
     *     exists: bool, existing_status: ?string, existing_id: ?int,
     *   }>,
     *   summary: array{to_create: int, exists: int, total_amount_to_create: float},
     * }
     */
    public function preview(int $year): array
    {
        // Compte des panneaux internes par commune (1 seule requête).
        $panelCounts = DB::table('panels')
            ->selectRaw('commune_id, COUNT(*) as cnt')
            ->whereNotNull('commune_id')
            ->groupBy('commune_id')
            ->pluck('cnt', 'commune_id');

        // Taxes existantes pour l'année — clé "{commune_id}:{type}".
        $existing = Tax::where('year', $year)
            ->get(['id', 'commune_id', 'type', 'amount', 'status'])
            ->keyBy(fn($t) => $t->commune_id . ':' . $t->type);

        $communes = Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate', 'db_rate']);
        $rows = [];
        $toCreate = 0;
        $existsCount = 0;
        $totalToCreate = 0;

        foreach ($communes as $commune) {
            $panels = (int) ($panelCounts[$commune->id] ?? 0);
            if ($panels === 0) continue; // pas de panneau → pas de taxe

            foreach (self::TYPES as $type) {
                $rate = (float) match ($type) {
                    'odp' => $commune->odp_rate,
                    'tm'  => $commune->tm_rate,
                    'db'  => $commune->db_rate,
                    default => 0,
                };
                if ($rate <= 0) continue; // commune sans tarif sur ce type → skip

                $amount = round($panels * $rate, 2);
                $key    = $commune->id . ':' . $type;
                $exists = $existing->has($key);

                $row = [
                    'commune_id'      => $commune->id,
                    'commune'         => $commune->name,
                    'type'            => $type,
                    'panels'          => $panels,
                    'rate'            => $rate,
                    'amount'          => $amount,
                    'exists'          => $exists,
                    'existing_status' => $exists ? $existing[$key]->status : null,
                    'existing_id'     => $exists ? $existing[$key]->id : null,
                ];
                $rows[] = $row;

                if ($exists) {
                    $existsCount++;
                } else {
                    $toCreate++;
                    $totalToCreate += $amount;
                }
            }
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'summary' => [
                'to_create'              => $toCreate,
                'exists'                 => $existsCount,
                'total_amount_to_create' => $totalToCreate,
            ],
        ];
    }

    /**
     * Applique la génération : crée les lignes manquantes uniquement,
     * ne touche pas aux lignes existantes (même si l'amount calculé a
     * changé — on n'écrase pas une éventuelle saisie/négociation).
     *
     * @return array{created: int, skipped: int, total_amount: float, year: int}
     */
    public function apply(int $year): array
    {
        $preview = $this->preview($year);
        $created = 0;
        $skipped = 0;
        $totalAmount = 0;

        DB::transaction(function () use ($preview, &$created, &$skipped, &$totalAmount, $year) {
            foreach ($preview['rows'] as $row) {
                if ($row['exists']) { $skipped++; continue; }

                Tax::create([
                    'commune_id' => $row['commune_id'],
                    'year'       => $year,
                    'type'       => $row['type'],
                    'amount'     => $row['amount'],
                    // Échéance par défaut : 31 mars de l'année (date typique
                    // côté collectivités CI). L'admin peut l'ajuster plus
                    // tard via la fiche taxe.
                    'due_date'   => sprintf('%d-03-31', $year),
                    'status'     => 'en_attente',
                ]);

                $created++;
                $totalAmount += $row['amount'];
            }
        });

        Log::info('taxes.auto.generated', [
            'year'    => $year,
            'created' => $created,
            'skipped' => $skipped,
            'amount'  => $totalAmount,
            'by'      => auth()->id(),
        ]);

        return [
            'year'         => $year,
            'created'      => $created,
            'skipped'      => $skipped,
            'total_amount' => $totalAmount,
        ];
    }
}
