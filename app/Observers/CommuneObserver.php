<?php

namespace App\Observers;

use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * CommuneObserver — historisation tarifaire automatique.
 *
 * À chaque modification d'un tarif (odp_rate / tm_rate) sur
 * une commune, on :
 *   1. ferme la ligne d'historique courante (effective_to = veille)
 *   2. crée une nouvelle ligne avec les nouveaux tarifs et effective_from
 *      = aujourd'hui
 *
 * Garantit que tout calcul rétroactif (rapport sur un mois passé)
 * s'appuie sur les tarifs en vigueur à l'époque, pas sur les valeurs
 * actuelles — indispensable pour les contrôles communaux.
 *
 * Si plusieurs changements le même jour : on écrase la ligne d'aujourd'hui
 * (pas de duplication par minute, on garde une granularité jour).
 */
class CommuneObserver
{
    /** Début de validité de l'ancien tarif d'une commune qui n'avait aucun historique. */
    public const DEBUT_SANS_HISTORIQUE = '2000-01-01';

    public function updated(Commune $commune): void
    {
        $changed = $commune->getChanges();
        $rateChanged = array_intersect_key($changed, array_flip(['odp_rate', 'tm_rate']));
        if (empty($rateChanged)) return;

        $today = Carbon::today();

        // 2026-11-18 — Commune encore sans aucune ligne d'historique (ex.
        // ADIAKE-ASSINIE) : Commune::ratesAt() retombe alors sur le tarif
        // COURANT pour toutes les dates, et la modification devenait
        // rétroactive, contrairement au message affiché. On fige d'abord
        // l'ancien tarif jusqu'à la veille.
        if (!CommuneTaxRateHistory::where('commune_id', $commune->id)->exists()) {
            CommuneTaxRateHistory::create([
                'commune_id'     => $commune->id,
                'odp_rate'       => (float) $commune->getOriginal('odp_rate'),
                'tm_rate'        => (float) $commune->getOriginal('tm_rate'),
                'effective_from' => self::DEBUT_SANS_HISTORIQUE,
                'effective_to'   => $today->copy()->subDay()->toDateString(),
                'created_by'     => auth()->id(),
                'notes'          => '[Auto] Tarif d\'origine, conservé avant la première modification',
            ]);
        }

        // Ferme l'historique précédent encore ouvert.
        CommuneTaxRateHistory::where('commune_id', $commune->id)
            ->whereNull('effective_to')
            ->where('effective_from', '<', $today->toDateString())
            ->update(['effective_to' => $today->copy()->subDay()->toDateString()]);

        // Granularité jour : si une ligne existe déjà avec effective_from
        // = aujourd'hui, on la met à jour au lieu d'en créer une autre.
        $existing = CommuneTaxRateHistory::where('commune_id', $commune->id)
            ->where('effective_from', $today->toDateString())
            ->first();

        $payload = [
            'commune_id'     => $commune->id,
            'odp_rate'       => (float) $commune->odp_rate,
            'tm_rate'        => (float) $commune->tm_rate,
            'effective_from' => $today,
            'effective_to'   => null,
            'created_by'     => auth()->id(),
            'notes'          => '[Auto] Mise à jour des tarifs : '
                . implode(', ', array_map(
                    fn($k) => $k . ' = ' . $commune->{$k},
                    array_keys($rateChanged)
                )),
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            CommuneTaxRateHistory::create($payload);
        }

        Log::info('commune.rates_updated', [
            'commune_id' => $commune->id,
            'changes'    => $rateChanged,
            'by'         => auth()->id(),
        ]);
    }
}
