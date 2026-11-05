<?php

namespace App\Services\Campagnes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtres de la liste des campagnes — source unique (2026-11-05).
 *
 * La liste (CampaignController::index), l'export Excel (CampaignsExport) et
 * l'export PDF (CampaignController::exportPdf) appliquent EXACTEMENT les
 * mêmes critères, périmètre du commercial compris. Avant, chacun recopiait
 * ses filtres : les exports ignoraient le commercial, la commune et la
 * zone, et un commercial exportait les campagnes de tout le monde.
 *
 * Trois règles de dates, validées le 2026-11-05 :
 *   · « Lancée du / au » (date_from / date_to, et l'ancien couple
 *     date_debut / date_fin) : la campagne a DÉBUTÉ dans la période, quelle
 *     que soit sa fin. Avant : débutée après le « Du » ET finie avant le
 *     « Au » — une campagne lancée en septembre et finie en décembre
 *     n'apparaissait pas dans « septembre ».
 *   · « Active pendant » (actives_du / actives_au) : la campagne chevauche
 *     la période, hors annulées — règle de l'indicateur « CA TTC équipe »
 *     de la Performance commerciale, dont le clic ouvre cette liste.
 *   · « Finit bientôt » (fin_proche = N jours) : Campaign::endingSoon(N),
 *     la règle du compteur du bandeau « se terminent dans moins de 14 jours ».
 *
 * Le statut n'en fait pas partie : la liste le filtre APRÈS ses compteurs.
 */
final class CampagneFiltres
{
    /** Critères reconnus dans l'URL de la liste des campagnes. */
    public const CLES = [
        'search', 'client_id', 'commercial_user_id',
        'date_from', 'date_to', 'date_debut', 'date_fin',
        'actives_du', 'actives_au', 'fin_proche',
        'created_from', 'created_to',
        'non_facturee', 'commune_id', 'zone_id',
    ];

    /** Les critères renseignés dans la requête (ni statut, ni page). */
    public static function depuis(Request $request): array
    {
        return array_filter(
            $request->only(self::CLES),
            fn ($valeur) => $valeur !== null && $valeur !== ''
        );
    }

    /**
     * Périmètre de l'utilisateur + critères. Un commercial ne voit que SES
     * campagnes (Campaign::forCommercialUser), à l'écran comme à l'export.
     */
    public static function appliquer(Builder $q, array $f, ?User $user): Builder
    {
        if ($user?->role?->value === 'commercial') {
            $q->forCommercialUser((int) $user->id);
        }

        if (!empty($f['search']))    $q->where('name', 'like', "%{$f['search']}%");
        if (!empty($f['client_id'])) $q->where('client_id', $f['client_id']);
        // Campagnes d'un commercial (suivi de portefeuille) : même règle
        // d'appartenance que le périmètre ci-dessus.
        if (!empty($f['commercial_user_id'])) $q->forCommercialUser((int) $f['commercial_user_id']);

        // Lancée dans la période : date de DÉBUT entre les deux bornes.
        foreach (['date_from', 'date_debut'] as $cle) {
            if (!empty($f[$cle])) $q->where('start_date', '>=', $f[$cle]);
        }
        foreach (['date_to', 'date_fin'] as $cle) {
            if (!empty($f[$cle])) $q->where('start_date', '<=', $f[$cle]);
        }

        // Active pendant la période : chevauchement, hors campagnes annulées —
        // la règle de CommercialPerformanceService::campaignsBaseQuery.
        if (!empty($f['actives_du']) || !empty($f['actives_au'])) {
            $q->where('status', '!=', 'annule');
        }
        if (!empty($f['actives_du'])) $q->where('end_date', '>=', $f['actives_du']);
        if (!empty($f['actives_au'])) $q->where('start_date', '<=', $f['actives_au']);

        if (!empty($f['fin_proche'])) $q->endingSoon(max(1, (int) $f['fin_proche']));

        // Créées dans la période (indicateur « Nouvelles campagnes »).
        if (!empty($f['created_from'])) $q->whereDate('campaigns.created_at', '>=', $f['created_from']);
        if (!empty($f['created_to']))   $q->whereDate('campaigns.created_at', '<=', $f['created_to']);

        if (!empty($f['non_facturee'])) $q->nonFacturees();
        if (!empty($f['commune_id'])) $q->whereHas('panels', fn ($p) => $p->where('commune_id', $f['commune_id']));
        if (!empty($f['zone_id']))    $q->whereHas('panels', fn ($p) => $p->where('zone_id', $f['zone_id']));

        return $q;
    }
}
