<?php

/*
|--------------------------------------------------------------------------
| Schéma de l'espace d'administration
|--------------------------------------------------------------------------
|
| Décrit les contenus modifiables sans développeur. Chaque champ porte son
| libellé, son type, sa valeur par défaut et, si besoin, une aide.
|
| La valeur par défaut vit ICI et nulle part ailleurs : elle est versionnée
| avec le code et sert de repli permanent. L'admin écrit ses modifications
| sur le volume persistant, par-dessus. Volume perdu ou vidé : le site
| réaffiche ces valeurs au lieu d'une page blanche.
|
| Pour rendre un nouveau contenu éditable : ajouter une ligne ici, puis
| appeler Contenu::get('page.champ') dans la vue. Rien d'autre — formulaire,
| validation et enregistrement sont générés.
|
| ⚠ Ne pas déplacer un champ vers une autre page sans migrer sa surcharge :
| la clé de stockage est « page.champ ».
|
*/

return [

/* ═══════════════════════════════════════════════════════════════════
   ACCUEIL
══════════════════════════════════════════════════════════════════ */
'accueil' => [
    'titre'   => "Page d'accueil",
    'resume'  => 'Bandeau principal, récit de marque, territoires, mission.',
    'couleur' => 'var(--rouge)',
    'url'     => '/',
    'groupes' => [

        ['titre' => 'Bandeau principal', 'champs' => [
            'hero_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte',
                'defaut' => "Régie & studio · Côte d'Ivoire · depuis 1994"],
            'hero_titre_1' => ['label' => 'Titre — ligne 1', 'type' => 'texte',
                'defaut' => 'Votre visibilité.'],
            'hero_titre_2' => ['label' => 'Titre — ligne 2', 'type' => 'texte',
                'defaut' => 'Notre intelligence média.'],
            'hero_titre_3' => ['label' => 'Titre — ligne 3 (en rouge)', 'type' => 'texte',
                'defaut' => 'Vos résultats.'],
            'hero_sous_titre' => ['label' => 'Sous-titre', 'type' => 'zone',
                'defaut' => "Depuis plus de 30 ans, nous accompagnons les entreprises, les institutions et les marques avec des stratégies de visibilité qui combinent affichage, communication 360°, digital, activation terrain et intelligence média."],
            'hero_accroche' => ['label' => 'Accroche', 'type' => 'zone',
                'aide' => 'Le texte placé entre **doubles astérisques** ressort en rouge.',
                'defaut' => "Votre marque mérite plus qu'une campagne. Elle mérite un impact durable : **nous construisons votre visibilité.**"],
            'hero_cta1' => ['label' => 'Bouton principal', 'type' => 'texte',
                'defaut' => 'Rendre ma marque visible'],
            'hero_cta2' => ['label' => 'Bouton secondaire', 'type' => 'texte',
                'defaut' => 'Voir nos réalisations'],
            'hero_image' => ['label' => 'Image du bandeau', 'type' => 'image',
                'defaut' => 'images/perroquet-cible.jpg',
                'aide'   => 'Format carré recommandé, au moins 900 px.'],
        ]],

        ['titre' => 'Bandeau défilant', 'aide' => 'Les 6 mentions qui défilent sous le bandeau principal.', 'champs' => [
            'ticker' => ['label' => 'Mentions', 'type' => 'liste',
                'defaut' => "+ 500 campagnes\nAbidjan & Intérieur du Pays\n30 ans d'expertise\n5 territoires de visibilité\nOnline & Offline\nDe la rue au digital",
                'aide'   => 'Une mention par ligne. Elles défilent en boucle.'],
        ]],

        ['titre' => 'Récit de marque', 'champs' => [
            'marque_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Opération plume rouge'],
            'marque_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Faire voir une marque est simple. La rendre incontournable est un métier.'],
            'marque_intro' => ['label' => 'Introduction', 'type' => 'zone',
                'defaut' => "Trente ans à rendre visibles les marques dans le paysage ivoirien — de l'affiche à la campagne 360°."],
            'marque_1_titre' => ['label' => 'Étape 1 — titre', 'type' => 'texte',
                'defaut' => 'Notre origine — maîtres de la visibilité extérieure'],
            'marque_1_texte' => ['label' => 'Étape 1 — texte', 'type' => 'zone',
                'defaut' => "Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire."],
            'marque_2_titre' => ['label' => 'Étape 2 — titre', 'type' => 'texte',
                'defaut' => 'Notre évolution — la visibilité devient mesurable'],
            'marque_2_texte' => ['label' => 'Étape 2 — texte', 'type' => 'zone',
                'defaut' => "Les usages ont changé, les attentes des annonceurs aussi. Le digital 360° prolonge notre réseau et le rend mesurable."],
            'marque_3_titre' => ['label' => 'Étape 3 — titre', 'type' => 'texte',
                'defaut' => 'Notre force — le terrain et la donnée'],
            'marque_3_texte' => ['label' => 'Étape 3 — texte', 'type' => 'zone',
                'defaut' => 'Trente ans de connaissance du terrain ivoirien fusionnés avec une approche moderne.'],
            'marque_signature' => ['label' => 'Signature', 'type' => 'texte',
                'aide' => 'Le texte placé entre **doubles astérisques** ressort en jaune.',
                'defaut' => "Créer l'impact. **Construire la notoriété.**"],
            'marque_image' => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/perroquet-cible.jpg'],
        ]],

        ['titre' => 'Territoires de visibilité', 'aide' => 'Les 5 onglets interactifs.', 'champs' => [
            'terr_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Où votre marque apparaît'],
            'terr_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Cinq territoires de visibilité, une seule audience.'],
            'terr_intro' => ['label' => 'Introduction', 'type' => 'zone',
                'defaut' => 'Les cinq couleurs représentent les cinq espaces que traverse une journée abidjanaise.'],
            'terr_aide' => ['label' => 'Consigne au visiteur', 'type' => 'texte',
                'defaut' => 'Cliquez sur un territoire pour le découvrir'],
        ]],

        ['titre' => 'Territoire 1 — affichage', 'champs' => [
            'terr1_onglet'  => ['label' => 'Nom de l\'onglet', 'type' => 'texte', 'defaut' => "L'Affichage Outdoor"],
            'terr1_surtitre'=> ['label' => 'Légende du chiffre', 'type' => 'texte', 'defaut' => 'Panneaux en exploitation'],
            'terr1_titre'   => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Affichage grand format : le seul média qu'on ne peut pas fermer."],
            'terr1_texte'   => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "L'affichage grand format reste le seul média que personne ne peut sauter, bloquer ou faire défiler."],
            'terr1_tags'    => ['label' => 'Étiquettes', 'type' => 'liste',
                'defaut' => "Classiques\nLumipub\nTrivision\nPanoramiques\nÉcrans digitaux\nAffichage en magasin"],
            'terr1_preuve'  => ['label' => 'Preuve', 'type' => 'zone',
                'defaut' => 'Chaque campagne se termine par une pige photo horodatée depuis le terrain.'],
            'terr1_image'   => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/pole-1-affichage.jpg'],
        ]],

        ['titre' => 'Territoire 2 — mobile', 'champs' => [
            'terr2_onglet'  => ['label' => 'Nom de l\'onglet', 'type' => 'texte', 'defaut' => 'La Publicité Mobile'],
            'terr2_surtitre'=> ['label' => 'Légende du chiffre', 'type' => 'texte', 'defaut' => 'Communes atteintes'],
            'terr2_titre'   => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Publicité mobile : la ville devient votre support.'],
            'terr2_texte'   => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Camions, tricycles, motos, taxis, chevalets. Le message va chercher l'audience là où elle est immobile."],
            'terr2_tags'    => ['label' => 'Étiquettes', 'type' => 'liste',
                'defaut' => "Camions publicitaires\nBranding véhicules\nTaxis & motos\nChevalets\nRégie mobile événementielle"],
            'terr2_preuve'  => ['label' => 'Preuve', 'type' => 'zone',
                'defaut' => 'Itinéraires et créneaux définis avec vous, tracés et rapportés après diffusion.'],
            'terr2_image'   => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/mobile-camion.jpg'],
        ]],

        ['titre' => 'Territoire 3 — brand content', 'champs' => [
            'terr3_onglet'  => ['label' => 'Nom de l\'onglet', 'type' => 'texte', 'defaut' => 'Le brand content'],
            'terr3_chiffre' => ['label' => 'Mention mise en avant', 'type' => 'texte', 'defaut' => 'Studio'],
            'terr3_surtitre'=> ['label' => 'Légende', 'type' => 'texte', 'defaut' => 'Production interne'],
            'terr3_titre'   => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Production audiovisuelle : l'image qui porte le message."],
            'terr3_texte'   => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => 'Films institutionnels, spots TV et radio, motion design, contenus de marque.'],
            'terr3_tags'    => ['label' => 'Étiquettes', 'type' => 'liste',
                'defaut' => "Films institutionnels\nSpots TV & audio\nMotion design\nIdentité visuelle\nContenu de marque"],
            'terr3_preuve'  => ['label' => 'Preuve', 'type' => 'zone',
                'defaut' => 'Film institutionnel réalisé pour le Groupe Cofina.'],
            'terr3_image'   => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/campagne-3.jpg'],
        ]],

        ['titre' => 'Territoire 4 — digital', 'champs' => [
            'terr4_onglet'  => ['label' => 'Nom de l\'onglet', 'type' => 'texte', 'defaut' => 'Le digital'],
            'terr4_chiffre' => ['label' => 'Mention mise en avant', 'type' => 'texte', 'defaut' => '24/7'],
            'terr4_surtitre'=> ['label' => 'Légende', 'type' => 'texte', 'defaut' => 'Présence continue'],
            'terr4_titre'   => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Communication digitale : le prolongement naturel du panneau.'],
            'terr4_texte'   => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => 'Social media ads, SEO/SEA, activations interactives, drive-to-store.'],
            'terr4_tags'    => ['label' => 'Étiquettes', 'type' => 'liste',
                'defaut' => "Social media ads\nSEO / SEA\nCampagnes virales\nActivations interactives\nDrive-to-store"],
            'terr4_preuve'  => ['label' => 'Preuve', 'type' => 'zone',
                'defaut' => 'Conception graphique et gestion des réseaux pour SGS / SICTA.'],
            'terr4_image'   => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/campagne-4.jpg'],
        ]],

        ['titre' => 'Territoire 5 — terrain', 'champs' => [
            'terr5_onglet'  => ['label' => 'Nom de l\'onglet', 'type' => 'texte', 'defaut' => 'Le terrain'],
            'terr5_chiffre' => ['label' => 'Mention mise en avant', 'type' => 'texte', 'defaut' => 'Face à face'],
            'terr5_surtitre'=> ['label' => 'Légende', 'type' => 'texte', 'defaut' => 'Le dernier mètre'],
            'terr5_titre'   => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Street marketing : là où la marque devient une rencontre.'],
            'terr5_texte'   => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => 'Street marketing, pop-up stores, roadshows, stands expérientiels.'],
            'terr5_tags'    => ['label' => 'Étiquettes', 'type' => 'liste',
                'defaut' => "Street marketing\nPop-up store\nRoadshow\nStand expérientiel\nArchitecture événementielle"],
            'terr5_preuve'  => ['label' => 'Preuve', 'type' => 'zone',
                'defaut' => 'Brand experience pour Orange · stand expérientiel pour IFG.'],
            'terr5_image'   => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/campagne-5.jpg'],
        ]],

        ['titre' => 'Mission', 'champs' => [
            'mission_texte' => ['label' => 'Paragraphe', 'type' => 'zone',
                'defaut' => 'Nous concevons des stratégies de visibilité qui transforment chaque point de contact avec votre audience en opportunité de croissance.'],
            'mission_fort' => ['label' => 'Phrase mise en avant', 'type' => 'zone',
                'defaut' => 'Notre mission : faire de votre visibilité un véritable levier de performance commerciale.'],
        ]],

        ['titre' => 'Bloc réalisations', 'champs' => [
            'travaux_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Réalisations'],
            'travaux_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Des campagnes qui parlent d'elles-mêmes."],
            'travaux_cta' => ['label' => 'Bouton', 'type' => 'texte', 'defaut' => 'Voir toutes les réalisations'],
        ]],

        ['titre' => 'Bloc réseau', 'champs' => [
            'reseau_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'La preuve'],
            'reseau_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "L'un des plus grands patrimoines d'affichage à Abidjan et en Côte d'Ivoire."],
            'reseau_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Une agence loue l'espace d'un tiers. Nous exploitons le nôtre : +400 panneaux dans 31 communes, de Bouaké à San-Pédro."],
            'reseau_cta' => ['label' => 'Bouton', 'type' => 'texte', 'defaut' => 'Explorer la carte du réseau'],
            'reseau_image' => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/hero-plateau-night.jpg'],
        ]],

        ['titre' => 'Bloc contact', 'champs' => [
            'contact_titre' => ['label' => 'Titre', 'type' => 'texte', 'defaut' => 'Entrons en contact.'],
            'contact_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => 'Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la journée ouvrée.'],
            'contact_cta' => ['label' => 'Bouton', 'type' => 'texte', 'defaut' => 'Recevoir une recommandation média'],
        ]],
    ],
],

