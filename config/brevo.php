<?php

/*
|--------------------------------------------------------------------------
| Brevo — connexion au service d'envoi
|--------------------------------------------------------------------------
|
| Tout vient du .env : chaque régie qui utilise Panora branche son propre
| compte Brevo. Aucune valeur propre à CIBLE n'est codée en dur.
|
| ⚠ La clé API ne doit JAMAIS être commitée ni transmise par messagerie.
|
| Guide de configuration pas à pas : docs/DIFFUSION_DISPONIBILITES.md
|
*/

return [

    // Clé API Brevo : Brevo → SMTP et API → Clés API.
    'api_key' => env('BREVO_API_KEY'),

    'base_url' => env('BREVO_BASE_URL', 'https://api.brevo.com/v3'),

    'timeout' => (int) env('BREVO_TIMEOUT', 30),

    // Expéditeur — doit être validé dans Brevo (Expéditeurs) et appartenir
    // à un domaine authentifié, sinon les envois partent en spam.
    // Guillemets retirés : Coolify les garde tels quels si on écrit la
    // valeur entre guillemets (« \"CIBLE CI\" » vu en prod le 2026-10-01).
    'sender' => [
        'email' => trim((string) env('BREVO_SENDER_EMAIL'), " \"'\\") ?: null,
        'name'  => trim((string) env('BREVO_SENDER_NAME', 'Service commercial'), " \"'\\") ?: 'Service commercial',
    ],

    // Identifiants numériques des listes Brevo (Contacts → Listes).
    'lists' => [
        'clients' => env('BREVO_LIST_CLIENTS') ? (int) env('BREVO_LIST_CLIENTS') : null,
        'tests'   => env('BREVO_LIST_TESTS')   ? (int) env('BREVO_LIST_TESTS')   : null,
    ],

    // Modèle de mail conçu dans l'éditeur Brevo (Modèles). Vide → Panora
    // utilise son gabarit par défaut (resources/views/diffusion/campagne-defaut).
    'template_id' => env('BREVO_TEMPLATE_DISPOS') ? (int) env('BREVO_TEMPLATE_DISPOS') : null,

    // Page de désinscription personnalisée (logo, couleurs, textes),
    // conçue dans Brevo. Identifiant de 24 caractères visible dans
    // l'adresse de la page quand on la modifie. Vide → page Brevo par défaut.
    'unsubscribe_page_id' => env('BREVO_UNSUBSCRIBE_PAGE_ID') ?: null,

    // Jeton secret placé dans l'URL du webhook configuré dans Brevo :
    //   https://<domaine>/webhooks/brevo/<BREVO_WEBHOOK_TOKEN>
    // Longue chaîne aléatoire, ex. : php artisan tinker → Str::random(48)
    'webhook_token' => env('BREVO_WEBHOOK_TOKEN'),

];
