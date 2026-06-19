<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminLiveDashboardService;
use App\Services\TechTimelineService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SM2b Lot 1.2 — Controller du dashboard admin live.
 *
 * Minimaliste : la logique vit dans AdminLiveDashboardService. Le
 * controller se contente de matérialiser la route, d'éventuellement
 * gérer du cache HTTP, et de renvoyer le JSON.
 *
 * À ce stade (Phase 1), seul le endpoint /live est implémenté. La vue
 * Blade arrive Phase 2.
 */
class AdminLiveDashboardController extends Controller
{
    public function __construct(
        protected AdminLiveDashboardService $service,
        protected TechTimelineService       $timeline,
    ) {}

    /**
     * GET /admin/dashboard/live → JSON payload temps réel.
     * Polling 20s depuis le navigateur admin (cf. spec §2.1).
     */
    public function live(): JsonResponse
    {
        $payload = $this->service->buildLivePayload();
        return response()->json($payload);
    }

    /**
     * GET /admin/map/live → positions GPS des techs actuellement en ligne.
     *
     * Source de la position : dernière Pige envoyée par le tech (proxy
     * raisonnable — précis à quelques minutes en moyenne, déjà stockée
     * en BDD). Pas de heartbeat GPS pour l'instant — sera ajouté si la
     * patronne demande un suivi temps réel à la minute près (migration
     * + update body côté tech).
     */
    public function mapLive(): JsonResponse
    {
        $onlineSince = now()->subMinutes(AdminLiveDashboardService::ONLINE_WINDOW_MIN);

        $techs = User::query()
            ->where('role', \App\Enums\UserRole::TECHNIQUE->value)
            ->where('is_active', true)
            ->where('last_seen_at', '>=', $onlineSince)
            ->get(['id', 'name', 'last_seen_at']);

        $rows = $techs->map(function (User $u) {
            // Dernière pige avec coordonnées GPS — source unique de la
            // position "actuelle" approchée. Si aucune pige avec GPS dans
            // les 24h, on n'affiche pas ce tech sur la carte.
            $lastPige = \App\Models\Pige::query()
                ->where('user_id', $u->id)
                ->whereNotNull('gps_lat')
                ->whereNotNull('gps_lng')
                ->where('created_at', '>=', now()->subDay())
                ->with(['panel:id,name'])
                ->latest('created_at')
                ->first();

            if (!$lastPige) return null;

            return [
                'id'                 => $u->id,
                'initials'           => mb_substr($u->name, 0, 2),
                'full_name'          => $u->name,
                'lat'                => (float) $lastPige->gps_lat,
                'lng'                => (float) $lastPige->gps_lng,
                'gps_at'             => $lastPige->created_at?->toIso8601String(),
                'current_pose_label' => $lastPige->panel?->name,
                'last_seen_at'       => optional($u->last_seen_at)->toIso8601String(),
            ];
        })->filter()->values()->all();

        return response()->json([
            'as_of' => now()->toIso8601String(),
            'techs' => $rows,
        ]);
    }

    /**
     * GET /admin/tech/{user}/timeline → frise chronologique d'un tech.
     * Param query optionnel : ?date=YYYY-MM-DD (défaut: today).
     */
    public function techTimeline(Request $request, User $user): JsonResponse
    {
        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->input('date'))
            : CarbonImmutable::today();

        $events = $this->timeline->buildTimeline($user, $date);

        return response()->json([
            'as_of'  => now()->toIso8601String(),
            'tech'   => [
                'id'        => $user->id,
                'full_name' => $user->name,
                'initials'  => mb_substr($user->name, 0, 2),
            ],
            'date'   => $date->format('Y-m-d'),
            'events' => $events,
        ]);
    }
}
