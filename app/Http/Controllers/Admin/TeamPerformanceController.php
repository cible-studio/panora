<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PoseTeam;
use App\Services\TechnicianPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * M2 Performance Équipe — pages /admin/performance/equipes.
 *
 * Routes :
 *   GET /admin/performance/equipes        → leaderboard équipes (admin/MP)
 *   GET /admin/performance/equipes/{team} → drill équipe
 *
 * RBAC : admin + mediaplanner uniquement. Tech / commercial bloqués.
 */
class TeamPerformanceController extends Controller
{
    public function __construct(protected TechnicianPerformanceService $perf) {}

    public function index(Request $request)
    {
        $role = $request->user()->role?->value;
        if (!in_array($role, ['admin', 'mediaplanner'], true)) {
            abort(403, 'Accès réservé à l\'administration et media planners.');
        }

        [$from, $to] = $this->resolvePeriod($request);
        $leaderboard = $this->perf->leaderboardTeams($from, $to);

        return view('admin.performance.equipes.index', [
            'leaderboard' => $leaderboard,
            'from'        => $from,
            'to'          => $to,
            'preset'      => $request->input('preset'),
        ]);
    }

    public function show(Request $request, PoseTeam $team)
    {
        $role = $request->user()->role?->value;
        if (!in_array($role, ['admin', 'mediaplanner'], true)) {
            abort(403);
        }

        [$from, $to] = $this->resolvePeriod($request);

        $team->load(['leader:id,name,agent_code', 'members:id,name,pose_team_id,agent_code']);
        $aggregated = $this->perf->byTeam($team->id, $from, $to);

        // Classement des membres au sein de l'équipe
        $memberRanking = $team->members->map(fn ($m) => [
            'user' => $m,
            'kpis' => $this->perf->kpis($m->id, $from, $to),
        ])->sortByDesc(fn ($r) => $r['kpis']['nb_poses_realisees'])->values();

        return view('admin.performance.equipes.show', compact(
            'team', 'aggregated', 'memberRanking', 'from', 'to'
        ));
    }

    protected function resolvePeriod(Request $request): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            try {
                return [Carbon::parse($request->input('from'))->startOfDay(),
                        Carbon::parse($request->input('to'))->endOfDay()];
            } catch (\Throwable) {}
        }
        return match ($request->input('preset')) {
            'month'   => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->firstOfQuarter(), now()->lastOfQuarter()],
            'all'     => [Carbon::create(2020, 1, 1), now()],
            default   => [now()->startOfYear(), now()->endOfYear()],
        };
    }
}
