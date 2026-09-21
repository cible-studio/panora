<?php

namespace App\Observers;

use App\Models\Pige;
use Illuminate\Support\Facades\Log;

/**
 * Hooks Pige :
 *   - Backfill du pose_task_id (lien legacy via panel+campaign)
 *   - Clôture automatique de la PoseTask dès qu'une pige existe
 *   - Alerte in-app à chaque nouvelle photo uploadée (tous les rôles voient la cloche 🔔)
 *   - Mail dédié aux MP uniquement pour aller valider (2026-08-11 : plus admin/commercial)
 *
 * ── HISTORIQUE DE LA RÈGLE DE CLÔTURE ────────────────────────────
 *
 * 2026-08 : on avait RETIRÉ la clôture auto de la PoseTask sur
 *   création d'une pige, car une photo prise par erreur clôturait la
 *   tâche et empêchait les uploads multiples.
 *
 * 2026-09-21 (feedback user, urgent) : effet de bord inacceptable —
 *   quand le media planner ajoute une pige manuellement depuis
 *   /admin/piges/create, la PoseTask restait PLANNED et continuait
 *   d'apparaître comme « à faire » dans l'espace technicien alors que
 *   la preuve de pose existait. Les techs se perdaient dans des tâches
 *   déjà réalisées.
 *
 *   Nouvelle règle : TOUTE pige clôture la PoseTask, peu importe qui
 *   l'a uploadée (tech via lien public OU MP/admin via back-office).
 *   On trace l'auteur dans completed_by_user_id + completed_source
 *   pour afficher « Fait par X » côté tech.
 *
 *   Le risque « photo par erreur » d'origine est neutralisé par le
 *   garde-fou inverse : si la pige est REJETÉE et qu'il ne reste plus
 *   aucune pige non-rejetée sur la tâche, celle-ci est automatiquement
 *   ROUVERTE (cf. PigeObserver::updated).
 */
