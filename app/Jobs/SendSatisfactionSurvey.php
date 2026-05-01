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
 * Job qui crée une SatisfactionSurvey et envoie le mail au client.
 *
 * Dispatché depuis CampaignObserver::updated() avec un delay(3 jours)
 * quand une campagne passe en statut TERMINE.
 *
 * Idempotent : si une survey existe déjà pour la campagne, ne refait rien.
 */
class SendSatisfactionSurvey implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 min entre tentatives

    public function __construct(
        public readonly int $campaignId
    ) {}

    public function handle(): void
    {
        $campaign = Campaign::with('client')->find($this->campaignId);

        if (!$campaign) {
            Log::info('satisfaction.skipped.campaign_not_found', ['campaign_id' => $this->campaignId]);
            return;
        }

        // Idempotence : déjà envoyée
        if (SatisfactionSurvey::where('campaign_id', $campaign->id)->exists()) {
            Log::info('satisfaction.skipped.already_exists', ['campaign_id' => $campaign->id]);
            return;
        }

        // Garde-fous métier : pas d'enquête sur campagnes annulées ou sans client
        if ($campaign->status->value !== 'termine') {
            Log::info('satisfaction.skipped.not_terminated', [
                'campaign_id' => $campaign->id,
                'status'      => $campaign->status->value,
            ]);
            return;
        }
        if (!$campaign->client || empty($campaign->client->email)) {
            Log::info('satisfaction.skipped.no_client_email', ['campaign_id' => $campaign->id]);
            return;
        }

        $survey = SatisfactionSurvey::create([
            'campaign_id' => $campaign->id,
            'client_id'   => $campaign->client_id,
            'token'       => SatisfactionSurvey::generateUniqueToken(),
            'sent_at'     => now(),
        ]);

        // Envoi en sync via NotificationMailer (cohérent avec le reste de l'app)
        $result = app(NotificationMailer::class)->sendNow(
            $campaign->client->email,
            new SatisfactionSurveyMail($survey),
            context: [
                'action'      => 'satisfaction.sent',
                'campaign_id' => $campaign->id,
                'client_id'   => $campaign->client_id,
            ],
        );

        if (!$result->ok) {
            Log::warning('satisfaction.mail_failed', [
                'campaign_id' => $campaign->id,
                'message'     => $result->message,
                'code'        => $result->code,
            ]);
            // On garde la survey créée — l'admin peut renvoyer manuellement
        }
    }
}
