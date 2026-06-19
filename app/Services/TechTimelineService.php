<?php

namespace App\Services;

use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\PoseTaskAction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * SM2b Lot 1.4 — Frise chronologique des événements d'un tech.
 *
 * Consommé par AdminLiveDashboardController::techTimeline et par la
 * fiche tech A2 (spec §4 — "frise chronologique verticale").
 *
 * Sources d'événements (cf. brief Lot 1.4) :
 *   - PoseTask.started_at  → tech_arrived (début travail sur place)
 *   - PoseTask.done_at     → pose_completed
 *   - Pige.created_at      → photo_sent
 *   - Pige.verified_at     → photo_validated OR photo_rejected (selon status)
 *   - PoseTaskAction       → problem_reported
 *
 * Hors-scope (faute de timestamps dédiés en BDD aujourd'hui) :
 *   - Transition tech_en_route (PoseTask passe planifiee → en_route)
 *     → demanderait une table pose_task_status_history qu'on ne veut
 *        pas créer en SM2b. À voir SM3 si besoin métier.
 *   - day_started (login user) → pas journalisé actuellement.
 */
class TechTimelineService
{
    /**
     * Frise chronologique pour un tech à une date donnée. Tri ASC par
     * 'at' (premier événement en haut). Marquage 'is_current' sur le
     * dernier événement de la journée.
     */
    public function buildTimeline(User $tech, ?CarbonImmutable $date = null): Collection
    {
        $date     = $date ?? CarbonImmutable::today();
        $startDay = $date->startOfDay();
        $endDay   = $date->endOfDay();

        $events = collect();

        // ── Tâches du tech : started_at + done_at ───────────────────
        PoseTask::with(['panel:id,name,commune_id', 'panel.commune:id,name'])
            ->where('assigned_user_id', $tech->id)
            ->where(function ($q) use ($startDay, $endDay) {
                $q->whereBetween('started_at', [$startDay, $endDay])
                  ->orWhereBetween('done_at',  [$startDay, $endDay])
                  ->orWhereBetween('scheduled_at', [$startDay, $endDay]);
            })
            ->get()
            ->each(function (PoseTask $t) use ($events, $startDay, $endDay) {
                $subject  = $t->panel?->name ?? '—';
                $location = $t->panel?->commune?->name;
                if ($t->started_at && $t->started_at->between($startDay, $endDay)) {
                    $events->push([
                        'at'       => $t->started_at,
                        'type'     => 'tech_arrived',
                        'label'    => 'Arrivé sur place',
                        'subject'  => $subject,
                        'location' => $location,
                        'meta'     => ['task_id' => $t->id],
                    ]);
                }
                if ($t->done_at && $t->done_at->between($startDay, $endDay)) {
                    $events->push([
                        'at'       => $t->done_at,
                        'type'     => 'pose_completed',
                        'label'    => 'Pose terminée',
                        'subject'  => $subject,
                        'location' => $location,
                        'meta'     => ['task_id' => $t->id],
                    ]);
                }
            });

        // ── Piges du tech : photo_sent + (validation OR refus) ──────
        Pige::with(['panel:id,name,commune_id', 'panel.commune:id,name'])
            ->where('user_id', $tech->id)
            ->where(function ($q) use ($startDay, $endDay) {
                $q->whereBetween('created_at',  [$startDay, $endDay])
                  ->orWhereBetween('verified_at', [$startDay, $endDay]);
            })
            ->get()
            ->each(function (Pige $p) use ($events, $startDay, $endDay) {
                $subject  = $p->panel?->name ?? '—';
                $location = $p->panel?->commune?->name;

                if ($p->created_at && $p->created_at->between($startDay, $endDay)) {
                    // SM2c B1 — Surface le flag is_off_schedule pour que
                    // la timeline A2 affiche un badge "hors créneau" sur
                    // les photos envoyées en dehors du créneau prévu.
                    $events->push([
                        'at'       => $p->created_at,
                        'type'     => $p->is_off_schedule ? 'photo_sent_off_schedule' : 'photo_sent',
                        'label'    => $p->is_off_schedule ? 'Photo envoyée (hors créneau)' : 'Photo envoyée',
                        'subject'  => $subject,
                        'location' => $location,
                        'meta'     => array_filter([
                            'pige_id'         => $p->id,
                            'is_off_schedule' => $p->is_off_schedule ? true : null,
                        ]),
                    ]);
                }
                if ($p->verified_at && $p->verified_at->between($startDay, $endDay)) {
                    $isReject = $p->status === 'rejete';
                    $events->push([
                        'at'       => $p->verified_at,
                        'type'     => $isReject ? 'photo_rejected' : 'photo_validated',
                        'label'    => $isReject ? 'Photo refusée' : 'Photo validée',
                        'subject'  => $subject,
                        'location' => $location,
                        'meta'     => array_filter([
                            'pige_id' => $p->id,
                            'reason'  => $isReject ? $p->rejection_reason : null,
                        ]),
                    ]);
                }
            });

        // ── Signalements terrain ─────────────────────────────────────
        PoseTaskAction::with(['task.panel:id,name,commune_id', 'task.panel.commune:id,name'])
            ->whereHas('task', fn($q) => $q->where('assigned_user_id', $tech->id))
            ->where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->whereBetween('created_at', [$startDay, $endDay])
            ->get()
            ->each(function (PoseTaskAction $a) use ($events) {
                $events->push([
                    'at'       => $a->created_at,
                    'type'     => 'problem_reported',
                    'label'    => 'Souci signalé',
                    'subject'  => $a->task?->panel?->name ?? '—',
                    'location' => $a->task?->panel?->commune?->name,
                    'meta'     => array_filter([
                        'task_id'  => $a->pose_task_id,
                        'resolved' => $a->resolved_at !== null,
                    ]),
                ]);
            });

        // ── Tri + marquage 'is_current' ──────────────────────────────
        $sorted = $events->sortBy(fn($e) => $e['at']->timestamp)->values();
        $lastIndex = $sorted->count() - 1;
        return $sorted->map(function ($e, $i) use ($lastIndex) {
            $e['at']         = $e['at']->toIso8601String();
            $e['is_current'] = ($i === $lastIndex);
            return $e;
        });
    }
}
