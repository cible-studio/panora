<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsAppService — envoi de messages WhatsApp aux techniciens / utilisateurs.
 *
 * 2 providers supportés (configurables via .env WHATSAPP_PROVIDER) :
 *   - "callmebot" (défaut) : gratuit, simple, idéal MVP. Limites : 1 sender,
 *      le destinataire doit avoir préalablement écrit "I allow callmebot to
 *      send me messages" au numéro CallMeBot et obtenu une clé.
 *   - "twilio" : payant, scalable, multi-destinataires, bien plus fiable
 *      en production.
 *
 * Format des numéros :
 *   - Acceptés : +2250707070707, 0707070707, 07 07 07 07 07
 *   - Normalisés en interne au format E.164 sans le "+"  (ex : 2250707070707)
 *
 * Tout envoi est try/catch + log. Aucune exception propagée — les
 * notifications WhatsApp sont du "best effort" (le métier ne doit pas se
 * bloquer si l'API WhatsApp est down).
 */
class WhatsAppService
{
    /** Préfixe Côte d'Ivoire — utilisé pour normaliser les numéros locaux */
    private const CI_PREFIX = '225';

    /**
     * Envoie un message WhatsApp.
     *
     * @param  string  $to      Numéro destinataire (toute forme acceptée)
     * @param  string  $message Texte du message (<= 1000 caractères)
     * @param  array   $context Méta pour les logs
     * @return bool             True si envoyé avec succès
     */
    public function send(string $to, string $message, array $context = []): bool
    {
        $normalized = $this->normalizeNumber($to);
        if ($normalized === null) {
            Log::warning('whatsapp.skipped.invalid_number', array_merge($context, [
                'raw' => $to,
            ]));
            return false;
        }

        // Tronque le message à 1000 chars pour éviter les rejets API
        $message = mb_substr(trim($message), 0, 1000);
        if ($message === '') {
            Log::warning('whatsapp.skipped.empty_message', $context);
            return false;
        }

        $provider = strtolower((string) config('services.whatsapp.provider', 'callmebot'));

        try {
            return match ($provider) {
                'twilio'    => $this->sendViaTwilio($normalized, $message, $context),
                'callmebot' => $this->sendViaCallMeBot($normalized, $message, $context),
                default     => $this->sendViaCallMeBot($normalized, $message, $context),
            };
        } catch (Throwable $e) {
            Log::error('whatsapp.failed', array_merge($context, [
                'to'       => $normalized,
                'provider' => $provider,
                'error'    => $e->getMessage(),
                'class'    => get_class($e),
            ]));
            return false;
        }
    }

    /**
     * Envoi multi-destinataires (filtre les numéros invalides, log chacun).
     * @return int  Nombre d'envois réussis
     */
    public function sendToMany(array $recipients, string $message, array $context = []): int
    {
        $ok = 0;
        foreach ($recipients as $r) {
            if ($this->send((string) $r, $message, $context)) $ok++;
        }
        return $ok;
    }

    // ══════════════════════════════════════════════════════════════════
    // Providers
    // ══════════════════════════════════════════════════════════════════

    /**
     * CallMeBot — provider gratuit basé sur un GET HTTP.
     *
     * Doc : https://www.callmebot.com/blog/free-api-whatsapp-messages/
     * URL : https://api.callmebot.com/whatsapp.php?phone=...&text=...&apikey=...
     */
    private function sendViaCallMeBot(string $to, string $message, array $context): bool
    {
        $apiKey = (string) config('services.callmebot.api_key', '');
        if ($apiKey === '') {
            Log::warning('whatsapp.callmebot.no_api_key', $context);
            return false;
        }

        $endpoint = 'https://api.callmebot.com/whatsapp.php';
        $response = Http::timeout(8)->retry(2, 200)->get($endpoint, [
            'phone'  => $to,           // déjà normalisé sans +
            'text'   => $message,
            'apikey' => $apiKey,
        ]);

        $ok = $response->successful() && !str_contains(
            strtolower((string) $response->body()),
            'error'
        );

        Log::info('whatsapp.sent', array_merge($context, [
            'to'       => $to,
            'provider' => 'callmebot',
            'status'   => $response->status(),
            'ok'       => $ok,
        ]));

        return $ok;
    }

    /**
     * Twilio — provider payant, multi-destinataires, fiable production.
     *
     * Doc : https://www.twilio.com/docs/whatsapp/api
     * Nécessite : SID, Auth Token, From WhatsApp number (sandbox ou business).
     */
    private function sendViaTwilio(string $to, string $message, array $context): bool
    {
        $sid    = (string) config('services.twilio.sid');
        $token  = (string) config('services.twilio.token');
        $from   = (string) config('services.twilio.whatsapp_from');

        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('whatsapp.twilio.missing_config', $context);
            return false;
        }

        $endpoint = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $response = Http::withBasicAuth($sid, $token)
            ->timeout(10)
            ->retry(2, 300)
            ->asForm()
            ->post($endpoint, [
                'From' => 'whatsapp:' . $from,
                'To'   => 'whatsapp:+' . $to,
                'Body' => $message,
            ]);

        $ok = $response->successful();

        Log::info('whatsapp.sent', array_merge($context, [
            'to'       => $to,
            'provider' => 'twilio',
            'status'   => $response->status(),
            'ok'       => $ok,
            'sid'      => $response->json('sid'),
        ]));

        return $ok;
    }

    // ══════════════════════════════════════════════════════════════════
    // Normalisation des numéros
    // ══════════════════════════════════════════════════════════════════

    /**
     * Normalise un numéro téléphone vers le format international sans "+".
     * Retourne null si le numéro est invalide.
     *
     * Convention Côte d'Ivoire (depuis le passage à 10 chiffres en 2021) :
     *   les numéros mobiles font 10 chiffres au format local (commencent par 0).
     *   Avec le code pays, ils font 13 chiffres : 225 + 10 chiffres.
     *
     * Exemples acceptés :
     *   "0707070707"           → "2250707070707"
     *   "07 07 07 07 07"       → "2250707070707"
     *   "+225 07 07 07 07 07"  → "2250707070707"
     *   "+2250707070707"       → "2250707070707"
     *   "00225 0707070707"     → "2250707070707"
     *   "2250707070707"        → "2250707070707"
     */
    public function normalizeNumber(string $raw): ?string
    {
        // Supprime tout sauf chiffres et "+"
        $clean = preg_replace('/[^+0-9]/', '', $raw);
        if ($clean === null || $clean === '') return null;

        // Convertit "00xxx" en "+xxx"
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }

        // Si commence par "+" → on garde tel quel (déjà international)
        // Sinon → format local CI (le 0 initial fait partie des 10 chiffres,
        //         on préfixe simplement avec +225)
        if (!str_starts_with($clean, '+')) {
            // Si commence déjà par 225 (cas "2250707070707"), on ajoute juste +
            if (str_starts_with($clean, self::CI_PREFIX)) {
                $clean = '+' . $clean;
            } else {
                $clean = '+' . self::CI_PREFIX . $clean;
            }
        }

        // Validation finale : E.164 (8 à 15 chiffres après "+")
        if (!preg_match('/^\+[1-9]\d{7,14}$/', $clean)) {
            return null;
        }

        // CallMeBot et Twilio acceptent le format SANS "+"
        return ltrim($clean, '+');
    }

    /** Formate pour affichage humain : +225 07 07 07 07 07 */
    public function format(string $normalized): string
    {
        if (!str_starts_with($normalized, self::CI_PREFIX)) {
            return '+' . $normalized;
        }
        $local = substr($normalized, strlen(self::CI_PREFIX));
        return '+' . self::CI_PREFIX . ' ' . trim(chunk_split($local, 2, ' '));
    }
}
