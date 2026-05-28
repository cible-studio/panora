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
        // Alerte MP/admin : nouvelle pige uploadée (pour visibilité). La
        // PoseTask N'EST PAS basculée en COMPLETED — c'est le technicien
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
    }
}