/* ═══════════════════════════════════════════════════════════════════
   SERVICES
══════════════════════════════════════════════════════════════════ */
'services' => [
    'titre'   => 'Page Services',
    'resume'  => 'Les 4 pôles, la méthode, les objectifs.',
    'couleur' => 'var(--bleu)',
    'url'     => '/services',
    'groupes' => [

        ['titre' => 'Bandeau', 'champs' => [
            'hero_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Nos expertises'],
            'hero_titre_1' => ['label' => 'Titre — début', 'type' => 'texte',
                'defaut' => "De la visibilité à l'expérience."],
            'hero_titre_2' => ['label' => 'Titre — fin (en rouge)', 'type' => 'texte',
                'defaut' => "De l'expérience à l'impact."],
            'hero_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "CIBLE conçoit et déploie des campagnes qui rendent les marques visibles, présentes et mémorables. Affichage, communication mobile, digital, street marketing, création de contenus et Media Intelligence : nous connectons les annonceurs à leurs audiences, dans la rue comme en ligne."],
            'hero_accroche' => ['label' => 'Accroche', 'type' => 'zone',
                'defaut' => "Un seul partenaire pour imaginer votre stratégie, activer les bons points de contact, déployer votre campagne et en suivre l'exécution."],
            'hero_cta1' => ['label' => 'Bouton principal', 'type' => 'texte', 'defaut' => 'Construire ma campagne'],
            'hero_cta2' => ['label' => 'Bouton secondaire', 'type' => 'texte', 'defaut' => 'Découvrir nos réalisations'],
        ]],

        ['titre' => 'Notre approche', 'champs' => [
            'approche_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Notre approche'],
            'approche_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Créer une rencontre entre votre marque et ses publics.'],
            'approche_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Une campagne performante ne consiste pas seulement à diffuser un message. Elle doit apparaître au bon endroit, au bon moment, dans le bon format et susciter une réaction. Nous combinons patrimoine média, connaissance du terrain, créativité, activation et données pour construire des expériences de marque capables de générer visibilité, engagement et impact."],
            'approche_1_titre' => ['label' => 'Preuve 1 — titre', 'type' => 'texte', 'defaut' => 'Être vu'],
            'approche_1_texte' => ['label' => 'Preuve 1 — texte', 'type' => 'zone',
                'defaut' => 'Positionner votre marque sur des emplacements, des supports et des canaux adaptés à vos audiences.'],
            'approche_2_titre' => ['label' => 'Preuve 2 — titre', 'type' => 'texte', 'defaut' => 'Être vécu'],
            'approche_2_texte' => ['label' => 'Preuve 2 — texte', 'type' => 'zone',
                'defaut' => 'Transformer une prise de parole en interaction grâce au street marketing, aux activations et aux expériences digitales.'],
            'approche_3_titre' => ['label' => 'Preuve 3 — titre', 'type' => 'texte', 'defaut' => 'Être mesuré'],
            'approche_3_texte' => ['label' => 'Preuve 3 — texte', 'type' => 'zone',
                'defaut' => 'Suivre le déploiement de la campagne, documenter son exécution et exploiter les données disponibles pour améliorer les prochaines actions.'],
        ]],

        ['titre' => 'Pôle 1 — Régie publicitaire', 'champs' => [
            'p1_tag'   => ['label' => 'Étiquette', 'type' => 'texte', 'defaut' => 'Pôle 01 · Régie publicitaire & visibilité extérieure'],
            'p1_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'aide' => 'Le texte entre **doubles astérisques** ressort dans la couleur du pôle.',
                'defaut' => "La puissance d'un **réseau national** au service de votre visibilité."],
            'p1_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Le plus grand maillage territorial. Avec **+400 panneaux en exploitation** à Abidjan et à l'intérieur du pays, CIBLE accompagne les annonceurs dans la sélection des emplacements, des formats et des zones les plus pertinents au regard de leurs objectifs, de leurs audiences et de leur budget."],
            'p1_accroche' => ['label' => 'Accroche', 'type' => 'zone',
                'defaut' => "Notre rôle ne se limite pas à mettre un espace à disposition : nous construisons des plans de visibilité cohérents avec les déplacements, les habitudes et les zones de concentration de vos cibles."],
            'p1_dispositifs' => ['label' => 'Dispositifs d\'affichage', 'type' => 'liste',
                'defaut' => "Panneaux classiques\nLumipub (caissons éclairés)\nTrivision (3 visuels en rotation)\nPanoramiques grand format\nÉcrans digitaux\nÉcrans en magasins"],
            'p1_image' => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/regie-lumipub.jpg'],
        ]],

        ['titre' => 'Pôle 2 — Communication mobile', 'champs' => [
            'p2_tag'   => ['label' => 'Étiquette', 'type' => 'texte', 'defaut' => 'Pôle 02 · Communication mobile & présence urbaine'],
            'p2_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'aide' => 'Le texte entre **doubles astérisques** ressort dans la couleur du pôle.',
                'defaut' => "Votre message ne reste pas immobile. **Il va à la rencontre de son audience.**"],
            'p2_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "La communication mobile transforme les rues, les axes de circulation, les marchés, les quartiers et les événements en espaces d'expression pour votre marque. Nous concevons des dispositifs capables d'aller vers les publics, de multiplier les points de contact et d'amplifier la couverture d'une campagne."],
            'p2_accroche' => ['label' => 'Accroche', 'type' => 'zone',
                'defaut' => "Une solution particulièrement adaptée aux lancements, opérations promotionnelles, ouvertures de points de vente, campagnes de proximité et prises de parole événementielles."],
            'p2_image' => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/mobile-camion.jpg'],
        ]],

        ['titre' => 'Pôle 3 — Brand experience', 'champs' => [
            'p3_tag'   => ['label' => 'Étiquette', 'type' => 'texte', 'defaut' => 'Pôle 03 · Brand experience & communication intégrée'],
            'p3_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'aide' => 'Le texte entre **doubles astérisques** ressort dans la couleur du pôle.',
                'defaut' => 'Faire voir votre marque. **Mais surtout, la faire vivre.**'],
            'p3_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Nous créons des expériences capables de rapprocher les marques de leurs publics. Dans la rue, sur un événement, en point de vente ou en ligne, chaque activation est pensée pour susciter l'attention, provoquer l'interaction et laisser une empreinte durable."],
            'p3_image' => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/campagne-5.jpg'],
        ]],

        ['titre' => 'Pôle 4 — Media Intelligence', 'champs' => [
            'p4_tag'   => ['label' => 'Étiquette', 'type' => 'texte', 'defaut' => 'Pôle 04 · Media Intelligence'],
            'p4_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'aide' => 'Le texte entre **doubles astérisques** ressort dans la couleur du pôle.',
                'defaut' => 'Une visibilité **pilotée par la donnée** et renforcée par la preuve.'],
            'p4_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "La Media Intelligence permet de passer d'une logique de diffusion à une logique de pilotage. Nos outils centralisent les informations liées aux emplacements, aux poses, aux périodes de diffusion et au suivi terrain afin d'apporter davantage de transparence, de contrôle et de précision aux annonceurs."],
            'p4_accroche' => ['label' => 'Accroche', 'type' => 'zone',
                'defaut' => "L'objectif : mieux préparer les campagnes, mieux suivre leur exécution et capitaliser sur les données disponibles pour améliorer les décisions média."],
            'p4_image' => ['label' => 'Image', 'type' => 'image', 'defaut' => 'images/cible/pole-1-affichage.jpg'],
        ]],

        ['titre' => 'Campagnes phygitales', 'champs' => [
            'phy_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'De la rue au digital'],
            'phy_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Une même expérience de marque, sur tous les points de contact.'],
            'phy_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Les audiences ne vivent plus dans un seul espace. Elles circulent, consultent, interagissent, partagent et achètent. Nous concevons des campagnes phygitales qui relient l'affichage, le terrain, les réseaux sociaux, les contenus et les points de vente pour créer un parcours de marque cohérent."],
        ]],

        ['titre' => 'Méthode', 'champs' => [
            'work_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Notre méthode'],
            'work_titre_1' => ['label' => 'Titre — début', 'type' => 'texte', 'defaut' => "De l'objectif"],
            'work_titre_2' => ['label' => 'Titre — fin (en jaune)', 'type' => 'texte', 'defaut' => "à l'impact."],
            'work_intro' => ['label' => 'Introduction', 'type' => 'zone',
                'defaut' => 'Une méthode intégrée, un interlocuteur unique et une traçabilité complète à chaque étape de votre campagne.'],
        ]],

        ['titre' => 'Objectifs', 'champs' => [
            'obj_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Vos ambitions, nos dispositifs'],
            'obj_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Une expertise adaptée à chaque objectif de marque.'],
            'obj_liste' => ['label' => 'Objectifs', 'type' => 'liste', 'max' => 2000,
                'defaut' => "Accroître la notoriété d'une marque\nLancer un produit ou un service\nGénérer du trafic vers un point de vente\nToucher une audience dans une zone précise\nCréer une interaction directe avec les consommateurs\nAmplifier une campagne en ligne et sur le terrain\nValoriser une institution ou une entreprise\nDéployer une campagne nationale"],
        ]],

        ['titre' => 'Appel à l\'action', 'champs' => [
            'cta_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Votre prochaine campagne commence ici'],
            'cta_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Construisons une expérience de marque que votre audience remarquera et retiendra."],
            'cta_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Partagez-nous vos objectifs, vos cibles, vos zones prioritaires et votre calendrier. Notre équipe vous proposera une stratégie de visibilité, d'activation et de déploiement adaptée à votre ambition."],
            'cta_bouton1' => ['label' => 'Bouton principal', 'type' => 'texte', 'defaut' => 'Parler de mon projet'],
            'cta_bouton2' => ['label' => 'Bouton secondaire', 'type' => 'texte', 'defaut' => 'Découvrir nos réalisations'],
        ]],
    ],
],

