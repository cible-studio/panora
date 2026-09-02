<?php
namespace App\Observers;

use App\Enums\PoseTaskStatus;
use App\Jobs\SendCampaignEndedMail;
use App\Jobs\SendCampaignEndedCommercialMail;
use App\Jobs\SendSatisfactionSurvey;
use App\Models\Campaign;
use App\Models\PoseTask;
use App\Services\AlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CampaignObserver — déclencheurs métier sur les transitions de campagne.
 *
 * Évènements gérés :
 *   - created  : alerte 'campagne_creee'
 *   - status PLANIFIE → ACTIF : alerte 'campagne_active'
 *   - status → TERMINE : alerte 'campagne_terminee' + emails (annonce + survey)
 *   - status → ANNULE  : alerte 'reservation_annulee' (côté campagne)
 */
class CampaignObserver
{
    public function created(Campaign $campaign): void
    {
        AlertService::notify(
            'campagne_creee',
            "Campagne créée — {$campaign->name}",
            "La campagne « {$campaign->name} » a été créée"
                . ($campaign->client_id && $campaign->client?->name
                    ? " pour {$campaign->client->name}"
                    : '')
                . ".",
            $campaign,
            ['lien' => route('admin.campaigns.show', $campaign)]
        );

        // Lot 9.1 — Auto-création des tâches de pose pour chaque panneau
        // interne de la campagne. Évite à l'admin de saisir N tâches dès
        // la confirmation d'une proposition.
        $this->autoCreatePoseTasks($campaign);
    }

    /**
     * Wrapper instance pour l'observer event 'created'. Au moment où
     * l'event tire, les panneaux ne sont PAS encore syncés (c'est fait
     * juste après par le caller) → la 1re passe ne crée rien. Les
     * orchestrateurs qui créent une campagne avec panneaux doivent
     * appeler explicitement `Campaign::ensurePoseTasksAutoCreated()`
     * après le sync, ce qui retombe sur la même logique idempotente.
     */
    private function autoCreatePoseTasks(Campaign $campaign): void
    {
        self::createPoseTasksForCampaign($campaign);
    }

    /**
     * Logique partagée — auto-crée 1 PoseTask par panneau interne, en
     * mode idempotent (skip ce qui existe déjà). Appelable :
     *   - Depuis l'observer (en `created`, peut-être no-op si pas de
     *     panneaux encore syncés)
     *   - Depuis les orchestrateurs après sync (pose tasks réellement
     *     créées). Voir Campaign::ensurePoseTasksAutoCreated().
     */
    public static function createPoseTasksForCampaign(Campaign $campaign): int
    {
        if ($campaign->status?->value === 'annule') return 0;

        // load() force le re-fetch (loadMissing utilise un cache éventuellement
        // périmé après un sync(). Coût négligeable : 2 SELECT id-only).
        $campaign->load(['panels:id', 'externalPanels:id']);
        $internalIds = $campaign->panels->pluck('id')->toArray();
        $externalIds = $campaign->externalPanels->pluck('id')->toArray();

        if (!empty($externalIds)) {
            Log::info('campaign.pose_tasks.external_skipped', [
                'campaign_id' => $campaign->id,
                'count'       => count($externalIds),
            ]);
        }

        if (empty($internalIds)) return 0;

        $existing = PoseTask::where('campaign_id', $campaign->id)
            ->whereIn('panel_id', $internalIds)
            ->whereNotIn('status', [PoseTaskStatus::CANCELLED->value])
            ->pluck('panel_id')
            ->flip();

        // ── Chargement des `panel_start_date` différés ────────────────
        // Cas typique : résa avec des panneaux qui rejoignent la campagne
        // plus tard (cf. logique "démarrage différé" du contrôleur résa).
        // La pose-task doit utiliser la date EFFECTIVE du panneau, pas
        // celle de la campagne — sinon le tech est convoqué AVANT que
        // le panneau soit disponible (faux retard, pose impossible).
        $panelStartDates = [];
        if ($campaign->reservation_id) {
            $panelStartDates = \DB::table('reservation_panels')
                ->where('reservation_id', $campaign->reservation_id)
                ->where('source', 'interne')
                ->whereIn('panel_id', $internalIds)
                ->whereNotNull('panel_start_date')
                ->pluck('panel_start_date', 'panel_id')
                ->toArray();
        }

        $created = 0;
        foreach ($internalIds as $panelId) {
            if (isset($existing[$panelId])) continue;

            // Date effective : panel_start_date si défini, sinon
            // campaign.start_date.
            $scheduled = $panelStartDates[$panelId] ?? $campaign->start_date;

            $task = PoseTask::create([
                'panel_id'         => $panelId,
                'campaign_id'      => $campaign->id,
                'assigned_user_id' => null,
                'team_name'        => null,
                'scheduled_at'     => $scheduled,
                'status'           => PoseTaskStatus::PLANNED->value,
                'notes'            => isset($panelStartDates[$panelId])
                    ? 'Tâche auto-créée — démarrage différé sur ce panneau (occupé au début de campagne).'
                    : 'Tâche auto-créée à partir de la campagne',
            ]);
            $task->ensurePublicToken();
            $created++;
        }

        if ($created > 0) {
            Log::info('campaign.pose_tasks.auto_created', [
                'campaign_id' => $campaign->id,
                'count'       => $created,
            ]);
        }

        return $created;
    }

