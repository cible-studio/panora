<?php

namespace App\Console\Commands;

use App\Models\CommuneTaxPayment;
use App\Models\TaxeReglement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des mairies (2026-11-16) — recopie les versements déjà saisis dans
 * les deux anciens suivis vers la table unique des règlements :
 *
 *   · commune_tax_payments (page Taxes, modale « Enregistrer paiement ») ;
 *   · taxes, lignes marquées payées (ancien module « Historique »).
 *
 * Rien n'est supprimé ni modifié dans les anciennes tables (règle n° 3).
 * Rejouable : (origine, origine_id) est unique, une ligne déjà reprise est
 * ignorée. Simulation par défaut ; --appliquer écrit réellement.
 */
class ReprendreReglementsTaxes extends Command
{
    protected $signature = 'taxes:reprendre-reglements
                            {--appliquer : écrit réellement en base (sans cette option, simple simulation)}';

    protected $description = 'Recopie les versements des anciens suivis de taxes vers les règlements du Suivi des mairies';

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $deja = TaxeReglement::query()
            ->whereIn('origine', [TaxeReglement::ORIGINE_PAIEMENT, TaxeReglement::ORIGINE_TAXE])
            ->get(['origine', 'origine_id'])
            ->map(fn ($r) => $r->origine . ':' . $r->origine_id)
            ->flip();

        $aCreer = collect();

        foreach (CommuneTaxPayment::orderBy('id')->get() as $p) {
            $odp = (int) $p->odp_paye;
            $tm  = (int) $p->tm_paye;
            if ($odp + $tm <= 0) continue;
            if ($deja->has(TaxeReglement::ORIGINE_PAIEMENT . ':' . $p->id)) continue;

            $periode = $p->period_type . ' ' . $p->period_year
                . ($p->period_type === 'annuel' ? '' : '-' . str_pad((string) $p->period_value, 2, '0', STR_PAD_LEFT));
            $aCreer->push([
                'commune_id'     => $p->commune_id,
                'exercice'       => (int) $p->period_year,
                'montant'        => $odp + $tm,
                'paid_at'        => ($p->paid_at ?? $p->created_at)?->toDateString(),
                'objet'          => $tm === 0 ? TaxeReglement::OBJET_ODP
                                  : ($odp === 0 ? TaxeReglement::OBJET_TM : TaxeReglement::OBJET_GLOBAL),
                'mode'           => $p->mode,
                'reference'      => $p->reference,
                'quittance_path' => $p->attestation_path,
                'commentaire'    => trim('Repris du suivi précédent (' . $periode
                                  . ' · ODP ' . number_format($odp, 0, ',', ' ')
                                  . ' · TM ' . number_format($tm, 0, ',', ' ') . ').'
                                  . ($p->comment ? "\n" . $p->comment : '')
                                  . ($p->notes ? "\n" . $p->notes : '')),
                'recorded_by'    => $p->recorded_by,
                'origine'        => TaxeReglement::ORIGINE_PAIEMENT,
                'origine_id'     => $p->id,
            ]);
        }

        if (Schema::hasTable('taxes')) {
            $taxes = DB::table('taxes')->whereNotNull('paid_at')->where('amount', '>', 0)->orderBy('id')->get();
            foreach ($taxes as $t) {
                if ($deja->has(TaxeReglement::ORIGINE_TAXE . ':' . $t->id)) continue;
                $aCreer->push([
                    'commune_id'     => $t->commune_id,
                    'exercice'       => (int) $t->year,
                    'montant'        => (int) round((float) $t->amount),
                    'paid_at'        => substr((string) $t->paid_at, 0, 10),
                    'objet'          => in_array($t->type, ['odp', 'tm'], true) ? $t->type : TaxeReglement::OBJET_GLOBAL,
                    'mode'           => null,
                    'reference'      => null,
                    'quittance_path' => null,
                    'commentaire'    => "Repris de l'ancien historique des taxes.",
                    'recorded_by'    => null,
                    'origine'        => TaxeReglement::ORIGINE_TAXE,
                    'origine_id'     => $t->id,
                ]);
            }
        }

        $aCreer = $aCreer->filter(fn ($r) => !empty($r['commune_id']) && !empty($r['paid_at']));
        $reprise = (int) config('taxes.exercice_reprise', 2026);
        $communes = \App\Models\Commune::pluck('name', 'id');

        if ($aCreer->isEmpty()) {
            $this->info('Rien à reprendre : tous les versements sont déjà dans le Suivi des mairies.');
            return self::SUCCESS;
        }

        $this->table(
            ['Origine', 'Mairie', 'Exercice', 'Montant', 'Versé le', 'Objet'],
            $aCreer->map(fn ($r) => [
                $r['origine'] . ' #' . $r['origine_id'],
                $communes[$r['commune_id']] ?? ('#' . $r['commune_id']),
                $r['exercice'] . ($r['exercice'] < $reprise ? ' (hors suivi)' : ''),
                number_format($r['montant'], 0, ',', ' '),
                $r['paid_at'],
                $r['objet'],
            ])->all()
        );
        $this->line(sprintf(
            '%d versement(s), %s FCFA. Les exercices antérieurs à %d sont recopiés pour la trace mais n\'apparaissent pas dans le suivi.',
            $aCreer->count(),
            number_format($aCreer->sum('montant'), 0, ',', ' '),
            $reprise
        ));

        if (!$appliquer) {
            $this->warn('Simulation : rien n\'a été écrit. Relancer avec --appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aCreer) {
            foreach ($aCreer as $r) {
                TaxeReglement::create($r);
            }
        });

        Log::info('taxes.reprendre_reglements', [
            'nombre'  => $aCreer->count(),
            'montant' => $aCreer->sum('montant'),
        ]);
        $this->info('✓ ' . $aCreer->count() . ' versement(s) repris.');

        return self::SUCCESS;
    }
}
