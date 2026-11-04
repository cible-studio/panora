<?php

namespace App\Services\Piges;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filtres de la page Piges — source unique (2026-11-04).
 *
 * La liste (PigeController::index) et l'export PDF (RapportPigesService)
 * appliquent exactement les mêmes critères : ce que le MP voit à l'écran
 * est ce qu'il exporte. Le statut n'en fait pas partie : la liste le
 * filtre APRÈS ses compteurs, et l'export ne prend que les vérifiées.
 *
 * Ajouts du 2026-11-04 : client (« toutes les piges de Moov »), commune,
 * et la recherche couvre aussi le nom du client et de la commune.
 */
final class PigeFiltres
{
    /** Critères reconnus dans l'URL de la page Piges. */
    public const CLES = [
        'q', 'client_id', 'campaign_id', 'commune_id', 'technicien_id',
        'panel_id', 'geo_check', 'date_from', 'date_to',
    ];

    /** Les critères renseignés dans la requête (ni statut, ni page). */
    public static function depuis(Request $request): array
    {
        return array_filter(
            $request->only(self::CLES),
            fn ($valeur) => $valeur !== null && $valeur !== ''
        );
    }

    public static function appliquer(Builder $query, array $f): Builder
    {
        if (!empty($f['client_id'])) {
            $query->whereHas('campaign', fn ($c) => $c->where('client_id', $f['client_id']));
        }
        if (!empty($f['campaign_id']))   $query->where('campaign_id', $f['campaign_id']);
        if (!empty($f['commune_id'])) {
            $query->whereHas('panel', fn ($p) => $p->where('commune_id', $f['commune_id']));
        }
        if (!empty($f['panel_id']))      $query->where('panel_id', $f['panel_id']);
        if (!empty($f['technicien_id'])) $query->where('user_id', $f['technicien_id']);
        if (!empty($f['geo_check']))     $query->where('geo_check', $f['geo_check']);
        if (!empty($f['date_from']))     $query->whereDate('taken_at', '>=', $f['date_from']);
        if (!empty($f['date_to']))       $query->whereDate('taken_at', '<=', $f['date_to']);

        if (!empty($f['q'])) {
            $q = $f['q'];
            $query->where(fn ($sq) =>
                $sq->whereHas('panel', fn ($p) =>
                    $p->where('reference', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhereHas('commune', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                )
                ->orWhereHas('campaign', fn ($c) =>
                    $c->where('name', 'like', "%{$q}%")
                      ->orWhereHas('client', fn ($cl) => $cl->where('name', 'like', "%{$q}%"))
                )
                ->orWhereHas('technicien', fn ($u) => $u->where('name', 'like', "%{$q}%"))
            );
        }

        return $query;
    }

    /**
     * Les choix des listes Client, Campagne et Commune de la page Piges.
     *
     * Ne proposent que ce qui a des piges (non archivées), campagnes
     * TERMINÉES comprises — avant le 2026-11-04, seules les actives
     * figuraient, d'où l'impossibilité de retrouver les piges d'une
     * campagne finie. Un client choisi restreint les campagnes aux
     * siennes ; les campagnes en cours d'abord.
     *
     * @return array{clients: Collection, campaigns: Collection, communes: Collection}
     */
    public static function listes(array $f): array
    {
        $avecPiges = fn ($q) => $q->active();

        return [
            'clients' => Client::whereHas('campaigns.piges', $avecPiges)
                ->orderBy('name')->get(['id', 'name']),
            'campaigns' => Campaign::whereHas('piges', $avecPiges)
                ->when($f['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
                ->orderByRaw('status = ? desc', [CampaignStatus::ACTIF->value])
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'communes' => Commune::whereHas('panels.piges', $avecPiges)
                ->orderBy('name')->get(['id', 'name']),
        ];
    }
}
