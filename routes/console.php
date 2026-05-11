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

// 3. Campagnes actives expirées → "termine"
//    Tous les jours à 01h30
Schedule::command('campaigns:sync-expired')->dailyAt('01:30');

Schedule::command('propositions:expire')->everyFifteenMinutes();

// Rappels J+2 et J+5 aux clients dont la proposition est en attente
Schedule::command('propositions:send-rappels')->dailyAt('09:00');

// 4. Génération automatique des alertes
//    Tous les jours à 07h00
Schedule::command('alerts:generate')->dailyAt('07:00');

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