/* ═══════════════════════════════════════════════════════════════════
   RÉSEAU
══════════════════════════════════════════════════════════════════ */
'reseau' => [
    'titre'   => 'Page Parc média',
    'resume'  => 'Couverture, formats, Media Intelligence.',
    'couleur' => 'var(--vert)',
    'url'     => '/reseau',
    'groupes' => [
        ['titre' => 'Bandeau', 'champs' => [
            'hero_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Notre parc publicitaire'],
            'hero_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Votre audience est quelque part. Notre réseau vous permet de l'atteindre."],
            'hero_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "CIBLE exploite un parc de +400 panneaux répartis à Abidjan et dans les principales villes de Côte d'Ivoire. Notre connaissance du terrain nous permet de recommander les zones, les axes et les formats les plus adaptés à vos objectifs de visibilité."],
            'hero_cta' => ['label' => 'Bouton', 'type' => 'texte', 'defaut' => 'Trouver mes emplacements'],
        ]],
        ['titre' => 'Approche média', 'champs' => [
            'app_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => "Plus qu'une carte"],
            'app_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Des emplacements sélectionnés selon vos audiences et vos objectifs.'],
            'app_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Une campagne extérieure performante ne repose pas uniquement sur le nombre de panneaux. Elle dépend de la pertinence des zones, de la visibilité du support, du sens de circulation, du format, de la durée d'exposition et de la cohérence avec les habitudes de votre cible."],
        ]],
        ['titre' => 'Couverture territoriale', 'champs' => [
            'comm_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Couverture territoriale'],
            'comm_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'De la capitale économique aux principaux bassins de consommation.'],
            'comm_abidjan_titre' => ['label' => 'Zone 1 — titre', 'type' => 'texte', 'defaut' => 'Grand Abidjan'],
            'comm_abidjan_sous'  => ['label' => 'Zone 1 — sous-titre', 'type' => 'zone',
                'defaut' => 'Principaux axes, communes et zones commerciales du Grand Abidjan.'],
            'comm_abidjan_liste' => ['label' => 'Zone 1 — communes', 'type' => 'liste', 'max' => 1200,
                'defaut' => "Plateau\nCocody\nYopougon\nAbobo\nMarcory\nTreichville\nKoumassi\nPort-Bouët\nAttécoubé\nAdjamé\nBingerville\nSongon\nAnyama"],
            'comm_abidjan_note'  => ['label' => 'Zone 1 — note', 'type' => 'texte',
                'defaut' => "Ainsi que les principales zones de la Riviera et d'Angré."],
            'comm_int_titre' => ['label' => 'Zone 2 — titre', 'type' => 'texte', 'defaut' => 'Intérieur du pays'],
            'comm_int_sous'  => ['label' => 'Zone 2 — sous-titre', 'type' => 'zone',
                'defaut' => "17 villes stratégiques de Côte d'Ivoire."],
            'comm_int_liste' => ['label' => 'Zone 2 — villes', 'type' => 'liste', 'max' => 1200,
                'defaut' => "Bouaké\nSan-Pédro\nYamoussoukro\nKorhogo\nMan\nDaloa\nGagnoa\nDivo\nBondoukou\nOdienné\nSéguéla\nFerkessédougou\nDabou\nGrand-Bassam\nAboisso\nSoubré"],
            'comm_int_note'  => ['label' => 'Zone 2 — note', 'type' => 'texte',
                'defaut' => 'Et autres zones selon disponibilité.'],
        ]],
        ['titre' => 'Appel à l\'action', 'champs' => [
            'cta_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Votre plan média commence ici'],
            'cta_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Identifions les emplacements qui donneront le plus de force à votre campagne.'],
            'cta_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Indiquez-nous vos objectifs, vos audiences, vos zones prioritaires, votre période et votre budget. Notre équipe vous préparera une sélection personnalisée de formats et d'emplacements."],
            'cta_bouton' => ['label' => 'Bouton', 'type' => 'texte', 'defaut' => 'Recevoir une recommandation média'],
        ]],
    ],
],

