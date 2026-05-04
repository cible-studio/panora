<?php
namespace App\Jobs;

use App\Mail\SatisfactionSurveyMail;
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
 * Rappel J+3 envoyé si le client n'a pas encore rempli le questionnaire.
 *
 * La SatisfactionSurvey est créée à J+0 par SendCampaignEndedMail.
 * Ce job vérifie simplement si elle est encore incomplète avant d'envoyer
 * le mail de rappel SatisfactionSurveyMail.
 */
class SendSatisfactionSurvey implements ShouldQueue
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
            Log::info('satisfaction.reminder.skipped.not_found', ['campaign_id' => $this->campaignId]);
            return;
        }

        if (!$campaign->client || empty($campaign->client->email)) {
            Log::info('satisfaction.reminder.skipped.no_email', ['campaign_id' => $campaign->id]);
            return;
        }

        $survey = SatisfactionSurvey::where('campaign_id', $campaign->id)->first();

        // Pas encore de survey (cas rare : email J+0 a échoué) — on la crée
        if (!$survey) {
            $survey = SatisfactionSurvey::create([
                'campaign_id' => $campaign->id,
                'client_id'   => $campaign->client_id,
                'token'       => SatisfactionSurvey::generateUniqueToken(),
                'sent_at'     => now(),
            ]);
        }

        // Déjà complété — pas de rappel
        if ($survey->isCompleted()) {
            Log::info('satisfaction.reminder.skipped.already_done', [
                'campaign_id' => $campaign->id,
                'survey_id'   => $survey->id,
            ]);
            return;
        }

        $result = app(NotificationMailer::class)->sendNow(
            $campaign->client->email,
            new SatisfactionSurveyMail($survey),
            context: [
                'action'      => 'satisfaction.reminder.sent',
                'campaign_id' => $campaign->id,
                'client_id'   => $campaign->client_id,
            ],
        );

        if (!$result->ok) {
            Log::warning('satisfaction.reminder.mail_failed', [
                'campaign_id' => $campaign->id,
                'message'     => $result->message,
                'code'        => $result->code,
            ]);
        }
    }
}
