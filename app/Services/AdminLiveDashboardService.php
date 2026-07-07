<?php

namespace App\Services;

use App\Enums\PoseTaskStatus;
use App\Enums\UserRole;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\PoseTaskAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * SM2b Lot 1.2 — Service du dashboard admin live.
 *
 * Agrège en 1 seul payload tout ce que l'admin voit sur /admin/dashboard/live :
 *   - 5 KPIs du jour (poses totales / faites / en cours / à valider / signalements)
 *   - Liste des techs actuellement en ligne (last_seen_at < 10 min)
 *   - Flux d'événements de la dernière minute (photo envoyée, signalement,
 *     validation, refus, transition de statut)
 *
 * Cache 5s via Cache::remember pour ne pas marteler la BDD si plusieurs
 * admins ouvrent le dashboard simultanément (3-5 admins polling toutes
 * les 20s = jusqu'à 1 req/4s sans cache).
 *
 * Adaptation brief vs réalité Panora (cf. docs/snapshots/sm2b-before/README.md) :
 *   - "ProblemReport" → PoseTaskAction::ACTION_PROBLEM_REPORTED
 *   - "User.last_seen_at" : ajouté Lot 1.1
 */
class AdminLiveDashboardService
{
    public const ONLINE_WINDOW_MIN = 10;
    public const RECENT_EVENT_SEC  = 60;
    public const CACHE_TTL_SEC     = 5;

    public function buildLivePayload(): array
    {
        return Cache::remember('admin.live.payload', self::CACHE_TTL_SEC, function () {
            return [
                'as_of'        => now()->toIso8601String(),
                'kpis'         => $this->buildKpis(),
                'techs_active' => $this->buildTechsActive(),
                'live_events'  => $this->buildLiveEvents(),
            ];
        });
    }

    private function buildKpis(): array
    {
        $startOfDay = Carbon::today()->startOfDay();
        $endOfDay   = Carbon::today()->endOfDay();

        // Hotfix 2026-06-19 :
        // L'ancienne version comptait `done` par scheduled_at today. Or les
        // techs travaillent en avance/retard de leurs poses planifiées — un
        // tech qui livre 5 poses prévues le 01/07 alors qu'on est le 19/06
        // avait son compteur "FAITES" bloqué à 0 sur le pilotage admin.
        // On bascule sur done_at qui reflète l'activité RÉELLE du jour.
        //
        // POSES DU JOUR : poses planifiées aujourd'hui OU livrées aujourd'hui
        // (union, sans double-comptage).
        $totalToday = PoseTask::whereNotNull('panel_id')
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('scheduled_at', [$startOfDay, $endOfDay])
                  ->orWhereBetween('done_at',    [$startOfDay, $endOfDay]);
            })
            ->count();

        // FAITES = poses livrées aujourd'hui (done_at), peu importe la date
        // initialement prévue.
        $done = PoseTask::whereBetween('done_at', [$startOfDay, $endOfDay])
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->count();

        $inProgress = PoseTask::whereIn('status', [
            PoseTaskStatus::EN_ROUTE->value,
            PoseTaskStatus::IN_PROGRESS->value,
        ])->count();

        $pendingValidation = Pige::where('status', 'en_attente')->count();

        $openProblems = PoseTaskAction::where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->whereNull('resolved_at')
            ->count();

        return [
            'total_poses_today'  => $totalToday,
            'done'               => $done,
            'in_progress'        => $inProgress,
            'pending_validation' => $pendingValidation,
            'problems_open'      => $openProblems,
        ];
    }

    private function buildTechsActive(): array
    {
        $onlineSince = now()->subMinutes(self::ONLINE_WINDOW_MIN);

        return User::query()
            ->where('role', UserRole::TECHNIQUE->value)
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineSince)
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get()
            ->map(fn($u) => $this->describeTech($u))
            ->values()
            ->all();
    }

    private function describeTech(User $tech): array
    {
        $startOfDay = Carbon::today()->startOfDay();
        $endOfDay   = Carbon::today()->endOfDay();

        // Hotfix 2026-06-19 :
        // L'ancienne version comptait par scheduled_at today → "0/0" sur la
        // fiche tech même quand le tech avait fini ses 5 poses prévues le
        // 01/07 alors qu'on est le 19/06.
        //
        // progress.done  = total des poses LIVRÉES du tech (= ce qu'il voit
        //                  comme "X faits" dans son espace tech)
        // progress.total = total des poses qui lui sont ASSIGNÉES (actives
        //                  + livrées). Permet à l'admin de voir où en est
        //                  le tech globalement.
        // done_today     = poses livrées aujourd'hui (utile pour fiche tech
        //                  qui affichait "FAITES AUJOURD'HUI").
        $doneTotal = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->count();

        $activeTotal = PoseTask::where('assigned_user_id', $tech->id)
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->count();

        $total = $activeTotal + $doneTotal;
        $done  = $doneTotal;

        $doneToday = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->whereBetween('done_at', [$startOfDay, $endOfDay])
            ->count();

        // Tâche actuellement "active" = en_route ou en_cours (la plus récente
        // si plusieurs — rare en pratique).
        $current = PoseTask::with(['panel:id,name,commune_id', 'panel.commune:id,name'])
            ->where('assigned_user_id', $tech->id)
            ->whereIn('status', [
                PoseTaskStatus::EN_ROUTE->value,
                PoseTaskStatus::IN_PROGRESS->value,
            ])
            ->orderByDesc('updated_at')
            ->first();

        $status = $current ? match ($current->status) {
            PoseTaskStatus::EN_ROUTE->value    => 'en_route',
            PoseTaskStatus::IN_PROGRESS->value => 'sur_place',
            default                             => 'autre',
        } : 'inactif';

        return [
            'id'                     => $tech->id,
            'initials'               => mb_substr($tech->name, 0, 2),
            'full_name'              => $tech->name,
            'is_online'              => true,
            'last_seen_at'           => optional($tech->last_seen_at)->toIso8601String(),
            'current_status'         => $status,
            'current_location_label' => $current?->panel?->commune?->name,
            'current_pose_label'     => $current?->panel?->name,
            // Chrono en direct côté admin (refonte 2026-07-07 : 3 jalons)
            'current_pose_progress'  => $current?->progress_percent !== null ? (int) $current->progress_percent : null,
            'current_pose_started_at' => optional($current?->started_at)->toIso8601String(),
            'current_pose_arrived_at' => optional($current?->arrived_at)->toIso8601String(),
            'progress' => [
                'done'       => $done,
                'total'      => $total,
                'done_today' => $doneToday,
                'remaining'  => $activeTotal,
            ],
        ];
    }

    /**
     * Événements de la dernière minute (photo envoyée, validation/refus
     * pige, signalement, transitions PoseTask). Triés DESC par horodatage.
     */
    private function buildLiveEvents(): array
    {
        $since = now()->subSeconds(self::RECENT_EVENT_SEC);
        $events = collect();

        // Piges récemment créées (envoi photo) ou changement de status
        // (validation / refus). On lit updated_at pour couvrir les deux.
        Pige::with('user:id,name')
            ->where('updated_at', '>=', $since)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->each(function (Pige $p) use ($events, $since) {
                if ($p->created_at && $p->created_at >= $since) {
                    $events->push([
                        'type'             => 'photo_sent',
                        'label'            => 'Photo envoyée',
                        'tech_initials'    => mb_substr($p->user?->name ?? '?', 0, 2),
                        'tech_full_name'   => $p->user?->name,
                        'at'               => $p->created_at->toIso8601String(),
                        'pige_id'          => $p->id,
                        // SM2b Phase 5 — Le tap sur "Valider →" ouvre la
                        // modale via data-action="open-validate-pige".
                        'actionable_url'   => '#validate-' . $p->id,
                        'actionable_data'  => ['action' => 'open-validate-pige', 'pige-id' => $p->id],
                    ]);
                }
                if ($p->status === 'verifie' && $p->verified_at && $p->verified_at >= $since) {
                    $events->push([
                        'type'           => 'photo_validated',
                        'label'          => 'Photo validée',
                        'tech_initials'  => mb_substr($p->user?->name ?? '?', 0, 2),
                        'tech_full_name' => $p->user?->name,
                        'at'             => $p->verified_at->toIso8601String(),
                        'pige_id'        => $p->id,
                    ]);
                } elseif ($p->status === 'rejete' && $p->updated_at && $p->updated_at >= $since) {
                    $events->push([
                        'type'           => 'photo_rejected',
                        'label'          => 'Photo refusée',
                        'tech_initials'  => mb_substr($p->user?->name ?? '?', 0, 2),
                        'tech_full_name' => $p->user?->name,
                        'at'             => $p->updated_at->toIso8601String(),
                        'pige_id'        => $p->id,
                    ]);
                }
            });

        // Signalements terrain (PoseTaskAction)
        PoseTaskAction::with(['task.technicien:id,name'])
            ->where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->each(function (PoseTaskAction $a) use ($events) {
                $tech = $a->task?->technicien;
                $events->push([
                    'type'           => 'problem_reported',
                    'label'          => 'Souci signalé',
                    'tech_initials'  => mb_substr($tech?->name ?? '?', 0, 2),
                    'tech_full_name' => $tech?->name,
                    'at'             => $a->created_at?->toIso8601String(),
                    'task_id'        => $a->pose_task_id,
                ]);
            });

        return $events->sortByDesc('at')->take(30)->values()->all();
    }
}
