<?php

/*
|--------------------------------------------------------------------------
| Baromètre clients — envoi du questionnaire par Panora (2026-11-17)
|--------------------------------------------------------------------------
|
| Décisions validées par écrit le 2026-11-16 et le 2026-11-17 :
|   · un seul mail par entreprise, à l'interlocuteur choisi par
|     Client::destinataireBarometre() ;
|   · régies, nouveaux clients et adresses écartées des envois commerciaux
|     exclus ; l'écran affiche le nombre exact avant l'envoi ;
|   · lien personnel sécurisé qui EXPIRE SEUL à la date limite ;
|   · envoi par Brevo, comme le mail J-7 ; relance par un bouton ;
|   · Google prévient Panora à chaque réponse (on ne relance que ceux qui
|     n'ont pas répondu) ;
|   · accès : les admins et les personnes de BAROMETRE_ACCES.
|
| Aucune valeur propre à une régie n'est codée en dur : le questionnaire,
| les exclusions et les accès se règlent ici, dans le .env ou à l'écran.
| Guide : docs/outils/barometre-panora.gs (en-tête) et A_TESTER § 35.
|
*/

return [

    // Édition en cours : une ligne par client et par édition.
    'edition' => (string) env('BAROMETRE_EDITION', '2026'),

    // test : seul « Envoyer un test » fonctionne (à l'adresse de la personne
    // connectée). production : « Envoyer à tous » et « Relancer » ouverts.
    // Le staging DOIT rester en test (base copiée de la prod, vrais clients).
    'mode' => env('BAROMETRE_MODE', 'test'),

    // Date limite proposée à la création de l'édition. Ensuite, elle se
    // change à l'écran ; les liens expirent à 23:59:59 ce jour-là.
    'date_limite' => env('BAROMETRE_DATE_LIMITE', '2026-11-30'),

    // Lien prérempli du formulaire Google, SANS le nom de la structure :
    // Panora ajoute « <champ_structure>=<nom du client> ».
    'formulaire'      => env('BAROMETRE_FORMULAIRE'),
    'champ_structure' => env('BAROMETRE_CHAMP_STRUCTURE'),

    // Les liens de test ouvrent le questionnaire au nom « TEST — <client> » :
    // une réponse envoyée depuis un test n'est jamais comptée pour le client.
    'prefixe_test' => 'TEST — ',

    // Jeton du script Google (en-tête Authorization: Bearer …). Vide = la
    // porte d'entrée répond 404. Ne jamais le committer.
    'jeton_google' => env('BAROMETRE_JETON_GOOGLE'),

    // Qui a l'écran : les admins, plus BAROMETRE_ACCES (adresses e-mail des
    // comptes Panora, séparées par des virgules) — lu par config/acces.php.

    // Expéditeur affiché (l'adresse reste celle de Brevo, BREVO_SENDER_EMAIL).
    // Vide : « <nom de la signature> – <société> », sinon l'équipe commerciale.
    'expediteur' => env('BAROMETRE_EXPEDITEUR'),

    // Adresse qui reçoit les réponses des clients (aussi réglable à l'écran).
    'repondre_a' => env('BAROMETRE_REPONDRE_A'),

    // Durée annoncée dans le mail (objet et texte de la direction, 17/11).
    'duree' => env('BAROMETRE_DUREE', '5 minutes'),

    // Signature du mail (texte de la direction, 17/11). Sans nom :
    // « L'équipe commerciale <charte.nom> ».
    'signature' => [
        'nom'      => env('BAROMETRE_SIGNATURE_NOM'),
        'fonction' => env('BAROMETRE_SIGNATURE_FONCTION'),
        'societe'  => env('BAROMETRE_SIGNATURE_SOCIETE'),
    ],

    // Envois traités par minute par la tâche planifiée barometre:envoyer.
    'lot' => (int) env('BAROMETRE_LOT', 60),

];
