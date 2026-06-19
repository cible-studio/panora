<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminLiveDashboardService;
use Illuminate\Http\JsonResponse;

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
    public function __construct(protected AdminLiveDashboardService $service) {}

    /**
     * GET /admin/dashboard/live → JSON payload temps réel.
     * Polling 20s depuis le navigateur admin (cf. spec §2.1).
     */
    public function live(): JsonResponse
    {
        $payload = $this->service->buildLivePayload();
        return response()->json($payload);
    }
}
