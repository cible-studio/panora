<?php

/*
|--------------------------------------------------------------------------
| Mails — règles d'envoi transverses
|--------------------------------------------------------------------------
|
| SOURCE UNIQUE de la copie commerciale (2026-10-30).
|
*/

return [

    /*
    | Copie systématique des mails COMMERCIAUX et de FACTURATION envoyés
    | au client : devis, propositions, démarrage de campagne, réservation
    | confirmée, factures et relances d'impayés, fin de campagne et
    | renouvellement.
    |
    | Règle validée par la direction le 2026-10-30.
    |
    | Ne concerne PAS les mails de suivi d'exécution (preuve d'affichage,
    | décapage, maintenance, enquête de satisfaction) ni les mails
    | techniques (mot de passe, invitation d'un utilisateur client).
    |
    | Le marquage se fait par l'interface App\Mail\Concerns\MailCommercial :
    | un mailable qui l'implémente reçoit la copie automatiquement, quel
    | que soit le chemin d'envoi (NotificationMailer, Mail::to, file
    | d'attente). Cf. AppServiceProvider, écouteur MessageSending.
    |
    | Plusieurs adresses : séparées par des virgules, des points-virgules
    | ou des espaces. Vide = aucune copie.
    */
    'copie_commerciale' => env('MAIL_COPIE_COMMERCIALE', 'stephane.oponou@cible-ci.com'),

];
