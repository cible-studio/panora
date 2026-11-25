<?php

/*
| Promotions envoyées par mail (Brevo) — page d'arrivée qui compte les clics
| (2026-11-25).
|
| Un lien du mail ouvre https://panora-cible.com/offre/{code}[/{format}] :
|   · Brevo compte le clic, client par client et lien par lien (un lien
|     « mailto: » direct n'était pas compté : 0 clic au Black Friday) ;
|   · Panora le compte aussi (table promo_clics, commande promos:clics) ;
|   · la page ouvre le mail de réponse prérempli vers le service commercial,
|     avec le format choisi, et propose l'appel si rien ne s'ouvre.
|
| Une nouvelle promotion = une entrée ici (images dans
| public/images/cible/promos/). 'active' => false : la page dit que l'offre
| est terminée et renvoie vers le service commercial.
|
| PROMO_COPIE (serveur, 25/11) : adresses mises en copie (Cc) du mail
| prérempli, séparées par des virgules. Des adresses nominatives : elles
| vivent dans Coolify, jamais dans le code. Vide = pas de copie.
*/

$copie = array_values(array_filter(array_map('trim', explode(',', (string) env('PROMO_COPIE', '')))));

return [
    'interieur-35' => [
        'active'    => true,
        // \u{00A0} : espace insécable (« 35 % », « 12 m² » jamais coupés).
        'titre'     => "-35\u{00A0}% sur nos panneaux à l'intérieur du pays",
        'accroche'  => "En ce moment, CIBLE vous offre 35\u{00A0}% de réduction sur ses panneaux à l'intérieur du pays, en 50\u{00A0}m², 12\u{00A0}m² ou 6\u{00A0}m².",
        'slogan'    => "La puissance d'un réseau national au service de votre visibilité.",
        'affiche'   => 'images/cible/promos/interieur-35-2026.jpg',
        'formats'   => [
            '50m2' => ['libelle' => '50 m²', 'detail' => 'Grand format, en bord de route', 'image' => 'images/cible/promos/interieur-35-2026-50m2.jpg'],
            '12m2' => ['libelle' => '12 m²', 'detail' => 'Au cœur de la ville',          'image' => 'images/cible/promos/interieur-35-2026-12m2.jpg'],
            '6m2'  => ['libelle' => '6 m²',  'detail' => 'Au plus près des passants',     'image' => 'images/cible/promos/interieur-35-2026-6m2.jpg'],
        ],
        'telephone' => '07 69 30 46 02',
        'email'     => 'commercial@cible-ci.com',
        'copie'     => $copie,
        'sujet'     => "Offre -35 % — panneaux à l'intérieur du pays",
        // « :format » est remplacé par le format choisi (vide sinon).
        'message'   => "Bonjour,\n\nJe suis intéressé(e) par l'offre de 35 % de réduction sur vos panneaux à l'intérieur du pays.\n\nFormat souhaité (50 m², 12 m² ou 6 m²) : :format\nVille(s) : \nPériode : \n\n",
    ],
];
