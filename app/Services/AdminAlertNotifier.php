<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * AdminAlertNotifier — dispatch d'emails aux destinataires staff selon leur rôle.
 *
 * Centralise la résolution des destinataires :
 *   - 'admin' : tous les users role=admin
 *   - 'commercial' : commercial assigné à l'objet OU tous les commerciaux
 *   - 'mediaplanner' : tous les users role=mediaplanner
 *   - 'comptable' : users role=admin (à défaut d'un rôle comptable dédié)
 *
 * Throttling : un même titre n'est pas envoyé 2× en moins de 30 minutes
 * (anti-spam si plusieurs déclencheurs simultanés).
 *
 * Usage :
 *   AdminAlertNotifier::notify(
 *       to: ['admin', 'commercial'],
 *       severity: 'warning',
 *       title: '...',
 *       summary: '...',
 *       lines: [...],
 *       ctaLabel: 'Voir →',
 *       ctaUrl: route(...),
 *       dedupKey: 'pige-uploaded-' . $pige->id, // optionnel — anti doublon
 *   );
 */
class AdminAlertNotifier
{
    /**
     * @param array  $to        Liste de rôles ou emails (ex: ['admin', 'commercial', 'foo@bar.com'])
     * @param string $severity  info | success | warning | danger
     */
    public static function notify(
        array $to,
        string $severity,
        string $title,
        string $summary,
        array $lines = [],
        ?string $ctaLabel = null,
        ?string $ctaUrl = null,
        ?string $footer = null,
        ?string $emoji = null,
        ?string $dedupKey = null,
        ?User $commercialAssigned = null,
    ): void {
        // Kill-switch staff alerts (2026-08-10) : désactivable sur staging
        // via MAIL_STAFF_ALERTS_ENABLED=false pour économiser les crédits
        // SMTP. Log info pour visibilité côté logs Coolify. Les mails
        // CLIENT ne passent PAS par ce chemin — pas d'impact métier.
        if (!config('mail.staff_alerts_enabled', true)) {
            Log::info('admin_alert.skipped.staff_alerts_disabled', [
                'title' => $title,
                'to'    => $to,
            ]);
            return;
        }

        $emails = self::resolveRecipients($to, $commercialAssigned);
        if (empty($emails)) {
            Log::info('admin_alert.skipped.no_recipient', ['title' => $title]);
            return;
        }

        // Dedup anti-spam (30 min)
        if ($dedupKey) {
            $cacheKey = 'admin_alert.dedup.' . md5($dedupKey);
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                return;
            }
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addMinutes(30));
        }

        $mail = new AdminAlertMail(
            severity: $severity,
            title:    $title,
            summary:  $summary,
            lines:    $lines,
            ctaLabel: $ctaLabel,
            ctaUrl:   $ctaUrl,
            footer:   $footer,
            emoji:    $emoji,
        );

        $mailer = app(NotificationMailer::class);
        $mailer->sendSilently(
            $emails,
            $mail,
            cc: null,
            context: ['type' => 'admin_alert', 'title' => $title, 'severity' => $severity],
        );
    }

    /**
     * Résout la liste d'emails à partir d'un mix de rôles + emails directs.
     */
    protected static function resolveRecipients(array $to, ?User $commercialAssigned = null): array
    {
        $emails = [];

        foreach ($to as $entry) {
            // Email direct
            if (filter_var($entry, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $entry;
                continue;
            }

            // Rôle → emails actifs
            switch (strtolower($entry)) {
                case 'commercial_assigned':
                    if ($commercialAssigned?->email) {
                        $emails[] = $commercialAssigned->email;
                    }
                    break;

                case 'admin':
                case 'commercial':
                case 'mediaplanner':
                    $list = User::query()
                        ->where('role', $entry)
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->toArray();
                    $emails = array_merge($emails, $list);
                    break;

                case 'comptable':
                    // À défaut d'un rôle dédié : admin
                    $list = User::query()
                        ->whereIn('role', ['admin'])
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->toArray();
                    $emails = array_merge($emails, $list);
                    break;
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }
}
