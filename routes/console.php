<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Réservations confirmées expirées → "termine" + panneaux libérés
//    Tous les jours à 01h00
Schedule::command('reservations:sync-expired')->dailyAt('01:00');

// 2a. Options dont la période est passée → "annule" + panneaux libérés
//     Tous les jours à 01h15
Schedule::command('reservations:expire-options')->dailyAt('01:15');

// 2b. Options trop anciennes (> 7j) même si période future → libère les panneaux bloqués
//     Tous les jours à 01h20
Schedule::command('reservations:expire-old-options', ['--days' => 7])->dailyAt('01:20');

// 2c. Cycle de vie devis commerciaux : alerte J-3 + bascule EXPIRE
//     Tous les jours à 01h25 — indépendant des réservations.
Schedule::command('quotes:handle-expiry')->dailyAt('01:25');

// 3. Campagnes actives expirées → "termine"
//    Tous les jours à 01h30
Schedule::command('campaigns:sync-expired')->dailyAt('01:30');

Schedule::command('propositions:expire')->everyFifteenMinutes();

// Rappels J+2 et J+5 aux clients dont la proposition est en attente
Schedule::command('propositions:send-rappels')->dailyAt('09:00');

// 4. Génération automatique des alertes
//    Tous les jours à 07h00
Schedule::command('alerts:generate')->dailyAt('07:00');

// 4-bis. Échéances de facturation (J-7, J-3, dépassée) — tourne avant
//        alerts:generate pour que les retards soient visibles dans le
//        même cycle quotidien.
Schedule::command('finance:check-due-dates')->dailyAt('06:30');

// 5. Synchronisation statut panneaux externes
//    Tous les jours à 02h00
Schedule::command('external-panels:sync-status')->dailyAt('02:00');

// 6. Activation automatique des campagnes planifiées
//    Tous les jours à 00h05
Schedule::command('campaigns:activate-planned')->dailyAt('00:05');

// 7. Rappels propositions (J+2 et J+5 sur les propositions en attente)
//    Tous les jours à 09h00 — heure d'ouverture, plus efficace pour la
//    lecture client. withoutOverlapping() évite les doublons en cas de
//    cron qui chevauche.
Schedule::command('propositions:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// 8. Relances factures impayées (J+7, J+14, J+30 depuis issued_at).
//    Idempotent : le N° de relance est stocké en metadata du PublicLink
//    pour éviter d'envoyer 2× la même relance.
Schedule::command('invoices:send-reminders')
    ->dailyAt('09:15')
    ->withoutOverlapping();

// 9. Relances campagnes arrivant à échéance (J-7).
//    Propose un renouvellement / prolongation au client.
//    Idempotent via cache flag (TTL 60 jours).
Schedule::command('campaigns:notify-ending', ['--days' => 7])
    ->dailyAt('10:00')
    ->withoutOverlapping();

// 10. Alertes opérationnelles internes (admin / comptable / équipe terrain).
//     Idempotent via AdminAlertNotifier::dedupKey (cooldown 30 min).
//
// ⚠ decap:notify-overdue DÉSACTIVÉ le 2026-08-03 (demande patronne :
//   « sa en fait trop »). Tant qu'une campagne terminée a des panneaux
//   non décapés depuis plus de 7j, le job renvoyait l'alerte chaque
//   matin → boîte mail saturée. Le suivi passe désormais uniquement
//   par le rapport /admin/rapports#tab-decap consulté à la demande.
//   Pour réactiver : décommenter les 3 lignes ci-dessous.
// Schedule::command('decap:notify-overdue', ['--days' => 7])
//     ->dailyAt('08:30')
//     ->withoutOverlapping();
Schedule::command('taxes:notify-due-soon', ['--days' => 15])
    ->dailyAt('08:45')
    ->withoutOverlapping();

// 11. Récaps périodiques (équipe + direction + comptable).
//     Décision métier : pas de récap email le samedi/dimanche (aucune
//     activité opérationnelle ces jours-là → contenu vide et bruit dans
//     les boîtes mail). Le recap weekly tombe déjà sur le lundi, donc
//     non concerné. Pour le monthly : si le 1er tombe un weekend, on
//     skip — le récap mensuel est non-critique et sera couvert par le
//     mensuel du mois suivant.
Schedule::command('recap:daily')
    ->dailyAt('18:00')
    ->weekdays()
    ->withoutOverlapping();
Schedule::command('recap:weekly-direction')
    ->mondays()->at('08:00')
    ->withoutOverlapping();
Schedule::command('recap:monthly')
    ->monthlyOn(1, '06:00')
    ->weekdays()
    ->withoutOverlapping();

// 12. Purge mensuelle des PublicLinks expirés/révoqués (> 90j).
Schedule::call(function () {
    \App\Services\PublicLinkService::purgeOld(90);
})->monthlyOn(1, '03:00');

// 13. Phase 4 cahier Recouvrement §7 — alertes échéances factures.
//     Matérialise les alertes J-15/-7/-3/-1/+1/+7/+15/+30 par canal
//     activé (in_app + email par défaut, cf. config('billing.alerts'))
//     et envoie celles dont la date de déclenchement est atteinte.
//     Bascule aussi les factures dont une échéance est dépassée +
//     reste à payer → status='en_retard'.
Schedule::command('invoices:alerts')
    ->dailyAt('07:00')
    ->withoutOverlapping();
