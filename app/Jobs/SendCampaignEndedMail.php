<?php
namespace App\Jobs;

use App\Mail\CampaignEndedMail;
use App\Models\Campaign;
use App\Models\SatisfactionSurvey;
use App\Services\NotificationMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job envoyé immédiatement (J+0) quand une campagne passe en statut TERMINE.
 *
 * Crée l'enregistrement SatisfactionSurvey et envoie l'email d'annonce de fin
 * de campagne au client avec le lien vers le questionnaire de satisfaction.
 *
 * Le job SendSatisfactionSurvey (J+3) envoie ensuite un rappel si le
 * questionnaire n'a pas encore été rempli.
 */
class SendCampaignEndedMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries  = 3;
    public int $backoff = 300;

    public function __construct(
        public readonly int $campaignId
    ) {}

    public function handle(): void
    {
        $campaign = Campaign::with('client')->find($this->campaignId);

        if (!$campaign) {
            Log::info('campaign_ended.skipped.not_found', ['campaign_id' => $this->campaignId]);
            return;
        }

        if ($campaign->status->value !== 'termine') {
            Log::info('campaign_ended.skipped.not_terminated', [
                'campaign_id' => $campaign->id,
                'status'      => $campaign->status->value,
            ]);
            return;
        }

        if (!$campaign->client || empty($campaign->client->email)) {
            Log::info('campaign_ended.skipped.no_client_email', ['campaign_id' => $campaign->id]);
            return;
        }

        // Crée la survey maintenant (J+0) pour que le lien soit dans l'email
        $survey = SatisfactionSurvey::firstOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'client_id' => $campaign->client_id,
                'token'     => SatisfactionSurvey::generateUniqueToken(),
                'sent_at'   => now(),
            ]
        );

        $result = app(NotificationMailer::class)->sendNow(
            $campaign->client->email,
            new CampaignEndedMail($campaign, $survey),
            context: [
                'action'      => 'campaign_ended.sent',
                'campaign_id' => $campaign->id,
                'client_id'   => $campaign->client_id,
            ],
        );

        if (!$result->ok) {
            Log::warning('campaign_ended.mail_failed', [
                'campaign_id' => $campaign->id,
                'message'     => $result->message,
                'code'        => $result->code,
            ]);
        }
    }
}