class PigeObserver
{
    public function creating(Pige $pige): void
    {
        // Backfill automatique du pose_task_id si manquant.
        //
        // Priorité (2026-09-21) : la tâche ENCORE OUVERTE la plus
        // récente. Sans ça, sur un panneau avec rechange, la pige
        // pouvait se rattacher à l'ancienne pose déjà réalisée et le
        // rechange restait éternellement « à faire » côté technicien.
        //
        // On ordonne par id DESC et non latest() (= created_at) : les
        // créations en lot (createRechangeBulk) partagent le même
        // created_at à la seconde près, ce qui rendait le résultat
        // non-déterministe.
        if (!$pige->pose_task_id && $pige->panel_id && $pige->campaign_id) {
            $base = fn () => \App\Models\PoseTask::where('panel_id', $pige->panel_id)
                ->where('campaign_id', $pige->campaign_id)
                ->orderByDesc('id');

            // 1er choix : une tâche encore ouverte (c'est elle que la
            // pige vient documenter).
            $poseTask = $base()
                ->whereNotIn('status', [
                    \App\Enums\PoseTaskStatus::COMPLETED->value,
                    \App\Enums\PoseTaskStatus::CANCELLED->value,
                ])
                ->first();

            // Repli : aucune tâche ouverte → on rattache à la plus
            // récente quelle qu'elle soit (photo complémentaire sur une
            // pose déjà clôturée, par exemple).
            $poseTask ??= $base()->first();

            if ($poseTask) {
                $pige->pose_task_id = $poseTask->id;
            }
        }

        // Anti-fraude : verdict de cohérence GPS pige ↔ panneau. Point unique
        // traversé par les 4 chemins d'upload (creating). Best-effort : un
        // échec ne doit jamais empêcher l'enregistrement de la pige.
        try {
            $panel    = $pige->panel; // lazy-load via panel_id
            $panelLat = ($panel && $panel->latitude  !== null) ? (float) $panel->latitude  : null;
            $panelLng = ($panel && $panel->longitude !== null) ? (float) $panel->longitude : null;

            $verdict = app(\App\Services\GeoService::class)->pigePanelCheck(
                $pige->gps_lat !== null ? (float) $pige->gps_lat : null,
                $pige->gps_lng !== null ? (float) $pige->gps_lng : null,
                $panelLat,
                $panelLng,
            );

            $pige->geo_distance_m = $verdict['distance'];
            $pige->geo_check      = $verdict['check'];
        } catch (\Throwable $e) {
            Log::warning('pige.geo_check_failed', [
                'panel_id' => $pige->panel_id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function created(Pige $pige): void
    {
        // ── Clôture automatique de la PoseTask (2026-09-21) ───────
        // Une pige existe = la pose est faite. On clôture la tâche pour
        // qu'elle sorte des listes « à faire » de l'espace technicien
        // (TechSpaceController filtre sur whereNotIn status COMPLETED).
        $this->closePoseTask($pige);

        // ── Alerte in-app (cloche 🔔) — historique ────────────────
        try {
            \App\Services\AlertService::notify(
                'avancement_pose',
                '📸 Nouvelle photo uploadée — ' . ($pige->panel?->reference ?? '#' . $pige->panel_id),
                'Le technicien a uploadé une photo pour le panneau '
                    . ($pige->panel?->reference ?? '#' . $pige->panel_id)
                    . ($pige->campaign ? ' (campagne « ' . $pige->campaign->name . ' »)' : '')
                    . '.',
                $pige,
                ['lien' => route('admin.piges.show', $pige)]
            );
        } catch (\Throwable $e) {
            Log::warning('pige.alert_failed', ['error' => $e->getMessage()]);
        }

        // ── Mail MP UNIQUEMENT : nouvelle pige à valider ──
        // Historique :
        //   2026-08-10 : initialement envoyé à commercial + MP + admin.
        //   2026-08-11 (feedback user) : trop de bruit dans les boîtes
        //     admin (studio@cible-ci.com notamment). Restreint aux SEULS
        //     media planners → c'est leur rôle métier de valider les piges,
        //     les admins/commerciaux voient l'info dans l'alerte in-app
        //     (cloche 🔔) sans polluer leur mail.
        //
        // Anti-spam : dedup par pose_task_id sur 30 min (défaut
        // AdminAlertNotifier). Si le tech upload 5 photos sur la même
        // pose en rafale, un seul mail part. Un nouvel upload > 30 min
        // plus tard (ex : re-photo après rejet) → nouveau mail.
        try {
            $pige->loadMissing('panel', 'campaign.client', 'campaign.user', 'poseTask.technicien', 'poseTask.poseTeam');
            $task     = $pige->poseTask;
            $panelRef = $pige->panel?->reference ?? '#' . $pige->panel_id;
            $techName = $task?->technicien?->name
                     ?? $task?->tech_name_self
                     ?? '(non identifié)';
            $teamMention = $task?->poseTeam
                ? ' (équipe ' . $task->poseTeam->name . ')'
                : '';

            \App\Services\AdminAlertNotifier::notify(
                to: ['mediaplanner'],
                severity: 'info',
                title: 'Nouvelle pige à valider — ' . $panelRef,
                summary: 'Un technicien vient d\'uploader une photo. Elle attend ta validation dans Panora.',
                lines: array_filter([
                    'Panneau : ' . $panelRef . ($pige->panel?->name ? ' — ' . $pige->panel->name : ''),
                    'Campagne : ' . ($pige->campaign?->name ?? '—'),
                    'Client : '   . ($pige->campaign?->client?->name ?? '—'),
                    'Technicien : ' . $techName . $teamMention,
                    'Uploadée le : ' . $pige->taken_at?->format('d/m/Y à H:i') ?? now()->format('d/m/Y à H:i'),
                ]),
                ctaLabel: 'Vérifier la pige →',
                ctaUrl: route('admin.piges.show', $pige),
                emoji: '📸',
                footer: 'Pige #' . $pige->id . ' · statut : en attente',
                dedupKey: 'pige-uploaded-task-' . ($pige->pose_task_id ?? $pige->id),
            );
        } catch (\Throwable $e) {
            // Best-effort : ne jamais casser la création d'une pige à
            // cause d'un souci mail (SMTP down, config manquante, etc.).
            Log::warning('pige.mail_admin_failed', [
                'pige_id' => $pige->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Garde-fou inverse : si une pige passe en « rejete » et qu'il ne
     * reste plus AUCUNE pige non-rejetée sur la tâche, on ROUVRE la
     * PoseTask (retour en IN_PROGRESS) pour qu'elle réapparaisse dans
     * les tâches à faire du technicien.
     *
     * Sans ce garde-fou, la clôture auto de `created()` laisserait des
     * poses fantômes marquées « faites » alors que la seule preuve a
     * été rejetée par le MP.
     */
    public function updated(Pige $pige): void
    {
        if (!$pige->wasChanged('status')) {
            return;
        }
        if ($pige->status !== 'rejete' || !$pige->pose_task_id) {
            return;
        }

        try {
            $task = \App\Models\PoseTask::find($pige->pose_task_id);
            if (!$task || $task->status !== \App\Enums\PoseTaskStatus::COMPLETED->value) {
                return;
            }

            // Reste-t-il une pige valable (non rejetée) sur cette tâche ?
            $stillHasValidPige = Pige::where('pose_task_id', $task->id)
                ->where('status', '!=', 'rejete')
                ->exists();

            if ($stillHasValidPige) {
                return; // une autre photo tient toujours la preuve
            }

            $task->forceFill([
                'status'               => \App\Enums\PoseTaskStatus::IN_PROGRESS->value,
                'done_at'              => null,
                'completed_by_user_id' => null,
                'completed_source'     => null,
            ])->save();

            Log::info('posetask.reopened_after_pige_rejected', [
                'pose_task_id' => $task->id,
                'pige_id'      => $pige->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('posetask.reopen_failed', [
                'pige_id' => $pige->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clôture la PoseTask liée à la pige, avec traçabilité de l'auteur.
     *
     * Idempotent : ne fait rien si la tâche est déjà COMPLETED ou
     * CANCELLED (uploads multiples de photos sur la même pose → un seul
     * passage effectif, pas de done_at qui se déplace à chaque photo).
     *
     * Best-effort : un échec ici ne doit jamais empêcher l'enregistrement
     * de la pige (la photo est la donnée critique, pas le statut).
     */
    private function closePoseTask(Pige $pige): void
    {
        if (!$pige->pose_task_id) {
            return;
        }

        try {
            $task = \App\Models\PoseTask::find($pige->pose_task_id);
            if (!$task) {
                return;
            }

            // Déjà terminée ou annulée → on ne touche à rien.
            if (in_array($task->status, [
                \App\Enums\PoseTaskStatus::COMPLETED->value,
                \App\Enums\PoseTaskStatus::CANCELLED->value,
            ], true)) {
                return;
            }

            $user = auth()->user();

            // Origine : 'tech' si l'upload vient du technicien assigné ou
            // d'un lien public anonyme (pas d'auth), 'admin' si c'est un
            // MP/admin connecté au back-office qui saisit la pige.
            $isAssignedTech = $user && (int) $user->id === (int) $task->assigned_user_id;
            $source = (!$user || $isAssignedTech) ? 'tech' : 'admin';

            $task->forceFill([
                'status'               => \App\Enums\PoseTaskStatus::COMPLETED->value,
                'done_at'              => $pige->taken_at ?? now(),
                'progress_percent'     => 100,
                'completed_by_user_id' => $user?->id,
                'completed_source'     => $source,
            ]);

            // real_minutes si on connaît l'heure de démarrage terrain.
            if ($task->started_at && !$task->real_minutes) {
                $task->real_minutes = max(1, (int) round(
                    $task->started_at->diffInMinutes($task->done_at)
                ));
            }

            $task->save();

            Log::info('posetask.auto_completed_by_pige', [
                'pose_task_id' => $task->id,
                'pige_id'      => $pige->id,
                'source'       => $source,
                'user_id'      => $user?->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('posetask.auto_complete_failed', [
                'pige_id'      => $pige->id,
                'pose_task_id' => $pige->pose_task_id,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