/* ═══════════════════════════════════════════════════════════════════
   QUI SOMMES-NOUS
══════════════════════════════════════════════════════════════════ */
'qui' => [
    'titre'   => 'Page Qui sommes-nous',
    'resume'  => 'Récit, chiffres, distinctions.',
    'couleur' => 'var(--violet)',
    'url'     => '/qui-sommes-nous',
    'groupes' => [
        ['titre' => 'Bandeau', 'champs' => [
            'hero_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Depuis 1994'],
            'hero_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Plus de trente ans à rendre les marques visibles, fortes et mémorables.'],
            'hero_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Née dans l'affichage publicitaire, CIBLE est devenue un acteur majeur de la communication en Côte d'Ivoire. Nous conjuguons aujourd'hui expertise terrain, créativité et intelligence média pour transformer la visibilité des marques en impact durable."],
        ]],
        ['titre' => 'Récit', 'champs' => [
            'recit_1_titre' => ['label' => 'Étape 1 — titre', 'type' => 'texte', 'defaut' => "Notre origine — l'expertise de la visibilité"],
            'recit_1_texte' => ['label' => 'Étape 1 — texte', 'type' => 'zone',
                'defaut' => "CIBLE est née en 1994 avec une conviction forte : une marque ne peut grandir que si elle est vue. Grâce à notre maîtrise de l'affichage publicitaire et à notre connaissance du territoire ivoirien, nous avons progressivement construit un réseau puissant et une expertise reconnue de la communication extérieure et du digital."],
            'recit_2_titre' => ['label' => 'Étape 2 — titre', 'type' => 'texte', 'defaut' => "Notre évolution — de l'affichage à la communication intégrée"],
            'recit_2_texte' => ['label' => 'Étape 2 — texte', 'type' => 'zone',
                'defaut' => "Les audiences, les usages et les attentes des annonceurs ont évolué. CIBLE aussi. À notre expertise historique de l'affichage, nous avons ajouté la stratégie, la création, le digital, la production audiovisuelle, la communication mobile et les activations terrain afin d'accompagner les marques sur l'ensemble de leurs points de contact."],
            'recit_3_titre' => ['label' => 'Étape 3 — titre', 'type' => 'texte', 'defaut' => 'Notre force — le terrain, la créativité et la donnée'],
            'recit_3_texte' => ['label' => 'Étape 3 — texte', 'type' => 'zone',
                'defaut' => "Trente ans de connaissance du terrain ivoirien fusionnés avec une approche moderne. La seule régie à posséder son réseau et l'outil qui le pilote."],
            'recit_4_titre' => ['label' => 'Étape 4 — titre', 'type' => 'texte', 'defaut' => 'Notre engagement — apporter des preuves, pas seulement des promesses'],
            'recit_4_texte' => ['label' => 'Étape 4 — texte', 'type' => 'zone',
                'defaut' => "Chaque campagne fait l'objet d'un suivi rigoureux : planification des poses, contrôle des emplacements, photos horodatées et géolocalisées, suivi de diffusion et reporting. Nos clients disposent ainsi d'une visibilité claire sur le déploiement réel de leurs campagnes."],
        ]],
        ['titre' => 'Chiffres et distinctions', 'champs' => [
            'stats_surtitre' => ['label' => 'Sur-titre des chiffres', 'type' => 'texte', 'defaut' => 'En chiffres'],
            'stats_titre' => ['label' => 'Titre des chiffres', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Trente ans concentrés en quatre nombres.'],
            'dist_surtitre' => ['label' => 'Sur-titre des distinctions', 'type' => 'texte', 'defaut' => 'Reconnaissances officielles'],
            'dist_titre' => ['label' => 'Titre des distinctions', 'type' => 'zone', 'max' => 400,
                'defaut' => "Trois distinctions de l'État ivoirien."],
        ]],
        ['titre' => 'Appel à l\'action', 'champs' => [
            'cta_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Construisons ensemble la prochaine campagne qui fera grandir votre marque.'],
            'cta_bouton1' => ['label' => 'Bouton principal', 'type' => 'texte', 'defaut' => 'Parler de mon projet'],
            'cta_bouton2' => ['label' => 'Bouton secondaire', 'type' => 'texte', 'defaut' => 'Découvrir nos réalisations'],
        ]],
    ],
],

