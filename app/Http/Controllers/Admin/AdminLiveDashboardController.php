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
