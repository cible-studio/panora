<?php

namespace App\Observers;

use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * CommuneObserver — historisation tarifaire automatique.
 *
 * À chaque modification d'un tarif (odp_rate / tm_rate / db_rate) sur
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
    public function updated(Commune $commune): void
    {
        $changed = $commune->getChanges();
        $rateChanged = array_intersect_key($changed, array_flip(['odp_rate', 'tm_rate', 'db_rate']));
        if (empty($rateChanged)) return;

        $today = Carbon::today();

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
            'db_rate'        => (float) $commune->db_rate,
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
