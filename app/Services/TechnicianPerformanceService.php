<?php
// app/Services/TechnicianPerformanceService.php

namespace App\Services;

use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\PoseTaskAction;
use App\Models\PoseTeam;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TechnicianPerformanceService — source unique pour les KPIs M2.
 *
 * Métriques calculées :
 *   - Volume : nb_poses_total, nb_realisees, nb_planifiees
 *   - Réactivité : avg(started_at - created_at)
 *   - Durée pose : avg(done_at - started_at)
 *   - Délai pige : avg(pige.taken_at - pose.done_at)
 *   - Taux poses en retard : % poses planifiees avec scheduled_at < now()
 *   - Taux piges rejetées : % piges rejetées sur les poses du tech
 *       (Q3 brief : Option B fallback piges.user_id si pose_task_id NULL)
 *   - Signalements émis : nb actions problem_reported avec actor = tech name
 *   - Respect estimation : % réel/estimé (sur les poses réalisées avec
 *     real_minutes ET estimated_minutes)
 *
 * RBAC : resolveTechIdForCurrentUser() — un user technique ne peut voir
 * QUE ses propres données (impossible de forger /performance/techniciens/{autre}).
 */
class TechnicianPerformanceService
{
    /** KPIs principaux d'un technicien sur la période. */
    public function kpis(int $techId, CarbonInterface $from, CarbonInterface $to): array
    {
        // Périmètre : poses assignées au tech, créées sur la période
        // OU dont scheduled_at chevauche la période.
        $base = PoseTask::query()
            ->where('assigned_user_id', $techId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                  ->orWhereBetween('scheduled_at', [$from, $to]);
            });

        $nbTotal      = (clone $base)->count();
        $nbRealisees  = (clone $base)->where('status', 'realisee')->count();
        $nbPlanifiees = (clone $base)->where('status', 'planifiee')->count();
        $nbEnRetard   = (clone $base)
            ->where('status', 'planifiee')
            ->where('scheduled_at', '<', now())
            ->count();