/* ═══════════════════════════════════════════════════════════════════
   RÉFÉRENCES
══════════════════════════════════════════════════════════════════ */
'references' => [
    'titre'   => 'Page Réalisations',
    'resume'  => 'Bandeau, introduction, appel à l\'action.',
    'couleur' => 'var(--jaune)',
    'url'     => '/references',
    'groupes' => [
        ['titre' => 'Bandeau', 'champs' => [
            'hero_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Nos réalisations'],
            'hero_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Des campagnes visibles. Des expériences mémorables. Des marques renforcées.'],
            'hero_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Plus de trente ans à accompagner les entreprises, institutions et grandes marques dans la conception et le déploiement de campagnes qui associent visibilité, créativité, expérience de marque et impact terrain."],
        ]],
        ['titre' => 'Introduction', 'champs' => [
            'intro_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Notre savoir-faire en action'],
            'intro_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "De l'affichage à l'expérience de marque."],
            'intro_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Nos réalisations couvrent l'ensemble de la chaîne de valeur : stratégie média, affichage, activation terrain, street marketing, production audiovisuelle, création digitale, architecture événementielle et pilotage de campagne. Chaque projet est conçu pour rapprocher la marque de ses audiences et atteindre un objectif concret."],
        ]],
        ['titre' => 'Logos clients', 'champs' => [
            'cli_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Ils nous ont fait confiance'],
            'cli_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Des marques, institutions et organisations accompagnées à travers la Côte d'Ivoire."],
            'cli_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "De la visibilité extérieure aux expériences de marque, nous collaborons avec des acteurs issus de secteurs variés pour concevoir des campagnes adaptées à leurs enjeux."],
        ]],
        ['titre' => 'Appel à l\'action', 'champs' => [
            'cta_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Et votre prochain projet ?'],
            'cta_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Faisons de votre prochaine campagne une réalisation qui compte.'],
            'cta_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Partagez-nous votre objectif, votre audience et votre ambition. Nous imaginerons le dispositif de visibilité, d'activation et de communication le plus pertinent pour votre marque."],
            'cta_bouton1' => ['label' => 'Bouton principal', 'type' => 'texte', 'defaut' => 'Parler de mon projet'],
            'cta_bouton2' => ['label' => 'Bouton secondaire', 'type' => 'texte', 'defaut' => 'Découvrir nos services'],
        ]],
    ],
],

