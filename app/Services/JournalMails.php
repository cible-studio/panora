<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalMail;
use App\Models\Pige;
use App\Models\Quote;
use App\Models\Reservation;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Journal des mails (2026-10-29) — un seul point d'écriture :
 *   · tout mail parti par le serveur de mail de Panora (événement
 *     MessageSent de Laravel : NotificationMailer, Mail::to, notifications) ;
 *   · les échecs signalés par NotificationMailer ;
 *   · les mails envoyés par l'API Brevo (J-7, renouvellement).
 * Ne lève jamais : un journal en panne ne doit pas bloquer un envoi.
 */
class JournalMails
{
    /** Écouteur de MessageSent (enregistré dans AppServiceProvider). */
    public static function surEnvoi(MessageSent $event): void
    {
        try {
            $message = $event->sent->getOriginalMessage();
            $adresses = fn ($liste) => implode(', ', array_map(fn (Address $a) => $a->getAddress(), $liste ?? []));
            [$dossierType, $dossierId] = self::dossier($event->data);

            self::noter([
                'statut'        => JournalMail::STATUT_ENVOYE,
                'canal'         => 'serveur',
                'type'          => $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? 'Mail',
                'sujet'         => method_exists($message, 'getSubject') ? $message->getSubject() : null,
                'destinataires' => method_exists($message, 'getTo') ? $adresses($message->getTo()) : '',
                'copie'         => method_exists($message, 'getCc') ? ($adresses($message->getCc()) ?: null) : null,
                'dossier_type'  => $dossierType,
                'dossier_id'    => $dossierId,
            ]);
        } catch (Throwable $e) {
            Log::warning('journal_mails.echec_ecriture', ['erreur' => $e->getMessage()]);
        }
    }

    /** Échec d'envoi (NotificationMailer). */
    public static function echec(array $destinataires, object $mail, string $erreur): void
    {
        try {
            [$dossierType, $dossierId] = self::dossier(get_object_vars($mail));
            self::noter([
                'statut'        => JournalMail::STATUT_ECHEC,
                'canal'         => 'serveur',
                'type'          => get_class($mail),
                'sujet'         => method_exists($mail, 'envelope') ? ($mail->envelope()->subject ?? null) : null,
                'destinataires' => implode(', ', $destinataires),
                'dossier_type'  => $dossierType,
                'dossier_id'    => $dossierId,
                'erreur'        => mb_substr($erreur, 0, 2000),
            ]);
        } catch (Throwable $e) {
            Log::warning('journal_mails.echec_ecriture', ['erreur' => $e->getMessage()]);
        }
    }

    /** Mail envoyé (ou refusé) par l'API Brevo. */
    public static function brevo(string $etape, ?string $sujet, string $destinataire, array $copie, ?Campaign $campagne, ?string $erreur = null): void
    {
        try {
            self::noter([
                'statut'        => $erreur ? JournalMail::STATUT_ECHEC : JournalMail::STATUT_ENVOYE,
                'canal'         => 'brevo',
                'type'          => 'Brevo · ' . $etape,
                'sujet'         => $sujet,
                'destinataires' => $destinataire,
                'copie'         => $copie ? implode(', ', $copie) : null,
                'dossier_type'  => $campagne ? 'campagne' : null,
                'dossier_id'    => $campagne?->id,
                'erreur'        => $erreur ? mb_substr($erreur, 0, 2000) : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('journal_mails.echec_ecriture', ['erreur' => $e->getMessage()]);
        }
    }

    private static function noter(array $ligne): void
    {
        JournalMail::create($ligne + [
            'envoye_le' => now(),
            'user_id'   => auth()->id(),
            'sujet'     => null,
        ]);
    }

    /**
     * Dossier concerné, d'après les données du mail : la campagne d'abord,
     * puis facture, réservation, devis, pige (sa campagne), client.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private static function dossier(array $donnees): array
    {
        $valeurs = array_values(array_filter($donnees, 'is_object'));
        foreach ([
            Campaign::class    => 'campagne',
            Invoice::class     => 'facture',
            Reservation::class => 'reservation',
            Quote::class       => 'devis',
        ] as $classe => $type) {
            foreach ($valeurs as $v) {
                if ($v instanceof $classe && $v->getKey()) {
                    return [$type, (int) $v->getKey()];
                }
            }
        }
        foreach ($valeurs as $v) {
            if ($v instanceof Pige && $v->campaign_id) {
                return ['campagne', (int) $v->campaign_id];
            }
        }
        foreach ($valeurs as $v) {
            if ($v instanceof Client && $v->getKey()) {
                return ['client', (int) $v->getKey()];
            }
        }

        return [null, null];
    }
}