        // ── Temps moyens (en minutes) — via SQL pour perf
        $tempsAvg = (clone $base)
            ->whereNotNull('started_at')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)) as reactivite_min,
                AVG(CASE WHEN done_at IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, started_at, done_at)
                    END) as duree_pose_min,
                AVG(CASE WHEN done_at IS NOT NULL AND estimated_minutes > 0 AND real_minutes > 0
                         THEN (real_minutes / estimated_minutes) * 100
                    END) as respect_estimation_pct
            ')
            ->first();

        // ── Délai pige (taken_at - pose.done_at) en heures, via JOIN
        $delaiPige = Pige::query()
            ->join('pose_tasks', 'pose_tasks.id', '=', 'piges.pose_task_id')
            ->where('pose_tasks.assigned_user_id', $techId)
            ->whereNotNull('piges.taken_at')
            ->whereNotNull('pose_tasks.done_at')
            ->whereBetween('piges.taken_at', [$from, $to])
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, pose_tasks.done_at, piges.taken_at)) as h')
            ->value('h');

        // ── Piges rejetées — Q3 brief : Option B + fallback user_id
        $pigesQuery = Pige::query()->where('status', 'rejete')
            ->whereBetween('created_at', [$from, $to]);
        $rejectedCount = (clone $pigesQuery)
            ->where(function ($q) use ($techId) {
                $q->whereHas('poseTask', fn ($qq) => $qq->where('assigned_user_id', $techId))
                  ->orWhere(function ($q2) use ($techId) {
                      $q2->whereNull('pose_task_id')->where('user_id', $techId);
                  });
            })->count();
        $totalPiges = (clone $pigesQuery)
            ->where(function ($q) use ($techId) {
                $q->whereHas('poseTask', fn ($qq) => $qq->where('assigned_user_id', $techId))
                  ->orWhere(function ($q2) use ($techId) {
                      $q2->whereNull('pose_task_id')->where('user_id', $techId);
                  });
            });
        // Re-base sans filtre status pour total
        $totalPigesCount = Pige::query()
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) use ($techId) {
                $q->whereHas('poseTask', fn ($qq) => $qq->where('assigned_user_id', $techId))
                  ->orWhere(function ($q2) use ($techId) {
                      $q2->whereNull('pose_task_id')->where('user_id', $techId);
                  });
            })->count();

        // ── Signalements émis (problem_reported) par le tech sur la période
        $tech = User::find($techId);
        $nbSignalements = PoseTaskAction::query()
            ->where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) use ($techId, $tech) {
                $q->whereHas('task', fn ($qq) => $qq->where('assigned_user_id', $techId));
                if ($tech) $q->orWhere('actor', $tech->name);
            })
            ->count();

        return [
            'nb_poses_total'          => $nbTotal,
            'nb_poses_realisees'      => $nbRealisees,
            'nb_poses_planifiees'     => $nbPlanifiees,
            'nb_poses_en_retard'      => $nbEnRetard,
            'taux_poses_en_retard'    => $nbTotal > 0 ? round(($nbEnRetard / $nbTotal) * 100, 1) : 0.0,
            'reactivite_avg_min'      => $tempsAvg?->reactivite_min !== null ? (int) round($tempsAvg->reactivite_min) : null,
            'duree_pose_avg_min'      => $tempsAvg?->duree_pose_min !== null ? (int) round($tempsAvg->duree_pose_min) : null,
            'delai_pige_avg_h'        => $delaiPige !== null ? (int) round($delaiPige) : null,
            'respect_estimation_pct'  => $tempsAvg?->respect_estimation_pct !== null ? round($tempsAvg->respect_estimation_pct, 1) : null,
            'nb_piges_rejetees'       => $rejectedCount,
            'taux_piges_rejetees'     => $totalPigesCount > 0 ? round(($rejectedCount / $totalPigesCount) * 100, 1) : 0.0,
            'nb_signalements'         => $nbSignalements,
        ];
    }

    /** KPIs agrégés au niveau équipe — somme/avg sur les membres. */
    public function byTeam(int $teamId, CarbonInterface $from, CarbonInterface $to): array
    {
        $team = PoseTeam::with('members:id,name,pose_team_id')->find($teamId);
        if (!$team) {
            return ['team' => null, 'members_count' => 0, 'kpis' => $this->emptyKpis()];
        }

        $members = $team->members;
        if ($members->isEmpty()) {
            return ['team' => $team, 'members_count' => 0, 'kpis' => $this->emptyKpis()];
        }

        $aggregated = $this->emptyKpis();
        $kpis = [];
        foreach ($members as $m) {
            $k = $this->kpis($m->id, $from, $to);
            $kpis[] = $k;
            // Sommes
            $aggregated['nb_poses_total']      += $k['nb_poses_total'];
            $aggregated['nb_poses_realisees']  += $k['nb_poses_realisees'];
            $aggregated['nb_poses_planifiees'] += $k['nb_poses_planifiees'];
            $aggregated['nb_poses_en_retard']  += $k['nb_poses_en_retard'];
            $aggregated['nb_piges_rejetees']   += $k['nb_piges_rejetees'];
            $aggregated['nb_signalements']     += $k['nb_signalements'];
        }
        // Moyennes (ignorer nulls)
        foreach (['reactivite_avg_min', 'duree_pose_avg_min', 'delai_pige_avg_h', 'respect_estimation_pct'] as $field) {
            $vals = array_filter(array_column($kpis, $field), fn ($v) => $v !== null);
            $aggregated[$field] = !empty($vals) ? round(array_sum($vals) / count($vals), 1) : null;
        }
        // Taux dérivés
        $aggregated['taux_poses_en_retard'] = $aggregated['nb_poses_total'] > 0
            ? round(($aggregated['nb_poses_en_retard'] / $aggregated['nb_poses_total']) * 100, 1) : 0.0;
        // Pour taux_piges_rejetees on prend la moyenne des taux (déjà normalisés)
        $tauxValues = array_column($kpis, 'taux_piges_rejetees');
        $aggregated['taux_piges_rejetees'] = !empty($tauxValues) ? round(array_sum($tauxValues) / count($tauxValues), 1) : 0.0;

        return ['team' => $team, 'members_count' => $members->count(), 'kpis' => $aggregated];
    }

    /** Leaderboard tous techs (admin/MP). */
    public function leaderboardTechs(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return User::techniciens()->with('poseTeam:id,name,color_slug')->get()
            ->map(fn (User $u) => [
                'user' => $u,
                'kpis' => $this->kpis($u->id, $from, $to),
            ])
            ->sortByDesc(fn ($r) => $r['kpis']['nb_poses_realisees'])
            ->values();
    }

    /** Leaderboard équipes (admin/MP). */
    public function leaderboardTeams(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return PoseTeam::active()->with('leader:id,name')->withCount('activeMembers')->get()
            ->map(fn (PoseTeam $t) => array_merge(
                $this->byTeam($t->id, $from, $to),
                ['members_count' => $t->active_members_count]
            ))
            ->sortByDesc(fn ($r) => $r['kpis']['nb_poses_realisees'])
            ->values();
    }

    /** Liste paginée des poses du tech. */
    public function posesList(int $techId, CarbonInterface $from, CarbonInterface $to, int $perPage = 20)
    {
        return PoseTask::query()
            ->where('assigned_user_id', $techId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                  ->orWhereBetween('scheduled_at', [$from, $to]);
            })
            ->with(['panel:id,reference,commune_id', 'panel.commune:id,name', 'campaign:id,name,client_id', 'campaign.client:id,name'])
            ->orderByDesc('scheduled_at')
            ->paginate($perPage);
    }

    /** Nb poses par jour sur les N derniers jours. */
    public function dailyPoses(int $techId, int $days = 30): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = PoseTask::query()
            ->where('assigned_user_id', $techId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $result = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $result->push([
                'date'  => $d,
                'label' => Carbon::parse($d)->format('d/m'),
                'count' => (int) ($rows[$d] ?? 0),
            ]);
        }
        return $result;
    }

    /**
     * Histogramme de réactivité — distribution des temps started_at - created_at
     * en 6 buckets : <1h, 1-4h, 4-24h, 1-3j, 3-7j, >7j.
     */
    public function reactivityDistribution(int $techId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = PoseTask::query()
            ->where('assigned_user_id', $techId)
            ->whereNotNull('started_at')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('TIMESTAMPDIFF(MINUTE, created_at, started_at) as min')
            ->pluck('min');

        $buckets = [
            ['label' => '< 1h',    'min' => 0,     'max' => 59,     'count' => 0, 'color' => '#16a34a'],
            ['label' => '1-4h',    'min' => 60,    'max' => 239,    'count' => 0, 'color' => '#0ea5e9'],
            ['label' => '4-24h',   'min' => 240,   'max' => 1439,   'count' => 0, 'color' => '#6366f1'],
            ['label' => '1-3j',    'min' => 1440,  'max' => 4319,   'count' => 0, 'color' => '#f59e0b'],
            ['label' => '3-7j',    'min' => 4320,  'max' => 10079,  'count' => 0, 'color' => '#ea580c'],
            ['label' => '> 7j',    'min' => 10080, 'max' => null,   'count' => 0, 'color' => '#dc2626'],
        ];
        foreach ($rows as $m) {
            foreach ($buckets as $i => $b) {
                if ($m >= $b['min'] && ($b['max'] === null || $m <= $b['max'])) {
                    $buckets[$i]['count']++;
                    break;
                }
            }
        }
        return collect($buckets);
    }

    /**
     * RBAC : un tech ne peut voir QUE ses propres stats.
     * admin/MP : libre. commercial : interdit (null).
     */
    public function resolveTechIdForCurrentUser(?int $requestedId, ?User $currentUser): ?int
    {
        if (!$currentUser) return null;
        $role = $currentUser->role?->value;
        if (in_array($role, ['admin', 'mediaplanner'], true)) {
            return $requestedId ?? $currentUser->id;
        }
        if ($role === 'technique') {
            return $currentUser->id;
        }
        return null;
    }

    /** KPIs vides — utilisé pour init agrégation. */
    protected function emptyKpis(): array
    {
        return [
            'nb_poses_total' => 0, 'nb_poses_realisees' => 0,
            'nb_poses_planifiees' => 0, 'nb_poses_en_retard' => 0,
            'taux_poses_en_retard' => 0.0,
            'reactivite_avg_min' => null, 'duree_pose_avg_min' => null,
            'delai_pige_avg_h' => null, 'respect_estimation_pct' => null,
            'nb_piges_rejetees' => 0, 'taux_piges_rejetees' => 0.0,
            'nb_signalements' => 0,
        ];
    }
}