/* ═══════════════════════════════════════════════════════════════════
   CONTACT
══════════════════════════════════════════════════════════════════ */
'contact' => [
    'titre'   => 'Page Contact',
    'resume'  => 'Bandeau, bénéfices, réassurance.',
    'couleur' => 'var(--rouge)',
    'url'     => '/contact',
    'groupes' => [
        ['titre' => 'Bandeau', 'champs' => [
            'hero_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Parlons de votre projet'],
            'hero_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => "Donnons à votre marque la visibilité qu'elle mérite."],
            'hero_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Que vous souhaitiez lancer une campagne d'affichage, créer une expérience de marque, renforcer votre présence digitale ou construire une stratégie de communication complète, nos équipes vous accompagnent dans la conception du dispositif le plus adapté à vos objectifs."],
            'hero_accroche' => ['label' => 'Accroche', 'type' => 'zone',
                'defaut' => 'Nous analysons votre besoin avant de vous proposer une recommandation média personnalisée.'],
            'benefices' => ['label' => 'Ce que vous obtenez', 'type' => 'liste', 'max' => 1200,
                'defaut' => "Une recommandation adaptée à votre objectif\nLes formats et emplacements les plus pertinents\nUne proposition adaptée à votre budget\nUn interlocuteur dédié\nUne réponse sous 24 h ouvrées"],
        ]],
        ['titre' => 'Formulaire', 'champs' => [
            'form_titre' => ['label' => 'Titre du formulaire', 'type' => 'texte', 'defaut' => 'Recevoir une recommandation média'],
            'form_sous'  => ['label' => 'Sous-titre', 'type' => 'zone',
                'defaut' => 'Décrivez votre projet. Nos équipes reviendront vers vous avec une proposition personnalisée et les solutions les plus adaptées à vos objectifs.'],
            'form_bouton'=> ['label' => 'Bouton d\'envoi', 'type' => 'texte', 'defaut' => 'Recevoir ma recommandation média'],
            'form_succes'=> ['label' => 'Message de confirmation', 'type' => 'zone',
                'defaut' => "Merci pour votre confiance. Un membre de notre équipe analysera votre projet et vous contactera dans les 24 heures ouvrées afin d'échanger sur vos objectifs et de vous proposer la solution la plus adaptée."],
        ]],
        ['titre' => 'Réassurance', 'champs' => [
            'reass_surtitre' => ['label' => 'Sur-titre', 'type' => 'texte', 'defaut' => 'Notre engagement'],
            'reass_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Chaque demande est étudiée avec attention.'],
            'reass_texte' => ['label' => 'Texte', 'type' => 'zone',
                'defaut' => "Nous ne transmettons pas de devis standardisés. Nous analysons vos objectifs, vos audiences, votre zone d'intervention et votre budget afin de vous proposer une stratégie de visibilité réellement adaptée."],
            'reass_liste' => ['label' => 'Engagements', 'type' => 'liste', 'max' => 1200,
                'defaut' => "Réponse sous 24 heures ouvrées\nAucun engagement avant validation de votre projet\nÉchanges confidentiels\nUn interlocuteur unique jusqu'au lancement de votre campagne"],
        ]],
        ['titre' => 'Pourquoi CIBLE', 'champs' => [
            'pourquoi_titre' => ['label' => 'Titre', 'type' => 'zone', 'max' => 400,
                'defaut' => 'Pourquoi les annonceurs choisissent CIBLE ?'],
            'pourquoi_liste' => ['label' => 'Arguments', 'type' => 'liste', 'max' => 2000,
                'defaut' => "+400 panneaux répartis sur le territoire ivoirien\n31 communes et villes couvertes\nPlus de 30 ans d'expertise\nUne offre intégrée : affichage, digital, street marketing, audiovisuel et brand experience\nUn pilotage des campagnes par la Media Intelligence\nDes preuves terrain avec photos horodatées et géolocalisées\nUn interlocuteur unique de la stratégie au reporting"],
        ]],
    ],
],

];
