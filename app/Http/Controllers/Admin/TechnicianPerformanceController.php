<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TechnicianPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * M2 Performance Technicien — pages /admin/performance/techniciens.
 *
 * Routes :
 *   GET /admin/performance/techniciens          → leaderboard (admin/MP)
 *   GET /admin/performance/techniciens/me       → alias self (technique)
 *   GET /admin/performance/techniciens/{user}   → drill
 */
class TechnicianPerformanceController extends Controller
{
    public function __construct(protected TechnicianPerformanceService $perf) {}

    public function index(Request $request)
    {
        $role = $request->user()->role?->value;
        if ($role === 'technique') {
            return redirect()->route('admin.performance.tech.show', $request->user());
        }
        if (!in_array($role, ['admin', 'mediaplanner'], true)) {
            abort(403, 'Accès réservé à l\'administration et media planners.');
        }

        [$from, $to] = $this->resolvePeriod($request);
        $leaderboard = $this->perf->leaderboardTechs($from, $to);

        // Alignement Performance Commerciale : KPI globaux équipe + courbe 12 mois
        $globalKpis  = $this->perf->globalTeamKpis($from, $to);
        $globalTrend = $this->perf->globalMonthlyTrend(12);

        return view('admin.performance.techniciens.index', [
            'leaderboard' => $leaderboard,
            'globalKpis'  => $globalKpis,
            'globalTrend' => $globalTrend,
            'from'        => $from,
            'to'          => $to,
            'preset'      => $request->input('preset'),
        ]);
    }

    public function me(Request $request)
    {
        return redirect()->route('admin.performance.tech.show', $request->user());
    }

    public function show(Request $request, User $user)
    {
        $effectiveId = $this->perf->resolveTechIdForCurrentUser($user->id, $request->user());
        if ($effectiveId === null) abort(403);
        if ($effectiveId !== $user->id) {
            return redirect()->route('admin.performance.tech.show', $request->user());
        }
        if ($user->role?->value !== 'technique') {
            abort(404, 'Cet utilisateur n\'est pas un technicien.');
        }

        [$from, $to] = $this->resolvePeriod($request);

        $kpis          = $this->perf->kpis($user->id, $from, $to);
        $dailyPoses    = $this->perf->dailyPoses($user->id, 30);
        $reactivity    = $this->perf->reactivityDistribution($user->id, $from, $to);
        $poses         = $this->perf->posesList($user->id, $from, $to, 15);

        $user->load('poseTeam:id,name,color_slug', 'teamLeading:id,name');

        return view('admin.performance.techniciens.show', compact(
            'user', 'kpis', 'dailyPoses', 'reactivity', 'poses', 'from', 'to'
        ));
    }

    protected function resolvePeriod(Request $request): array
    {
        // Cf. CommercialPerformanceController : on accepte UNE seule date
        // (from ou to) sans exiger les 2, sinon le preset par défaut
        // écrasait silencieusement la saisie de l'utilisateur.
        $hasFrom = $request->filled('from');
        $hasTo   = $request->filled('to');
        if ($hasFrom || $hasTo) {
            try {
                $from = $hasFrom
                    ? Carbon::parse($request->input('from'))->startOfDay()
                    : Carbon::parse($request->input('to'))->copy()->subYear()->startOfDay();
                $to = $hasTo
                    ? Carbon::parse($request->input('to'))->endOfDay()
                    : Carbon::now()->endOfDay();
                if ($from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                return [$from, $to];
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
