<?php

namespace App\Observers;

use App\Models\Pige;
use Illuminate\Support\Facades\Log;

/**
 * Hooks Pige :
 *   - Backfill du pose_task_id (lien legacy via panel+campaign)
 *   - Alerte MP/admin à chaque nouvelle photo uploadée
 *
 * IMPORTANT : on ne marque PLUS automatiquement la PoseTask en COMPLETED
 * sur création d'une pige. Le technicien doit confirmer explicitement
 * la fin de la pose via le bouton "Marquer terminée" (markDone) sur la
 * page publique — sinon une simple photo prise par erreur clôturait la
 * tâche, et il était impossible d'uploader plusieurs photos sans que la
 * pose repasse en re-traitement.
 */
class PigeObserver
{
    public function creating(Pige $pige): void
    {
        // Backfill automatique du pose_task_id si manquant — recherche
        // la PoseTask (panel + campaign) la plus récente.
        if (!$pige->pose_task_id && $pige->panel_id && $pige->campaign_id) {
            $poseTask = \App\Models\PoseTask::where('panel_id', $pige->panel_id)
                ->where('campaign_id', $pige->campaign_id)
                ->latest()
                ->first();
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
        // ── Alerte in-app (cloche 🔔) — historique ────────────────
        // La PoseTask N'EST PAS basculée en COMPLETED — c'est le technicien
        // qui décide via le bouton "Marquer terminée".
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

        // ── Mail admin + MP : nouvelle pige à valider (2026-08-10) ──
        // Demande user : au cas où admin/MP ne sont pas connectés à l'app,
        // ils reçoivent un mail pour aller vérifier la pige. Cible :
        // commercial assigné à la campagne + tous MP + tous admin. Le
        // commercial est notifié car c'est lui qui suit son client.
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
                to: ['commercial_assigned', 'mediaplanner', 'admin'],
                commercialAssigned: $pige->campaign?->user,
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
}
