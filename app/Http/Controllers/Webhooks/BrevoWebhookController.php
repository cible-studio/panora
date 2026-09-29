<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\DiffusionDesinscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Retour d'information de Brevo (2026-09-29).
 *
 * Brevo prévient Panora quand un client se désinscrit, quand une adresse
 * n'existe plus (rebond définitif) ou quand un mail est signalé comme
 * spam. Sans ce retour, Panora réinscrirait ces adresses à la
 * synchronisation suivante.
 *
 * Sécurité : l'URL contient un jeton secret (BREVO_WEBHOOK_TOKEN). Un
 * jeton faux reçoit un 404, pour ne pas révéler que la route existe.
 * CSRF désactivé sur cette route (cf. bootstrap/app.php) : c'est un
 * serveur, pas un navigateur, qui appelle.
 */
class BrevoWebhookController extends Controller
{
    /** Événement Brevo → motif d'exclusion. Les deux API (marketing et
     *  transactionnel) n'écrivent pas les noms de la même façon. */
    private const EVENEMENTS = [
        'unsubscribed' => 'desinscription',
        'unsubscribe'  => 'desinscription',
        'hard_bounce'  => 'rebond',
        'hardBounce'   => 'rebond',
        'hardbounce'   => 'rebond',
        'spam'         => 'spam',
        'complaint'    => 'spam',
    ];

    public function handle(Request $request, string $token): JsonResponse
    {
        $attendu = (string) config('brevo.webhook_token');

        if ($attendu === '' || !hash_equals($attendu, $token)) {
            abort(404);
        }

        // Brevo envoie un événement par appel ; on accepte aussi un lot.
        $payload = $request->json()->all();
        $evenements = array_is_list($payload) ? $payload : [$payload];

        $traites = 0;
        foreach ($evenements as $ev) {
            $motif = self::EVENEMENTS[$ev['event'] ?? ''] ?? null;
            $email = mb_strtolower(trim((string) ($ev['email'] ?? '')));

            if (!$motif || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue; // ouverture, clic, rebond temporaire… : rien à faire
            }

            DiffusionDesinscription::updateOrCreate(
                ['email' => $email],
                [
                    'motif'      => $motif,
                    'source'     => 'brevo',
                    'survenu_at' => $this->date($ev),
                ]
            );
            $traites++;

            Log::info('brevo.webhook', ['email' => $email, 'motif' => $motif]);
        }

        // Toujours 200 : un autre code ferait rejouer l'appel par Brevo.
        return response()->json(['ok' => true, 'traites' => $traites]);
    }

    private function date(array $ev): \Carbon\Carbon
    {
        foreach (['date_event', 'date', 'ts_event'] as $cle) {
            if (!empty($ev[$cle])) {
                try {
                    return is_numeric($ev[$cle])
                        ? \Carbon\Carbon::createFromTimestamp((int) $ev[$cle])
                        : \Carbon\Carbon::parse($ev[$cle]);
                } catch (\Throwable) {
                    // format inattendu : on retombe sur maintenant
                }
            }
        }

        return now();
    }
}
