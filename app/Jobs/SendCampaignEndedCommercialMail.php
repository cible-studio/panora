<?php
namespace App\Jobs;

use App\Mail\CampaignEndedCommercialMail;
use App\Models\Campaign;
use App\Services\NotificationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job dispatché quand une campagne passe au statut TERMINE.
 * Envoie une notification au COMMERCIAL assigné (pas au client — le
 * mail client est géré par SendCampaignEndedMail en parallèle).
 *
 * Objectif : le commercial est averti pour préparer son mail de suivi
 * client (satisfaction, upsell). Il rédige lui-même le message client.
 *
 * Skip proprement si :
 *   - campagne introuvable / status pas termine
 *   - pas de commercial assigné (campaign->user null ou sans email)
 *
 * Kill-switch : NotificationMailer::sendNow respecte
 * config('mail.staff_alerts_enabled') pour couper sur staging.
 */
class SendCampaignEndedCommercialMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300;

    public function __construct(
        public readonly int $campaignId
    ) {}

    public function handle(): void
    {
        $campaign = Campaign::with(['client:id,name', 'user:id,name,email'])
            ->find($this->campaignId);

        if (!$campaign) {
            Log::info('campaign_ended_commercial.skipped.not_found', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        if ($campaign->status->value !== 'termine') {
            Log::info('campaign_ended_commercial.skipped.not_terminated', [
                'campaign_id' => $campaign->id,
                'status'      => $campaign->status->value,
            ]);
            return;
        }

        $commercial = $campaign->user;
        if (!$commercial || empty($commercial->email)) {
            Log::info('campaign_ended_commercial.skipped.no_commercial', [
                'campaign_id' => $campaign->id,
            ]);
            return;
        }

        $result = app(NotificationMailer::class)->sendNow(
            $commercial->email,
            new CampaignEndedCommercialMail($campaign),
            context: [
                'action'        => 'campaign_ended_commercial.sent',
                'campaign_id'   => $campaign->id,
                'commercial_id' => $commercial->id,
            ],
        );

        if (!$result->ok) {
            Log::warning('campaign_ended_commercial.mail_failed', [
                'campaign_id' => $campaign->id,
                'message'     => $result->message,
                'code'        => $result->code,
            ]);
        }
    }
}
