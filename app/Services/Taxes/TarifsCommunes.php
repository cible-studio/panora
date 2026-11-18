<?php

namespace App\Services\Taxes;

use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enregistrer un tarif ODP / TM d'une commune à une date d'effet
 * (2026-11-18) — depuis l'écran « Tarifs communes ».
 *
 * Demande du 18/11 : les nouveaux tarifs (Port-Bouët, Adjamé, panneaux
 * éclairés) doivent pouvoir être saisis À L'ÉCRAN, avec effet au
 * 01/01/2026, quand ils seront confirmés. Avant, l'écran n'acceptait
 * qu'une date future.
 *
 * Une date PASSÉE est permise, avec un motif obligatoire (contrôleur) :
 *   · elle recalcule les taxes dues depuis cette date ;
 *   · elle ne touche JAMAIS une facture émise (taux figés sur ses lignes) ;
 *   · l'historique garde tout : la ligne précédente est refermée la veille.
 *
 * L'historique reste une suite de périodes sans chevauchement :
 *   · la période qui couvrait la date d'effet se termine la veille ;
 *   · la nouvelle va jusqu'à la veille du tarif suivant déjà connu, ou
 *     sans fin ;
 *   · même date d'effet qu'une ligne existante → la ligne est mise à jour.
 * Si la nouvelle période couvre aujourd'hui, le tarif « courant » de la
 * commune suit (sans déclencher l'historisation automatique du crayon).
 */
class TarifsCommunes
{
    /**
     * @param array{odp: int, tm: int, odp_eclaire?: ?int, tm_eclaire?: ?int} $tarif
     */
    public function enregistrer(Commune $commune, \Carbon\CarbonInterface $effet, array $tarif, ?string $notes = null, ?User $par = null): CommuneTaxRateHistory
    {
        return DB::transaction(function () use ($commune, $effet, $tarif, $notes, $par) {
            $effet   = Carbon::instance($effet)->startOfDay();
            $jour    = $effet->toDateString();
            $veille  = $effet->copy()->subDay()->toDateString();

            // Le tarif suivant déjà connu borne la nouvelle période.
            $suivant = CommuneTaxRateHistory::where('commune_id', $commune->id)
                ->whereDate('effective_from', '>', $jour)
                ->orderBy('effective_from')->first();
            $fin = $suivant?->effective_from?->copy()->subDay()->toDateString();

            // Commune sans aucun historique : son tarif d'origine vaut pour
            // tout ce qui précède (même garde que CommuneObserver).
            if (!CommuneTaxRateHistory::where('commune_id', $commune->id)->exists() && $effet->gt(Carbon::create(2000, 1, 1))) {
                CommuneTaxRateHistory::create([
                    'commune_id'       => $commune->id,
                    'odp_rate'         => (int) $commune->odp_rate,
                    'tm_rate'          => (int) $commune->tm_rate,
                    'odp_rate_eclaire' => $commune->odp_rate_eclaire,
                    'tm_rate_eclaire'  => $commune->tm_rate_eclaire,
                    'effective_from'   => \App\Observers\CommuneObserver::DEBUT_SANS_HISTORIQUE,
                    'effective_to'     => $veille,
                    'created_by'       => $par?->id,
                    'notes'            => '[Auto] Tarif d\'origine, conservé avant le premier tarif daté',
                ]);
            }

            // La période qui couvrait la date d'effet se termine la veille.
            CommuneTaxRateHistory::where('commune_id', $commune->id)
                ->whereDate('effective_from', '<', $jour)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $jour))
                ->update(['effective_to' => $veille]);

            $valeurs = [
                'odp_rate'         => (int) $tarif['odp'],
                'tm_rate'          => (int) $tarif['tm'],
                'odp_rate_eclaire' => isset($tarif['odp_eclaire']) && $tarif['odp_eclaire'] !== '' ? (int) $tarif['odp_eclaire'] : null,
                'tm_rate_eclaire'  => isset($tarif['tm_eclaire']) && $tarif['tm_eclaire'] !== '' ? (int) $tarif['tm_eclaire'] : null,
                'effective_to'     => $fin,
                'created_by'       => $par?->id,
                'notes'            => $notes ?: '[Manuel] Tarif enregistré depuis l\'écran Tarifs communes',
            ];

            $ligne = CommuneTaxRateHistory::where('commune_id', $commune->id)->whereDate('effective_from', $jour)->first();
            if ($ligne) {
                $ligne->update($valeurs);
            } else {
                $ligne = CommuneTaxRateHistory::create($valeurs + ['commune_id' => $commune->id, 'effective_from' => $jour]);
            }

            // Tarif « courant » de la fiche : suit la période qui couvre aujourd'hui.
            if ($effet->lte(today()) && ($fin === null || Carbon::parse($fin)->gte(today()))) {
                Commune::withoutEvents(fn () => $commune->forceFill([
                    'odp_rate'         => $valeurs['odp_rate'],
                    'tm_rate'          => $valeurs['tm_rate'],
                    'odp_rate_eclaire' => $valeurs['odp_rate_eclaire'],
                    'tm_rate_eclaire'  => $valeurs['tm_rate_eclaire'],
                ])->save());
            }

            Log::warning('commune.tarif_enregistre', [
                'commune' => $commune->name, 'effet' => $jour, 'jusqu_au' => $fin,
                'tarif' => $valeurs, 'retroactif' => $effet->lt(today()), 'par' => $par?->id,
            ]);

            return $ligne->fresh();
        });
    }
}
