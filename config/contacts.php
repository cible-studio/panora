<?php

/*
|--------------------------------------------------------------------------
| Contacts envoyés depuis le Google Sheets (2026-10-28)
|--------------------------------------------------------------------------
|
| Le bouton « Envoyer à Panora » du Sheets appelle /api/contacts-gmail/*
| avec un jeton secret dans l'en-tête (Authorization: Bearer …), jamais
| dans l'adresse. Seules les personnes de la liste peuvent envoyer.
|
*/

return [

    'gmail' => [
        // Jeton partagé avec le script du Sheets. Vide = porte fermée.
        'token' => env('GMAIL_SYNC_TOKEN'),

        // Adresses Google autorisées à envoyer, séparées par des virgules.
        'autorises' => array_values(array_filter(array_map(
            fn ($e) => mb_strtolower(trim($e)),
            explode(',', (string) env('GMAIL_SYNC_AUTORISES', ''))
        ))),

        // Lignes par appel (le script découpe au-delà).
        'max_lignes' => 500,
    ],

    // Nos propres adresses : jamais rattachées à une entreprise cliente.
    'domaines_internes' => ['cible-ci.com', 'cible.ci'],

    // Messageries personnelles : une adresse de ces domaines ne dit rien de
    // l'entreprise, elle se rattache par le nom seulement. Source unique pour
    // Panora et le Sheets (qui la lit par /api/contacts-gmail/listes).
    'messageries_personnelles' => [
        'gmail.com', 'yahoo.fr', 'yahoo.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.fr', 'live.com', 'icloud.com',
        'aol.com', 'ymail.com', 'msn.com', 'me.com', 'gmx.fr', 'gmx.com', 'proton.me', 'protonmail.com', 'yahoo.co.uk', 'rocketmail.com',
        '163.com', '126.com', 'qq.com', 'sina.com', 'yandex.ru', 'yandex.com', 'mail.ru', 'orange.fr', 'free.fr', 'laposte.net', 'sfr.fr', 'wanadoo.fr',
        'hotmail.be', 'live.be', 'outlook.be', 'yahoo.ca', 'yahoo.es', 'hotmail.es', 'yahoo.it', 'hotmail.it', 'aim.com', 'zoho.eu', 'mail.com',
    ],

];
