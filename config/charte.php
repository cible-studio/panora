<?php

/*
|--------------------------------------------------------------------------
| Charte graphique de la régie — mails et PDF qui sortent de Panora
|--------------------------------------------------------------------------
|
| Source unique des couleurs, polices et logos utilisés par :
|   • l'habillage des mails (resources/views/components/mail/layout.blade.php
|     et les mails autonomes) ;
|   • les PDF (partiel de styles commun).
|
| Valeurs par défaut : charte CIBLE (site cible-ci.com, palette verrouillée).
| Panora étant commercialisable à d'autres régies, chaque valeur peut être
| remplacée par le .env sans toucher au code.
|
| ⚠ Ne pas ajouter de couleur hors palette sans validation de la direction.
|
*/

return [

    'nom' => env('CHARTE_NOM', 'CIBLE'),

    'slogan' => env('CHARTE_SLOGAN', 'Vous visez juste.'),

    'couleurs' => [
        // Accent principal : boutons, surtitres, focus, alertes / échec.
        'rouge'  => env('CHARTE_ROUGE', '#E20613'),
        // Mises en avant, chiffres clés, « en attente » / option.
        'jaune'  => env('CHARTE_JAUNE', '#FAB80B'),
        // Positif : libre, payé, réussi.
        'vert'   => env('CHARTE_VERT', '#3AA835'),
        // Information, détails.
        'bleu'   => env('CHARTE_BLEU', '#3F7FC0'),
        // Détails.
        'violet' => env('CHARTE_VIOLET', '#81358A'),
        // Neutres.
        'gris'   => env('CHARTE_GRIS', '#E6E6E6'),
        'noir'   => env('CHARTE_NOIR', '#111111'),
        'blanc'  => env('CHARTE_BLANC', '#FFFFFF'),
    ],

    // Ordre du liseré multicolore (les cinq couleurs du symbole).
    'lisere' => ['rouge', 'jaune', 'vert', 'bleu', 'violet'],

    'polices' => [
        // Mails : chargées depuis Google Fonts, Arial si la messagerie
        // les refuse (Gmail, Outlook).
        'titres'       => env('CHARTE_POLICE_TITRES', 'Poppins'),
        'texte'        => env('CHARTE_POLICE_TEXTE', 'Nunito'),
        'google_fonts' => env('CHARTE_GOOGLE_FONTS', 'https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Nunito:wght@400;700&display=swap'),
        'secours'      => 'Arial, Helvetica, sans-serif',
    ],

    // Logos (chemins dans public/). « clair » = pour fond clair,
    // « sombre » = pour fond foncé.
    'logos' => [
        'clair'  => env('CHARTE_LOGO_CLAIR', 'images/logol.png'),
        'sombre' => env('CHARTE_LOGO_SOMBRE', 'images/logob.png'),
    ],

    // Coordonnées affichées en pied de mail / de PDF.
    'coordonnees' => [
        'activite'   => env('CHARTE_ACTIVITE', 'Régie publicitaire · Côte d\'Ivoire'),
        'adresse'    => env('CHARTE_ADRESSE', 'Rue des Ambassadeurs, Riviera M\'Badon · 10 BP 1029 Abidjan 10'),
        'telephones' => env('CHARTE_TELEPHONES', '+225 07 98 49 66 74 · +225 27 22 20 80 08'),
        'email'      => env('CHARTE_EMAIL', 'commercial@cible-ci.com'),
        'site'       => env('CHARTE_SITE', 'cible-ci.com'),
    ],

];
