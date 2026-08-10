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

    /**
     * GET /admin/performance/equipes/export/pdf
     * Export PDF du leaderboard équipes (2026-06-18, feedback patronne).
     */
    public function exportPdf(Request $request)
    {
        $role = $request->user()->role?->value;
        if (!in_array($role, ['admin', 'mediaplanner'], true)) {
            abort(403);
        }

        [$from, $to] = $this->resolvePeriod($request);
        $leaderboard = $this->perf->leaderboardTeams($from, $to);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.performance.equipes.pdf', [
            'leaderboard' => $leaderboard,
            'from'        => $from,
            'to'          => $to,
            'user'        => $request->user(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $filename = 'performance-equipes-' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    public function show(Request $request, PoseTeam $team)
    {
        $role = $request->user()->role?->value;
        if (!in_array($role, ['admin', 'mediaplanner'], true)) {
            abort(403);
        }

        [$from, $to] = $this->resolvePeriod($request);

        // 2026-08-10 refonte : plus de `members:id,name,pose_team_id` — la
        // colonne pose_team_id sur users est legacy (multi-équipe passe par
        // le pivot pose_team_user). On charge les infos utiles à l'annuaire.
        $team->load(['leader:id,name,agent_code', 'members:id,name,agent_code']);
        $aggregated = $this->perf->byTeam($team->id, $from, $to);

        // Le "classement des membres" par nb_poses_realisees est SUPPRIMÉ :
        // il sommait les poses solo du membre pour le compte de l'équipe
        // (bug identifié 2026-08-10). La vue affiche désormais :
        //   - contributions (aggregated['contributions']) : piges de chaque
        //     membre sur les poses d'équipe (info seule, pas classement)
        //   - annuaire des membres (team->members) : liens vers leur rapport
        //     perso pour voir leur mérite individuel (poses solo).

        return view('admin.performance.equipes.show', compact(
            'team', 'aggregated', 'from', 'to'
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
