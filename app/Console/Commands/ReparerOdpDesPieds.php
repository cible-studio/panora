<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Services\Axes\AxeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rattrape l'ancienneté ODP des pieds d'axe — correctif du 2026-11-13.
 *
 * ═══ LE DÉFAUT ═══
 * Panel::booted() remplit `odp_start_date` à la date du jour pour tout
 * panneau créé. Les 101 pieds nés du découpage des 11 et 13/11/2026 ont
 * donc une date de départ ODP en novembre 2026 — alors que les mâts sont
 * plantés depuis des années : seule leur ligne dans Panora est récente.
 *
 * Conséquence constatée à l'écran : sur un exercice ANNUEL, l'ODP d'un
 * pied Lumipub se calculait sur 2 mois (novembre + décembre) au lieu de
 * 12. Le docblock de Panel::booted() avertit précisément de ce cas pour
 * les imports de panneaux historiques.
 *
 * ═══ LA RÈGLE ═══
 * Un pied porte la MÊME ancienneté que son axe — y compris NULL, qui
 * signifie « panneau préexistant, ODP due sur toute la période »
 * (TX-10, validé par la patronne).
 *
 * Simulation par défaut, comme axes:decouper. Rien n'est écrit sans
 * --appliquer.
 */
class ReparerOdpDesPieds extends Command
{
    protected $signature = 'axes:reparer-odp
                            {--appliquer : Écrire les corrections (sinon simulation)}
                            {--axe= : Limiter à une référence d\'axe}';

    protected $description = "Aligne la date de départ ODP des pieds sur celle de leur axe (simulation par défaut)";

    public function handle(AxeService $axes): int
    {
        if (! $axes->colonnesPresentes()) {
            $this->error('Colonnes axe_id / est_axe absentes : joue d\'abord php artisan migrate.');
            return self::FAILURE;
        }

        $applique = (bool) $this->option('appliquer');

        $requete = Panel::query()->whereNotNull('axe_id')->whereNull('deleted_at');
        if ($ref = $this->option('axe')) {
            $axe = Panel::where('reference', $ref)->where('est_axe', true)->first();
            if (! $axe) {
                $this->error("Axe {$ref} introuvable.");
                return self::FAILURE;
            }
            $requete->where('axe_id', $axe->id);
        }

        $pieds = $requete->orderBy('reference')->get(
            ['id', 'reference', 'axe_id', 'odp_start_date', 'faces_par_pied']
        );

        if ($pieds->isEmpty()) {
            $this->info('Aucun pied à examiner.');
            return self::SUCCESS;
        }

        $parAxe = Panel::whereIn('id', $pieds->pluck('axe_id')->unique()->filter())
            ->get(['id', 'reference', 'name', 'odp_start_date', 'faces_par_pied'])
            ->keyBy('id');

        $lignes      = [];
        $aCorriger   = [];
        $sansFaces   = 0;

        foreach ($pieds as $pied) {
            $axe = $parAxe->get($pied->axe_id);
            if (! $axe) continue;

            if (empty($pied->faces_par_pied)) $sansFaces++;

            $actuelle = $pied->odp_start_date?->toDateString();
            $attendue = $axe->odp_start_date?->toDateString();

            if ($actuelle === $attendue) continue;

            $aCorriger[] = $pied;
            $lignes[] = [
                $axe->reference,
                $pied->reference,
                $actuelle ?? 'NULL (préexistant)',
                $attendue ?? 'NULL (préexistant)',
            ];
        }

        if (empty($aCorriger)) {
            $this->info("✅ Les {$pieds->count()} pied(s) portent déjà l'ancienneté de leur axe.");
            if ($sansFaces) {
                $this->warn("⚠ {$sansFaces} pied(s) sans faces_par_pied : le calcul retombe sur le nom de l'axe.");
            }
            return self::SUCCESS;
        }

        $this->table(['AXE', 'PIED', 'ACTUELLE', 'ATTENDUE'], $lignes);
        $this->newLine();
        $this->warn(count($aCorriger) . ' pied(s) à corriger sur ' . $pieds->count() . '.');

        if ($sansFaces) {
            $this->warn("⚠ {$sansFaces} pied(s) sans faces_par_pied : le calcul des faces "
                . "retombe sur le nom de l'axe. Rejoue axes:decouper si besoin.");
        }

        if (! $applique) {
            $this->newLine();
            $this->info('SIMULATION — rien n\'a été modifié.');
            $this->line('Relance avec --appliquer pour écrire les corrections.');
            return self::SUCCESS;
        }

        foreach ($aCorriger as $pied) {
            $axe = $parAxe->get($pied->axe_id);
            $pied->forceFill(['odp_start_date' => $axe->odp_start_date])->save();
        }

        Log::info('axes.reparer_odp', [
            'pieds_corriges' => count($aCorriger),
            'par'            => auth()->id(),
        ]);

        $this->newLine();
        $this->info('✅ ' . count($aCorriger) . ' pied(s) corrigé(s).');
        $this->line("Recalcule l'ODP annuelle : les pieds doivent maintenant compter 12 mois.");

        return self::SUCCESS;
    }
}
