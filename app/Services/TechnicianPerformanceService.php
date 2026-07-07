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
        // Périmètre : poses assignées au tech dont un des jalons tombe sur
        // la période demandée : création, planification, démarrage OU
        // réalisation. Sans ça, une pose planifiée le 21/05 mais RÉALISÉE
        // aujourd'hui n'apparaissait pas dans le KPI "aujourd'hui" — bug
        // signalé par la patronne 2026-07-01 (fait 2 poses aujourd'hui,
        // KPI = 0). Fix : élargir aux 4 jalons.
        $base = PoseTask::query()
            ->where('assigned_user_id', $techId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at',   [$from, $to])
                  ->orWhereBetween('scheduled_at', [$from, $to])
                  ->orWhereBetween('started_at',   [$from, $to])
                  ->orWhereBetween('done_at',      [$from, $to]);
            });

        $nbTotal      = (clone $base)->count();
        $nbRealisees  = (clone $base)->where('status', 'realisee')->count();
        $nbPlanifiees = (clone $base)->where('status', 'planifiee')->count();
        $nbEnRetard   = (clone $base)
            ->where('status', 'planifiee')
            ->where('scheduled_at', '<', PoseTask::lateThreshold())
            ->count();

        // ── Temps moyens (en minutes) — via SQL pour perf
        // 2026-07-01 — DUREE_POSE > 0 : filtre défensif contre les vieilles
        // poses où started_at=now() était posé en fallback au moment du
        // upload photo (bug fixé côté controllers), ce qui donnait
        // TIMESTAMPDIFF=0 min et tirait la moyenne SLA vers le bas.
        // 2026-07-06 (validé patronne) — SLA "durée de pose" recalculée
        // sur arrived_at → done_at (temps sur SITE, hors trajet).
        //   réactivité   = created_at → started_at (temps entre attribution et départ)
        //   temps trajet = started_at → arrived_at (temps de route)
        //   temps pose   = arrived_at → done_at   (VRAI SLA — temps sur site)
        // Fallback started_at si arrived_at NULL (rétrocompat vieilles poses).
        $tempsAvg = (clone $base)
            ->whereNotNull('started_at')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)) as reactivite_min,
                AVG(CASE WHEN done_at IS NOT NULL
                              AND COALESCE(arrived_at, started_at) IS NOT NULL
                              AND TIMESTAMPDIFF(MINUTE, COALESCE(arrived_at, started_at), done_at) > 0
                         THEN TIMESTAMPDIFF(MINUTE, COALESCE(arrived_at, started_at), done_at)
                    END) as duree_pose_min,
                AVG(CASE WHEN arrived_at IS NOT NULL AND started_at IS NOT NULL
                              AND TIMESTAMPDIFF(MINUTE, started_at, arrived_at) > 0
                         THEN TIMESTAMPDIFF(MINUTE, started_at, arrived_at)
                    END) as trajet_min,
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
            'trajet_avg_min'          => $tempsAvg?->trajet_min !== null ? (int) round($tempsAvg->trajet_min) : null,
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
        foreach (['reactivite_avg_min', 'duree_pose_avg_min', 'trajet_avg_min', 'delai_pige_avg_h', 'respect_estimation_pct'] as $field) {
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
        // 2026-06-19 — Multi-équipe : on charge poseTeams (plural).
        return User::techniciens()->with('poseTeams:id,name,color_slug')->get()
            ->map(fn (User $u) => [
                'user' => $u,
                'kpis' => $this->kpis($u->id, $from, $to),
            ])
            ->sortByDesc(fn ($r) => $r['kpis']['nb_poses_realisees'])
            ->values();
    }

    /**
     * COURBE GLOBALE TECHNIQUE — évolution mensuelle sur N mois.
     * Symétrique de CommercialPerformanceService::globalMonthlyTrend()
     * pour aligner les 2 dashboards perf.
     *
     * 3 séries :
     *   - poses_realisees   : nb done_at sur le mois (toutes équipes)
     *   - poses_planifiees  : nb scheduled_at sur le mois (toutes équipes)
     *   - retard_pct        : % moyen poses en retard sur le mois
     *
     * @return Collection<array{label:string, year:int, month:int,
     *                          poses_realisees:int, poses_planifiees:int,
     *                          retard_pct:float}>
     */
    public function globalMonthlyTrend(int $months = 12): Collection
    {
        $end   = now()->endOfMonth();
        $start = now()->subMonths($months - 1)->startOfMonth();

        // Poses RÉALISÉES (done_at posé dans le mois)
        $rowsReal = DB::table('pose_tasks')
            ->whereNotNull('done_at')
            ->whereBetween('done_at', [$start, $end])
            ->selectRaw('YEAR(done_at) as y, MONTH(done_at) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

        // Poses PLANIFIÉES (scheduled_at posé dans le mois)
        $rowsPlan = DB::table('pose_tasks')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->selectRaw('YEAR(scheduled_at) as y, MONTH(scheduled_at) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

        // Poses EN RETARD par mois (scheduled_at dans le mois ET (en retard à la date ref ou done_at > scheduled_at))
        $rowsRet = DB::table('pose_tasks')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$start, $end])
            ->where(function ($q) {
                $q->whereRaw('done_at > scheduled_at')
                  ->orWhere(function ($q2) {
                      // J+2 grace : cf. PoseTask::LATE_GRACE_DAYS
                      $q2->whereNull('done_at')
                         ->where('scheduled_at', '<', PoseTask::lateThreshold());
                  });
            })
            ->selectRaw('YEAR(scheduled_at) as y, MONTH(scheduled_at) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

        $moisFr = [1=>'janv', 2=>'févr', 3=>'mars', 4=>'avr', 5=>'mai', 6=>'juin', 7=>'juil', 8=>'août', 9=>'sept', 10=>'oct', 11=>'nov', 12=>'déc'];

        $result = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $d   = now()->subMonths($i);
            $key = $d->year . '-' . str_pad((string) $d->month, 2, '0', STR_PAD_LEFT);
            $plan = isset($rowsPlan[$key]) ? (int) $rowsPlan[$key]->cnt : 0;
            $ret  = isset($rowsRet[$key])  ? (int) $rowsRet[$key]->cnt  : 0;
            $result->push([
                'label'            => $moisFr[$d->month] . ' ' . $d->format('y'),
                'year'             => $d->year,
                'month'            => $d->month,
                'poses_realisees'  => isset($rowsReal[$key]) ? (int) $rowsReal[$key]->cnt : 0,
                'poses_planifiees' => $plan,
                'retard_pct'       => $plan > 0 ? round(($ret / $plan) * 100, 1) : 0.0,
            ]);
        }
        return $result;
    }

    /**
     * KPI agrégés équipe (totalité des techniciens) sur la période.
     * Permet d'afficher les cards en tête de la page leaderboard.
     *
     * @return array{
     *   nb_techniciens:int, nb_poses_realisees:int, nb_poses_planifiees:int,
     *   reactivite_avg_min:?int, duree_pose_avg_min:?int,
     *   taux_retard_avg:float, taux_piges_rejetees_avg:float
     * }
     */
    public function globalTeamKpis(CarbonInterface $from, CarbonInterface $to): array
    {
        $techs = User::techniciens()->pluck('id');
        if ($techs->isEmpty()) {
            return [
                'nb_techniciens'           => 0,
                'nb_poses_realisees'       => 0,
                'nb_poses_planifiees'      => 0,
                'reactivite_avg_min'       => null,
                'duree_pose_avg_min'       => null,
                'taux_retard_avg'          => 0.0,
                'taux_piges_rejetees_avg'  => 0.0,
            ];
        }

        $kpisByTech = $techs->map(fn ($id) => $this->kpis((int) $id, $from, $to));

        $sum = fn (string $k) => (int) $kpisByTech->sum($k);
        $avg = fn (string $k) => $kpisByTech
            ->filter(fn ($r) => $r[$k] !== null)
            ->avg($k);
        $avgPct = fn (string $k) => round((float) ($kpisByTech->avg($k) ?? 0), 1);

        return [
            'nb_techniciens'           => $techs->count(),
            'nb_poses_realisees'       => $sum('nb_poses_realisees'),
            'nb_poses_planifiees'      => $sum('nb_poses_planifiees'),
            'reactivite_avg_min'       => ($a = $avg('reactivite_avg_min')) !== null ? (int) round($a) : null,
            'duree_pose_avg_min'       => ($a = $avg('duree_pose_avg_min')) !== null ? (int) round($a) : null,
            'taux_retard_avg'          => $avgPct('taux_poses_en_retard'),
            'taux_piges_rejetees_avg'  => $avgPct('taux_piges_rejetees'),
        ];
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

    /**
     * KPIs globaux équipe (tous techniciens actifs) pour le hero leaderboard.
     * Bloc 2 — Famille A (2026-06-17) — homogénéisation avec page Commerciale.
     *
     * @return array{
     *   nb_techs_actifs:int, nb_poses_realisees:int, reactivite_avg_min:?int,
     *   duree_pose_avg_min:?int, taux_poses_en_retard:float, taux_piges_rejetees:float
     * }
     */
    public function globalKpis(CarbonInterface $from, CarbonInterface $to): array
    {
        $techIds = User::techniciens()->pluck('id');
        if ($techIds->isEmpty()) {
            return [
                'nb_techs_actifs'      => 0,
                'nb_poses_realisees'   => 0,
                'reactivite_avg_min'   => null,
                'duree_pose_avg_min'   => null,
                'taux_poses_en_retard' => 0.0,
                'taux_piges_rejetees'  => 0.0,
            ];
        }

        $aggregate = DB::table('pose_tasks')
            ->whereIn('assigned_user_id', $techIds)
            ->where(function ($q) use ($from, $to) {
                // Périmètre élargi 2026-07-01 : cohérent avec kpis() —
                // une pose réalisée AUJOURD'HUI mais planifiée hier doit
                // apparaître dans le KPI "aujourd'hui".
                $q->whereBetween('created_at',   [$from, $to])
                  ->orWhereBetween('scheduled_at', [$from, $to])
                  ->orWhereBetween('started_at',   [$from, $to])
                  ->orWhereBetween('done_at',      [$from, $to]);
            })
            ->selectRaw('
                COUNT(*) as nb_total,
                SUM(CASE WHEN status = "realisee" THEN 1 ELSE 0 END) as nb_realisees,
                SUM(CASE WHEN status = "planifiee" AND scheduled_at < DATE_SUB(CURDATE(), INTERVAL ' . PoseTask::LATE_GRACE_DAYS . ' DAY) THEN 1 ELSE 0 END) as nb_retard,
                AVG(CASE WHEN started_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, started_at) END) as reactivite,
                AVG(CASE WHEN done_at IS NOT NULL
                              AND COALESCE(arrived_at, started_at) IS NOT NULL
                              AND TIMESTAMPDIFF(MINUTE, COALESCE(arrived_at, started_at), done_at) > 0
                         THEN TIMESTAMPDIFF(MINUTE, COALESCE(arrived_at, started_at), done_at) END) as duree_pose
            ')
            ->first();

        // Taux piges rejetées global
        $pigesRows = Pige::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('poseTask', fn ($q) => $q->whereIn('assigned_user_id', $techIds))
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = "rejete" THEN 1 ELSE 0 END) as rejected')
            ->first();
        $tauxRejet = ($pigesRows && $pigesRows->total > 0)
            ? round(($pigesRows->rejected / $pigesRows->total) * 100, 1)
            : 0.0;

        return [
            'nb_techs_actifs'      => $techIds->count(),
            'nb_poses_realisees'   => (int) ($aggregate->nb_realisees ?? 0),
            'reactivite_avg_min'   => $aggregate?->reactivite !== null ? (int) round($aggregate->reactivite) : null,
            'duree_pose_avg_min'   => $aggregate?->duree_pose !== null ? (int) round($aggregate->duree_pose) : null,
            'taux_poses_en_retard' => $aggregate && $aggregate->nb_total > 0
                ? round(($aggregate->nb_retard / $aggregate->nb_total) * 100, 1) : 0.0,
            'taux_piges_rejetees'  => $tauxRejet,
        ];
    }

    /**
     * Évolution nb poses réalisées par mois — 12 mois glissants.
     * Toutes équipes / tous techniciens confondus.
     */
    public function monthlyTrendAllTechs(int $months = 12): Collection
    {
        $end   = now()->endOfMonth();
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = DB::table('pose_tasks')
            ->where('status', 'realisee')
            ->whereBetween('done_at', [$start, $end])
            ->whereNotNull('done_at')
            ->selectRaw('YEAR(done_at) as y, MONTH(done_at) as m, COUNT(*) as c')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y . '-' . str_pad((string) $r->m, 2, '0', STR_PAD_LEFT));

        $moisFr = [1=>'janv', 2=>'févr', 3=>'mars', 4=>'avr', 5=>'mai', 6=>'juin', 7=>'juil', 8=>'août', 9=>'sept', 10=>'oct', 11=>'nov', 12=>'déc'];
        $result = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $key = $d->year . '-' . str_pad((string) $d->month, 2, '0', STR_PAD_LEFT);
            $result->push([
                'label' => $moisFr[$d->month] . ' ' . $d->format('y'),
                'count' => isset($rows[$key]) ? (int) $rows[$key]->c : 0,
            ]);
        }
        return $result;
    }

    /**
     * Top techniciens par commune — qui pose le plus dans chaque commune.
     * Utile pour identifier les zones d'expertise.
     * Bloc 2 — Famille A (2026-06-17).
     *
     * @return Collection<int, array{user_id, user_name, commune, count}>
     */
    public function topByCommune(CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return DB::table('pose_tasks')
            ->join('panels', 'panels.id', '=', 'pose_tasks.panel_id')
            ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
            ->join('users', 'users.id', '=', 'pose_tasks.assigned_user_id')
            ->where('users.role', 'technique')
            ->where('users.is_active', true)
            ->where('pose_tasks.status', 'realisee')
            ->whereNotNull('pose_tasks.assigned_user_id')
            ->whereBetween('pose_tasks.done_at', [$from, $to])
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                COALESCE(communes.name, "(Sans commune)") as commune,
                COUNT(*) as count
            ')
            ->groupBy('users.id', 'users.name', 'communes.name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values();
    }

    /**
     * Top techniciens par campagne — qui a fait le plus de poses sur
     * les campagnes en cours sur la période.
     * Bloc 2 — Famille A (2026-06-17).
     *
     * @return Collection<int, array{user_id, user_name, campaign, client, count}>
     */
    public function topByCampaign(CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return DB::table('pose_tasks')
            ->join('campaigns', 'campaigns.id', '=', 'pose_tasks.campaign_id')
            ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
            ->join('users', 'users.id', '=', 'pose_tasks.assigned_user_id')
            ->where('users.role', 'technique')
            ->where('users.is_active', true)
            ->where('pose_tasks.status', 'realisee')
            ->whereNotNull('pose_tasks.assigned_user_id')
            ->whereBetween('pose_tasks.done_at', [$from, $to])
            ->selectRaw('
                users.id as user_id,
                users.name as user_name,
                campaigns.name as campaign,
                COALESCE(clients.name, "—") as client,
                COUNT(*) as count
            ')
            ->groupBy('users.id', 'users.name', 'campaigns.id', 'campaigns.name', 'clients.name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->values();
    }

    /**
     * Liste paginée des poses du tech.
     * 2026-07-07 : périmètre aligné sur kpis() (4 jalons created/scheduled/
     * started/done). Sans ça, une pose PLANIFIÉE pour le 08/07 mais
     * RÉALISÉE aujourd'hui 07/07 n'apparaissait pas dans la liste
     * "aujourd'hui", alors que les KPI la comptaient — incohérence
     * signalée par la patronne (card = 2 poses réalisées, liste vide).
     */
    public function posesList(int $techId, CarbonInterface $from, CarbonInterface $to, int $perPage = 20)
    {
        return PoseTask::query()
            ->where('assigned_user_id', $techId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at',   [$from, $to])
                  ->orWhereBetween('scheduled_at', [$from, $to])
                  ->orWhereBetween('started_at',   [$from, $to])
                  ->orWhereBetween('done_at',      [$from, $to]);
            })
            ->with(['panel:id,reference,commune_id', 'panel.commune:id,name', 'campaign:id,name,client_id', 'campaign.client:id,name'])
            ->orderByDesc(DB::raw('COALESCE(done_at, started_at, scheduled_at, created_at)'))
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
            'trajet_avg_min' => null,
            'delai_pige_avg_h' => null, 'respect_estimation_pct' => null,
            'nb_piges_rejetees' => 0, 'taux_piges_rejetees' => 0.0,
            'nb_signalements' => 0,
        ];
    }
}
