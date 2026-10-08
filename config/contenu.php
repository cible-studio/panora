<?php

/*
|--------------------------------------------------------------------------
| Contenu éditable du site vitrine
|--------------------------------------------------------------------------
|
| Ce fichier porte les valeurs PAR DÉFAUT, versionnées dans git. L'espace
| admin n'y touche jamais : il écrit des surcharges en JSON sur le volume
| persistant (storage/app/contenu/), et App\Support\Contenu fusionne les
| deux à l'affichage.
|
| Conséquence voulue : si le volume est perdu, mal monté ou vidé, le site
| continue d'afficher le dernier contenu committé au lieu d'une page vide.
| Le volume est une couche de surcharge, jamais la source de vérité.
|
| Pour ajouter un champ éditable : le déclarer ici, puis l'exposer dans
| l'écran d'admin correspondant. Rien d'autre à faire.
|
*/

return [

    'coordonnees' => [
        'tel_mobile'      => '+225 07 00 78 06 28',
        'tel_fixe'        => '+225 27 22 20 80 08',
        'email_commercial'=> 'commercial@cible-ci.com',
        'email_secretariat'=> 'secretariat@cible-ci.com',
        'adresse_rue'     => 'Rue des Ambassadeurs',
        'adresse_complement' => "Riviera M'Badon · 10 BP 1029 Abidjan 10",
    ],

    /*
    | Chiffres officiels. Modifiables ici et depuis l'admin, mais ce sont
    | des affirmations publiques : cf. CLAUDE.md, le parc est communiqué en
    | volume arrondi (« +400 ») et non plus au chiffre exact.
    */
    'chiffres' => [
        'panneaux'     => '400',
        'communes'     => '31',
        'annees'       => '30',
        'distinctions' => '3',
    ],

    /*
    | Les 6 réalisations. `filtres` alimente la barre de filtres de
    | /references ; `couleur` doit rester une variable de la palette.
    */
    'realisations' => [
        'orange' => [
            'nom'      => 'Orange',
            'cat'      => 'Brand experience & activation terrain',
            'titre'    => 'Faire vivre la marque au plus près de ses publics.',
            'texte'    => "Conception et déploiement d'une expérience de marque destinée à renforcer la proximité avec les audiences, générer de l'interaction et amplifier la visibilité de l'opération.",
            'services' => 'Concept créatif · Activation terrain · Production · Coordination opérationnelle',
            'image'    => 'images/cible/campagne-1.jpg',
            'couleur'  => 'var(--violet)',
            'filtres'  => ['brand-experience', 'street-marketing'],
        ],
        'cofina' => [
            'nom'      => 'Groupe Cofina',
            'cat'      => 'Production audiovisuelle',
            'titre'    => 'Traduire une vision institutionnelle en récit de marque.',
            'texte'    => "Réalisation d'un film institutionnel conçu pour valoriser l'identité, la mission et l'impact du groupe auprès de ses parties prenantes.",
            'services' => 'Conseil éditorial · Scénarisation · Tournage · Postproduction',
            'image'    => 'images/cible/campagne-2.jpg',
            'couleur'  => 'var(--vert)',
            'filtres'  => ['production-audiovisuelle'],
        ],
        'snedai' => [
            'nom'      => 'SNEDAI',
            'cat'      => 'Stratégie de communication intégrée',
            'titre'    => 'Construire une présence cohérente sur plusieurs points de contact.',
            'texte'    => "Conception d'une stratégie associant communication institutionnelle, supports de visibilité et contenus afin de renforcer la lisibilité et la portée de la marque.",
            'services' => 'Stratégie · Création · Déploiement multicanal · Suivi',
            'image'    => 'images/cible/campagne-3.jpg',
            'couleur'  => 'var(--bleu)',
            'filtres'  => ['affichage-regie', 'digital-contenus'],
        ],
        'sgs-sicta' => [
            'nom'      => 'SGS · SICTA',
            'cat'      => 'Création digitale & réseaux sociaux',
            'titre'    => "Prolonger l'expérience de marque en ligne.",
            'texte'    => "Conception de contenus visuels et animation des prises de parole digitales afin de renforcer la présence, la cohérence et l'engagement de la marque sur les réseaux sociaux.",
            'services' => 'Direction artistique · Création de contenus · Réseaux sociaux · Suivi éditorial',
            'image'    => 'images/cible/campagne-4.jpg',
            'couleur'  => 'var(--jaune)',
            'filtres'  => ['digital-contenus'],
        ],
        'ifg' => [
            'nom'      => 'IFG',
            'cat'      => 'Stand expérientiel',
            'titre'    => 'Transformer un espace en expérience de marque.',
            'texte'    => "Conception et réalisation d'un stand pensé pour attirer les visiteurs, valoriser l'offre et favoriser les échanges avec les publics présents.",
            'services' => "Concept · Design d'espace · Production · Installation",
            'image'    => 'images/cible/campagne-5.jpg',
            'couleur'  => 'var(--rouge)',
            'filtres'  => ['brand-experience', 'design-evenementiel'],
        ],
        'sigfu' => [
            'nom'      => 'SIGFU',
            'cat'      => 'Design & architecture événementielle',
            'titre'    => 'Donner une forme visible et cohérente à une identité institutionnelle.',
            'texte'    => "Conception d'un dispositif architectural et visuel destiné à valoriser la présence de l'organisation et à renforcer la qualité de l'expérience proposée aux visiteurs.",
            'services' => 'Direction artistique · Design · Architecture · Production',
            'image'    => 'images/cible/campagne-6.jpg',
            'couleur'  => 'var(--violet)',
            'filtres'  => ['design-evenementiel'],
        ],
    ],

];
