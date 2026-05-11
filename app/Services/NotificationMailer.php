<?php
namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * NotificationMailer — wrapper centralisé pour TOUS les envois de mail de l'app.
 *
 * Avantages :
 *   - Try/catch systématique : un mail qui échoue ne casse JAMAIS le flux métier
 *   - Logs uniformes (canal `mail` + niveau adapté)
 *   - Diagnostic SMTP humain (pour l'admin)
 *   - Compatible queue (si Mailable implements ShouldQueue + worker actif)
 *   - Hook pour tracer chaque envoi (audit, monitoring)
 *
 * Usage :
 *   $mailer = app(NotificationMailer::class);
 *   $result = $mailer->send($recipient, new MyMail($data), context: ['order_id' => 123]);
 *   if (!$result->ok) { ... }
 */
class NotificationMailer
{
    /**
     * Envoie un Mailable à un destinataire (synchrone par défaut).
     *
     * IMPORTANT — comportement par défaut SYNC :
     * Sans worker queue actif, un Mailable `ShouldQueue` reste empilé dans la
     * table `jobs` et n'est JAMAIS envoyé. C'est un faux positif côté UX.
     * Pour éviter ça, send() utilise toujours sendNow() qui force l'envoi
     * synchrone, peu importe le QUEUE_CONNECTION.
     *
     * Si tu veux explicitement utiliser la queue (worker actif sur le serveur),
     * appelle plutôt dispatchAsync().
     *
     * @param  string|array  $to       email(s) destinataire(s)
     * @param  Mailable      $mailable instance Mailable
     * @param  string|null   $cc       optionnel
     * @param  array         $context  méta pour les logs (order_id, user_id, etc.)
     * @return MailResult
     */
    public function send($to, Mailable $mailable, ?string $cc = null, array $context = []): MailResult
    {
        // Par défaut on force sync pour garantir la délivrance.
        return $this->sendNow($to, $mailable, $cc, $context);
    }

    /**
     * Tente un envoi mais ne casse JAMAIS le flux. Idempotent.
     * Utiliser quand on veut "fire-and-forget" sans bloquer (ex: notif annexe).
     */
    public function sendSilently($to, Mailable $mailable, ?string $cc = null, array $context = []): bool
    {
        return $this->send($to, $mailable, $cc, $context)->ok;
    }

    /**
     * Met en queue le Mailable (asynchrone). À utiliser SEULEMENT si tu sais
     * qu'un worker queue tourne en prod (php artisan queue:work).
     * Sinon, le mail reste empilé dans la table `jobs` et n'est jamais envoyé.
     */
    public function dispatchAsync($to, Mailable $mailable, ?string $cc = null, array $context = []): MailResult
    {
        $recipients = is_array($to) ? $to : [$to];
        $recipients = array_values(array_filter($recipients, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));

        if (empty($recipients)) {
            Log::warning('mail.skipped.invalid_recipient', array_merge($context, [
                'mailable' => get_class($mailable),
                'to_raw'   => $to,
                'mode'     => 'async',
            ]));
            return MailResult::failure('Aucun destinataire valide.', 'INVALID_RECIPIENT');
        }

        try {
            $send = Mail::to($recipients);
            if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $send->cc($cc);
            }
            $send->send($mailable); // push en queue si Mailable implements ShouldQueue

            Log::info('mail.queued', array_merge($context, [
                'mailable'   => get_class($mailable),
                'recipients' => $recipients,
                'mode'       => 'async',
            ]));

            return MailResult::success();
        } catch (Throwable $e) {
            $diagnostic = $this->diagnose($e);
            Log::error('mail.failed', array_merge($context, [
                'mailable'   => get_class($mailable),
                'recipients' => $recipients,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'diagnostic' => $diagnostic,
                'mode'       => 'async',
            ]));
            return MailResult::failure($diagnostic, $this->errorCode($e));
        }
    }

    /**
     * Force un envoi SYNCHRONE même si le Mailable implements ShouldQueue.
     *
     * Critique pour les flows où l'utilisateur doit savoir IMMÉDIATEMENT si
     * l'email est parti :
     *   - Création de compte (l'admin doit communiquer le mot de passe)
     *   - Envoi de proposition (l'admin doit re-tenter si KO)
     *   - Reset password
     *
     * Sans ça, sur un environnement avec QUEUE_CONNECTION=database et SANS
     * worker actif, le mail reste empilé indéfiniment alors que le code
     * retourne "ok" → faux positif vu côté UX.
     */
    public function sendNow($to, Mailable $mailable, ?string $cc = null, array $context = []): MailResult
    {
        $recipients = is_array($to) ? $to : [$to];
        $recipients = array_values(array_filter($recipients, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));

        if (empty($recipients)) {
            Log::warning('mail.skipped.invalid_recipient', array_merge($context, [
                'mailable' => get_class($mailable),
                'to_raw'   => $to,
                'mode'     => 'sendNow',
            ]));
            return MailResult::failure('Aucun destinataire valide.', 'INVALID_RECIPIENT');
        }

        try {
            $send = Mail::to($recipients);
            if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $send->cc($cc);
            }
            // sendNow() bypasse la queue, force l'envoi synchrone immédiat.
            $send->sendNow($mailable);

            Log::info('mail.sent', array_merge($context, [
                'mailable'   => get_class($mailable),
                'recipients' => $recipients,
                'cc'         => $cc,
                'mode'       => 'sync',
            ]));

            return MailResult::success();

        } catch (Throwable $e) {
            $diagnostic = $this->diagnose($e);

            Log::error('mail.failed', array_merge($context, [
                'mailable'   => get_class($mailable),
                'recipients' => $recipients,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'diagnostic' => $diagnostic,
                'mode'       => 'sync',
            ]));

            return MailResult::failure($diagnostic, $this->errorCode($e));
        }
    }

    /**
     * Diagnostic humain de l'erreur SMTP (pour affichage à l'admin).
     */
    private function diagnose(Throwable $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            str_contains($msg, 'Connection could not be established'),
            str_contains($msg, 'Connection refused'),
            str_contains($msg, 'Could not connect') =>
                '⚠️ Connexion SMTP impossible. Vérifiez MAIL_HOST et MAIL_PORT dans .env.',

            str_contains($msg, 'Authentication failed'),
            str_contains($msg, 'Authentication required'),
            str_contains($msg, 'Username and Password not accepted') =>
                '⚠️ Identifiants SMTP refusés. Vérifiez MAIL_USERNAME et MAIL_PASSWORD '
                . '(mot de passe d\'application Gmail si 2FA active).',

            str_contains($msg, 'STARTTLS'),
            str_contains($msg, 'TLS'),
            str_contains($msg, 'SSL') =>
                '⚠️ Erreur TLS/SSL. Vérifiez MAIL_ENCRYPTION (tls ou ssl) dans .env.',

            str_contains($msg, 'Address') =>
                '⚠️ Adresse email invalide ou expéditeur (MAIL_FROM_ADDRESS) mal configuré.',

            str_contains($msg, 'too many arguments'),
            str_contains($msg, 'Too many arguments') =>
                '⚠️ Erreur interne : signature Mailable incohérente. Contactez le support.',

            str_contains($msg, 'route') =>
                '⚠️ Erreur interne : génération de lien dans le mail. Contactez le support.',

            default => '⚠️ Erreur d\'envoi : ' . mb_substr($msg, 0, 180),
        };
    }

    private function errorCode(Throwable $e): string
    {
        $msg = strtolower($e->getMessage());

        return match (true) {
            str_contains($msg, 'connection') || str_contains($msg, 'connect') => 'SMTP_CONNECTION',
            str_contains($msg, 'authent')   => 'SMTP_AUTH',
            str_contains($msg, 'tls') || str_contains($msg, 'ssl') => 'SMTP_TLS',
            str_contains($msg, 'address')   => 'SMTP_ADDRESS',
            str_contains($msg, 'route')     => 'INTERNAL_ROUTE',
            str_contains($msg, 'argument')  => 'INTERNAL_SIGNATURE',
            default                         => 'UNKNOWN',
        };
    }
}

/**
 * Résultat d'un envoi de mail (DTO simple, lisible par les controllers).
 */
final class MailResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $message = null,
        public readonly ?string $code = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $message, string $code = 'UNKNOWN'): self
    {
        return new self(false, $message, $code);
    }
}
