<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CampaignStartedMail;
use App\Mail\PropositionMail;
use App\Mail\QuoteSentMail;
use App\Models\Campaign;
use App\Models\Quote;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Mailable;

/**
 * Aperçu avant envoi au client (2026-10-29, demande de la direction) : les
 * adresses exactes et le mail tel qu'il partira, calculés avec les mêmes
 * règles et le même mail que l'envoi réel. Rien n'est envoyé ici.
 */
class ApercuEnvoiController extends Controller
{
    public function show(string $type, int $id): JsonResponse
    {
        [$mail, $destinataires, $copie] = match ($type) {
            // « Notifier le client » et « Démarrer la campagne » : CampaignService::sendStartedMailToClient.
            'campagne' => $this->campagne(Campaign::with(['client.contacts', 'interlocuteur'])->findOrFail($id)),
            // « Envoyer au client » d'un devis : QuoteController::send.
            'devis' => $this->devis(Quote::with(['client', 'interlocuteur'])->findOrFail($id)),
            // « Envoyer la proposition » : PropositionController::send.
            'proposition' => $this->proposition(Reservation::with(['client', 'interlocuteur'])->findOrFail($id)),
            default => abort(404),
        };

        $html = $mail->render();

        return response()->json([
            'destinataires' => array_values($destinataires),
            'copie'         => array_values($copie),
            'sujet'         => $mail->subject ?: (method_exists($mail, 'envelope') ? $mail->envelope()->subject : null),
            'html'          => $html,
        ]);
    }

    /** @return array{0: Mailable, 1: list<string>, 2: list<string>} */
    private function campagne(Campaign $campaign): array
    {
        $this->authorize('update', $campaign);
        $client = $campaign->client;

        return [
            new CampaignStartedMail($campaign),
            $client ? array_column($client->destinatairesSuivi($campaign->interlocuteur), 'email') : [],
            [],
        ];
    }

    /** @return array{0: Mailable, 1: list<string>, 2: list<string>} */
    private function devis(Quote $quote): array
    {
        $this->authorize('send', $quote);

        return [new QuoteSentMail($quote), $quote->adressesClient(), []];
    }

    /** @return array{0: Mailable, 1: list<string>, 2: list<string>} */
    private function proposition(Reservation $reservation): array
    {
        $this->authorize('proposition.send', $reservation);

        // Le lien définitif est créé à l'envoi : l'aperçu montre un lien d'exemple.
        $apercu = clone $reservation;
        if (!$apercu->proposition_slug && !$apercu->proposition_token) {
            $apercu->proposition_slug = 'apercu';
        }

        return [new PropositionMail($apercu), $reservation->adressesClient(), []];
    }
}