    public function updated(Campaign $campaign): void
    {
        // Pas un changement de statut → on n'agit pas
        if (!$campaign->wasChanged('status')) return;

        $newStatus = $campaign->status->value;
        $oldStatus = $campaign->getOriginal('status');
        $oldStatus = is_object($oldStatus) ? $oldStatus->value : $oldStatus;

        switch ($newStatus) {
            case 'actif':
                if ($oldStatus === 'planifie') {
                    AlertService::notify(
                        'campagne_active',
                        "Campagne lancée — {$campaign->name}",
                        "La campagne « {$campaign->name} » est désormais active.",
                        $campaign,
                        ['lien' => route('admin.campaigns.show', $campaign)]
                    );
                }
                break;

            case 'termine':
                if ($campaign->client_id) {
                    SendCampaignEndedMail::dispatch($campaign->id);
                    SendSatisfactionSurvey::dispatch($campaign->id)
                        ->delay(now()->addDays(3));
                    Log::info('campaign.ended.emails_scheduled', [
                        'campaign_id' => $campaign->id,
                        'client_id'   => $campaign->client_id,
                    ]);
                }

                // Notif commerciale J0 — indépendante du mail client :
                // même si pas de client_id, on prévient le commercial si
                // assigné. Le job skip proprement s'il n'y a pas de
                // commercial (campaign->user null ou sans email).
                SendCampaignEndedCommercialMail::dispatch($campaign->id);

                AlertService::notify(
                    'campagne_terminee',
                    "Campagne terminée — {$campaign->name}",
                    "La campagne « {$campaign->name} » est terminée. Pensez à déclencher la facturation finale.",
                    $campaign,
                    ['lien' => route('admin.campaigns.show', $campaign)]
                );
                break;

            // 'annule' est déjà géré côté CampaignService::cancel() qui crée
            // l'alerte spécifique avec le motif → pas de doublon ici.
        }
    }

    /**
     * Soft-delete de la campagne → on archive ses piges.
     *
     * Les piges ont une valeur légale (preuve photo) et comptable
     * (facturation). On les conserve EN BASE mais on les sort de la
     * vue active via `archived_at = now()`. L'admin peut les
     * retrouver dans /admin/piges/archives.
     *
     * Idempotent : si une pige est déjà archivée (cleanup manuel),
     * son archived_at est préservé (pas réécrit à chaque delete).
     */
    public function deleted(Campaign $campaign): void
    {
        $affected = \App\Models\Pige::where('campaign_id', $campaign->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        if ($affected > 0) {
            Log::info('campaign.deleted.piges_archived', [
                'campaign_id'   => $campaign->id,
                'campaign_name' => $campaign->name,
                'archived'      => $affected,
            ]);
        }
    }

    /**
     * Restoration éventuelle (Campaign::restore()) — on déarchive les
     * piges précédemment archivées par ce delete. Idempotent.
     */
    public function restored(Campaign $campaign): void
    {
        $affected = \App\Models\Pige::where('campaign_id', $campaign->id)
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null]);

        if ($affected > 0) {
            Log::info('campaign.restored.piges_unarchived', [
                'campaign_id' => $campaign->id,
                'unarchived'  => $affected,
            ]);
        }
    }
}
