<?php

/*
|--------------------------------------------------------------------------
| Diffusion automatique des disponibilités
|--------------------------------------------------------------------------
|
| Règles validées le 2026-09-29 — cf. docs/DIFFUSION_DISPONIBILITES.md.
|
*/

return [

    // ── SÉCURITÉ ─────────────────────────────────────────────────────
    //
    // 'test'       : TOUS les envois (automatiques et manuels) partent vers
    //                la liste Brevo « Tests internes ». Aucun client réel
    //                ne peut recevoir quoi que ce soit.
    // 'production' : les envois partent vers la liste des clients.
    //
    // Défaut volontairement sur 'test' : le staging (branche develop) fait
    // tourner le même planificateur que la prod. Sans ce garde-fou, un
    // staging configuré avec la vraie clé Brevo écrirait aux clients.
    'mode' => env('DIFFUSION_MODE', 'test'),

    // Envoi automatique le 1er et le 15. false = seul le bouton manuel
    // de l'admin permet d'envoyer. À activer après le premier envoi réel
    // validé à la main.
    'auto' => (bool) env('DIFFUSION_AUTO', false),

    // Heure d'envoi (le serveur tourne en UTC = heure d'Abidjan).
    'heure_envoi'       => env('DIFFUSION_HEURE', '10:00'),
    // Préparation du PDF : tout le parc avec photos prend plusieurs
    // minutes (~7 min mesurées en dev). Une heure de marge avant l'envoi.
    'heure_preparation' => env('DIFFUSION_HEURE_PREPARATION', '09:00'),

    // Confirmation interne après chaque envoi (et alerte en cas d'échec) :
    // cette adresse + tous les utilisateurs actifs au rôle Media Planner.
    'confirmation_email' => env('DIFFUSION_CONFIRMATION_EMAIL', env('BREVO_SENDER_EMAIL')),

    // Objet du mail client. {periode} est remplacé par « du 1er au
    // 30 novembre 2026 ». Ignoré si le modèle Brevo fixe son propre objet.
    'objet' => env('DIFFUSION_OBJET', 'Nos disponibilités {periode}'),

    // En dessous de ce délai depuis le dernier envoi réussi, le bouton
    // manuel exige une confirmation renforcée (taper ENVOYER).
    'delai_alerte_heures' => 24,

];
