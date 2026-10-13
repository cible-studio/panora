/*!
 * CIBLE — données du site (source unique). Vous visez juste.
 * Textes : « SITE INTERNET CIBLE_TEXTES » (validé CIBLE), maquette V2 et textes des deux
 * expertises fournis par CIBLE (octobre 2026).
 * Règles : « +400 panneaux » sans répartition par zone ; aucun prix ; aucune donnée personnelle.
 */
window.CIBLE = {
  slogan: 'Vous visez juste',

  coordonnees: {
    mobile: { affiche: '+225 07 98 49 66 74', court: '07 98 49 66 74', tel: '+2250798496674', libelle: 'Mobile · WhatsApp' },
    standard: { affiche: '+225 27 22 20 80 08', court: '27 22 20 80 08', tel: '+2252722208008', libelle: 'Standard' },
    whatsapp: 'https://wa.me/2250798496674',
    commercial: 'commercial@cible-ci.com',
    secretariat: 'secretariat@cible-ci.com',
    adresse: ['Rue des Ambassadeurs', 'Riviera M’Badon · 10 BP 1029 Abidjan 10', 'Côte d’Ivoire'],
    horaires: 'Du lundi au vendredi, 8h – 17h30',
    reponse: 'Réponse sous 24 heures ouvrées',
    site: 'www.cible-ci.com'
  },

  chiffres: [
    { valeur: 400, prefixe: '+', libelle: 'Panneaux en exploitation' },
    { valeur: 31, libelle: 'Communes et villes couvertes' },
    { valeur: 1994, libelle: 'Depuis', statique: true },
    { valeur: 3, libelle: 'Distinctions', pad: true }
  ],

  distinctions: [
    { an: '2016', titre: '2ᵉ prix du meilleur publicitaire', texte: 'Distinction professionnelle du secteur de la publicité ivoirienne.' },
    { an: '2019', titre: 'Chevalier de l’Ordre du Mérite de la Communication', texte: 'Reconnaissance de la contribution à la structuration du métier en Côte d’Ivoire.' },
    { an: '2020', titre: 'Officier de l’Ordre du Mérite National', texte: 'Distinction républicaine pour services rendus au pays.' }
  ],

  /* Les quatre pôles d'expertise (document « SITE INTERNET CIBLE_TEXTES ») */
  poles: [
    { id: 'ooh', n: '01', nom: 'Régie publicitaire', etiquette: 'Régie publicitaire & visibilité extérieure', couleur: 'var(--rouge)', page: 'affichage.html',
      titre: 'Donner de la visibilité aux marques dans l’espace public.',
      court: 'L’espace public est le seul média qu’on ne peut pas zapper.',
      prestations: ['Affichage grand format et mobilier urbain', 'DOOH', 'Transports et gares', 'Dispositifs spéciaux et street marketing', 'Création des visuels'],
      visuel: 'assets/img/rendus/panoramiques.webp', rendu: true, appel: 'Lancez votre campagne' },
    { id: 'mobile', n: '02', nom: 'Communication mobile', etiquette: 'Communication mobile & présence urbaine', couleur: 'var(--rouge)', page: 'communication-mobile.html',
      titre: 'Votre message ne reste pas immobile. Il va à la rencontre de son audience.',
      court: 'Camions, motos, véhicules habillés et roadshows : votre marque circule là où vit votre audience.',
      prestations: ['Camions publicitaires', 'Motos publicitaires', 'Branding véhicules', 'Branding taxis & cars', 'Chevalets et dispositifs de proximité', 'Roadshows et caravanes'],
      visuel: 'assets/img/rendus/camions.webp', rendu: true, appel: 'Planifiez votre tournée' },
    { id: 'experience', n: '03', nom: 'Brand experience', etiquette: 'Brand experience & communication intégrée', couleur: 'var(--rouge)', page: 'brand-experience.html',
      titre: 'Faire voir votre marque. Mais surtout, la faire vivre.',
      court: 'Stratégie, création, digital, activations et événementiel, dans une même équipe.',
      prestations: ['Stratégie de communication', 'Création graphique', 'Digital & réseaux sociaux', 'Street marketing', 'Production audiovisuelle', 'Architecture événementielle'],
      visuel: 'assets/img/photos/post-regie.webp', rendu: false, appel: 'Parlons de votre marque' },
    { id: 'data', n: '04', nom: 'Media Intelligence', etiquette: 'Media Intelligence & pilotage de campagne', couleur: 'var(--rouge)', page: 'media-intelligence.html',
      titre: 'Une visibilité pilotée par la donnée et renforcée par la preuve.',
      court: 'Chaque pose photographiée, datée et localisée, et un bilan clair de votre campagne.',
      prestations: ['Inventaire média digitalisé', 'Sélection des emplacements', 'Suivi terrain photo', 'Reporting de campagne', 'Espace client', 'Analyse et optimisation'],
      visuel: 'assets/img/photos/affichage.webp', rendu: false, appel: 'Pilotez votre campagne' }
  ],

  /* Plan du site, pour la navigation de fin de page (« Poursuivre la visite ») */
  pages: [
    { id: 'qui', titre: 'Qui sommes-nous', texte: 'Plus de trente ans à rendre les marques visibles, fortes et mémorables.', lien: 'qui-sommes-nous.html', visuel: 'assets/img/photos/rue.webp' },
    { id: 'ooh', titre: 'Régie publicitaire', texte: 'Donner de la visibilité aux marques dans l’espace public.', lien: 'affichage.html', visuel: 'assets/img/rendus/panoramiques.webp', rendu: true },
    { id: 'mobile', titre: 'Communication mobile', texte: 'Votre message va à la rencontre de son audience.', lien: 'communication-mobile.html', visuel: 'assets/img/rendus/camions.webp', rendu: true },
    { id: 'experience', titre: 'Brand experience', texte: 'Faire voir votre marque, et surtout la faire vivre.', lien: 'brand-experience.html', visuel: 'assets/img/photos/post-regie.webp' },
    { id: 'data', titre: 'Media Intelligence', texte: 'Une visibilité pilotée par la donnée et renforcée par la preuve.', lien: 'media-intelligence.html', visuel: 'assets/img/photos/affichage.webp' },
    { id: 'reseau', titre: 'Notre réseau', texte: '+400 panneaux dans 31 communes et villes, sur la carte.', lien: 'reseau.html', visuel: 'assets/img/photos/affichage.webp' },
    { id: 'realisations', titre: 'Nos réalisations', texte: 'Des campagnes visibles, des marques renforcées.', lien: 'travaux.html', visuel: 'assets/img/photos/campagne-6.webp' },
    { id: 'contact', titre: 'Contact', texte: 'Une recommandation média sous 24 heures ouvrées.', lien: 'contact.html', visuel: 'assets/img/photos/campagne-5.webp' }
  ],

  /* Formats d'affichage officiels */
  formats: [
    { id: 'petit', nom: 'Petit format', dim: '2×1 m à 5×2 m', surface: '2 à 10 m²', w: 4, h: 2 },
    { id: 'classique', nom: 'Classique', dim: '4×3 m', surface: '12 m²', w: 4, h: 3 },
    { id: 'grande-dimension', nom: 'Grande dimension', dim: '6×3 m à 6×4 m', surface: '18 à 24 m²', w: 6, h: 3.5 },
    { id: 'grand-format', nom: 'Grand format', dim: '8×4 m à 6×6 m', surface: '32 à 36 m²', w: 8, h: 4 },
    { id: 'tres-grand', nom: 'Très grand format', dim: '10×5 m à 9×6 m', surface: '50 à 54 m²', w: 10, h: 5 },
    { id: 'panoramique', nom: 'Panoramique', dim: '14×5 m', surface: '70 m²', w: 14, h: 5 }
  ],

  /* Dispositifs : affichage (pôle 01) et communication mobile (pôle 02), chacun avec son île 3D */
  dispositifs: [
    { id: 'panneaux-classiques', pole: 'ooh', n: '01', nom: 'Panneaux classiques', cat: 'Formats classiques', formats: true,
      court: 'Le socle du réseau. Présence continue sur les axes à fort trafic, en 4×3 et grands formats.',
      texte: 'Du mobilier urbain de proximité aux dispositifs panoramiques, nous adaptons le format à votre message, à votre audience et à votre ambition de visibilité.',
      tags: ['Axes à fort trafic', '6 formats', 'Pige photo'], alt: 'Panneau d’affichage 4×3 au bord d’un boulevard, sur une île flottante' },
    { id: 'lumipub', pole: 'ooh', n: '02', nom: 'Lumipub', cat: 'Caissons éclairés',
      court: 'Caissons éclairés : votre message reste lisible après la tombée de la nuit, quand le trafic est encore dense.',
      tags: ['Rétroéclairé', 'Lisible de nuit', 'Terre-pleins'], alt: 'Caissons lumineux Lumipub sur un terre-plein central' },
    { id: 'trivision', pole: 'ooh', n: '03', nom: 'Trivision', cat: '3 visuels en rotation',
      court: 'Trois visuels en rotation sur une même face. Trois messages, ou trois annonceurs, un seul emplacement.',
      tags: ['3 messages', 'Rotation', 'Un emplacement'], alt: 'Panneau trivision à prismes rotatifs' },
    { id: 'panoramiques', pole: 'ooh', n: '04', nom: 'Panoramiques grand format', cat: 'Très grands formats',
      court: 'Les très grands formats, sur les axes d’entrée et de sortie d’Abidjan. Pour les prises de parole fortes.',
      dim: '14×5 m · 70 m²', tags: ['Axes d’entrée et de sortie', '70 m²', 'Prise de parole forte'], alt: 'Panneau panoramique 14×5 au bord d’une voie rapide' },
    { id: 'ecrans-digitaux', pole: 'ooh', n: '05', nom: 'Écrans digitaux', cat: 'DOOH',
      court: 'Diffusion animée et programmable. Idéal pour une campagne à durée courte ou à message variable.',
      tags: ['Animé', 'Programmable', 'Message variable'], alt: 'Écran digital LED à un carrefour' },
    { id: 'ecrans-magasins', pole: 'ooh', n: '06', nom: 'Affichage en magasin', cat: 'Écrans en magasins',
      court: 'Au dernier mètre, là où la décision d’achat se prend réellement.',
      tags: ['Dernier mètre', 'Point de vente', 'Écrans'], alt: 'Boutique avec écrans et totem digital' },
    { id: 'camions', pole: 'mobile', n: '01', nom: 'Camions publicitaires', cat: 'Affichage mobile',
      court: 'Des surfaces d’affichage mobiles à fort impact pour accompagner un lancement, annoncer une opération ou occuper plusieurs zones au cours d’une même campagne.',
      tags: ['Fort impact', 'Plusieurs zones', 'Lancements'], alt: 'Camion publicitaire avec caisson d’affichage, sur une île flottante' },
    { id: 'motos', pole: 'mobile', n: '02', nom: 'Motos publicitaires', cat: 'Proximité',
      court: 'Des dispositifs agiles pour circuler dans les zones denses, toucher les quartiers de proximité et accéder aux espaces difficiles à couvrir avec de grands véhicules.',
      tags: ['Agiles', 'Zones denses', 'Quartiers'], alt: 'Motos publicitaires avec coffres aux couleurs d’une campagne' },
    { id: 'branding-vehicules', pole: 'mobile', n: '03', nom: 'Branding véhicules', cat: 'Habillage de flotte',
      court: 'Habillage partiel ou intégral de véhicules d’entreprise, de flottes commerciales ou de véhicules spécifiquement dédiés à une campagne.',
      tags: ['Partiel ou intégral', 'Flottes', 'Durable'], alt: 'Voiture et camionnette habillées aux couleurs d’une marque' },
    { id: 'taxis-cars', pole: 'mobile', n: '04', nom: 'Branding taxis & cars', cat: 'Flux urbains',
      court: 'Une présence répétée dans les flux urbains pour renforcer la mémorisation et augmenter la fréquence d’exposition au message.',
      tags: ['Répétition', 'Mémorisation', 'Fréquence'], alt: 'Taxis et autocar habillés aux couleurs d’une marque' },
    { id: 'chevalets', pole: 'mobile', n: '05', nom: 'Chevalets et dispositifs de proximité', cat: 'Supports tactiques',
      court: 'Des supports tactiques déployés autour des marchés, points de vente, événements et lieux de forte affluence.',
      tags: ['Marchés', 'Points de vente', 'Événements'], alt: 'Chevalets publicitaires sur une place animée' },
    { id: 'roadshows', pole: 'mobile', n: '06', nom: 'Roadshows et caravanes', cat: 'Campagnes itinérantes',
      court: 'Des campagnes itinérantes combinant visibilité mobile, animation, prise de parole et interaction directe avec les publics.',
      tags: ['Itinérant', 'Animation', 'Interaction'], alt: 'Camion podium avec scène dépliée et écran géant' }
  ],

  /* Réalisations (titres et textes : document validé CIBLE) */
  realisations: [
    { slug: 'orange', n: '01', nom: 'Orange', cat: 'Brand experience & activation terrain', titre: 'Faire vivre la marque au plus près de ses publics.',
      texte: 'Conception et déploiement d’une expérience de marque destinée à renforcer la proximité avec les audiences, générer de l’interaction et amplifier la visibilité de l’opération.',
      services: ['Concept créatif', 'Activation terrain', 'Production', 'Coordination opérationnelle'], filtres: ['experience', 'street'], couleur: 'var(--violet)',
      image: 'assets/img/photos/libre-foule-festive.webp', credit: 'Unsplash · Andrey K' },
    { slug: 'cofina', n: '02', nom: 'Groupe Cofina', cat: 'Production audiovisuelle', titre: 'Traduire une vision institutionnelle en récit de marque.',
      texte: 'Réalisation d’un film institutionnel conçu pour valoriser l’identité, la mission et l’impact du groupe auprès de ses parties prenantes.',
      services: ['Conseil éditorial', 'Scénarisation', 'Tournage', 'Postproduction'], filtres: ['audiovisuel'], couleur: 'var(--vert)',
      image: 'assets/img/photos/libre-studio-lumiere.webp', credit: 'Unsplash · Madeline Liu' },
    { slug: 'snedai', n: '03', nom: 'SNEDAI', cat: 'Stratégie de communication intégrée', titre: 'Construire une présence cohérente sur plusieurs points de contact.',
      texte: 'Conception d’une stratégie associant communication institutionnelle, supports de visibilité et contenus afin de renforcer la lisibilité et la portée de la marque.',
      services: ['Stratégie', 'Création', 'Déploiement multicanal', 'Suivi'], filtres: ['affichage', 'digital'], couleur: 'var(--bleu)',
      image: 'assets/img/photos/libre-rue-afrique.webp', credit: 'Unsplash · Benjamin Chambon' },
    { slug: 'sgs-sicta', n: '04', nom: 'SGS · SICTA', cat: 'Création digitale & réseaux sociaux', titre: 'Prolonger l’expérience de marque en ligne.',
      texte: 'Conception de contenus visuels et animation des prises de parole digitales afin de renforcer la présence, la cohérence et l’engagement de la marque sur les réseaux sociaux.',
      services: ['Direction artistique', 'Création de contenus', 'Réseaux sociaux', 'Suivi éditorial'], filtres: ['digital'], couleur: 'var(--jaune)',
      image: 'assets/img/photos/libre-mobile.webp', credit: 'Unsplash · Howard Bouchevereau' },
    { slug: 'ifg', n: '05', nom: 'IFG', cat: 'Stand expérientiel', titre: 'Transformer un espace en expérience de marque.',
      texte: 'Conception et réalisation d’un stand pensé pour attirer les visiteurs, valoriser l’offre et favoriser les échanges avec les publics présents.',
      services: ['Concept', 'Design d’espace', 'Production', 'Installation'], filtres: ['experience', 'design'], couleur: 'var(--rouge)',
      image: 'assets/img/photos/libre-stand.webp', credit: 'Unsplash · Marc Rentschler' },
    { slug: 'sigfu', n: '06', nom: 'SIGFU', cat: 'Design & architecture événementielle', titre: 'Donner une forme visible et cohérente à une identité institutionnelle.',
      texte: 'Conception d’un dispositif architectural et visuel destiné à valoriser la présence de l’organisation et à renforcer la qualité de l’expérience proposée aux visiteurs.',
      services: ['Direction artistique', 'Design', 'Architecture', 'Production'], filtres: ['design'], couleur: 'var(--violet)',
      image: 'assets/img/photos/libre-architecture.webp', credit: 'Unsplash · Mike Hindle' }
  ],
  filtresRealisations: [
    { id: 'tout', libelle: 'Toutes les réalisations' },
    { id: 'affichage', libelle: 'Affichage & régie' },
    { id: 'experience', libelle: 'Brand experience' },
    { id: 'street', libelle: 'Street marketing' },
    { id: 'digital', libelle: 'Digital & contenus' },
    { id: 'audiovisuel', libelle: 'Production audiovisuelle' },
    { id: 'design', libelle: 'Design & événementiel' }
  ],

  clients: [
    { img: 'danone.webp', nom: 'Danone' }, { img: 'moov-africa.webp', nom: 'Moov Africa' }, { img: 'sipra.webp', nom: 'SIPRA' },
    { img: 'bgfibank.webp', nom: 'BGFIBank' }, { img: 'banque-atlantique.webp', nom: 'Banque Atlantique' }, { img: 'rimco-motors.webp', nom: 'Rimco Motors' },
    { img: 'bni.webp', nom: 'BNI' }, { img: 'solibra.webp', nom: 'Solibra' }, { img: 'gna-ci.webp', nom: 'GNA-CI Assurances' },
    { img: 'uniwax.webp', nom: 'Uniwax' }, { img: 'sotaci.webp', nom: 'SOTACI' }, { img: 'bem.webp', nom: 'BeM Management School' }
  ],

  /* 31 communes et villes (coordonnées : reseau-map.json ; le nombre de faces n'est jamais publié) */
  communes: [
    ['Abobo', 'abidjan', 5.4192, -4.0332], ['Adjamé', 'abidjan', 5.3600, -4.0208], ['Angré', 'abidjan', 5.3900, -3.9900],
    ['Attécoubé', 'abidjan', 5.3300, -4.0400], ['Bingerville', 'abidjan', 5.3548, -3.8899], ['Cocody', 'abidjan', 5.3600, -3.9900],
    ['Koumassi', 'abidjan', 5.2900, -3.9500], ['Marcory', 'abidjan', 5.3000, -3.9900], ['Plateau', 'abidjan', 5.3200, -4.0200],
    ['Port-Bouët', 'abidjan', 5.2500, -3.9300], ['Riviera', 'abidjan', 5.3500, -3.9800], ['Songon', 'abidjan', 5.3100, -4.2200],
    ['Treichville', 'abidjan', 5.2900, -4.0100], ['Yopougon', 'abidjan', 5.3400, -4.0900], ['Anyama', 'abidjan', 5.4930, -4.0510],
    ['Aboisso', 'interieur', 5.4670, -3.2000, 'Sud-Comoé'], ['Bondoukou', 'interieur', 8.0400, -2.8000, 'Gontougo'], ['Bouaké', 'interieur', 7.6900, -5.0300, 'Gbêkê'],
    ['Dabou', 'interieur', 5.3200, -4.3800, 'Grands Ponts'], ['Daloa', 'interieur', 6.8800, -6.4500, 'Haut-Sassandra'], ['Divo', 'interieur', 5.8390, -5.3570, 'Lôh-Djiboua'],
    ['Ferkessédougou', 'interieur', 9.5900, -5.2000, 'Tchologo'], ['Gagnoa', 'interieur', 6.1300, -5.9400, 'Gôh'], ['Grand-Bassam', 'interieur', 5.2100, -3.7400, 'Sud-Comoé'],
    ['Korhogo', 'interieur', 9.4500, -5.6300, 'Poro'], ['Man', 'interieur', 7.4100, -7.5500, 'Tonkpi'], ['Odienné', 'interieur', 9.5000, -7.5700, 'Kabadougou'],
    ['San-Pédro', 'interieur', 4.7480, -6.6360, 'San-Pédro'], ['Séguéla', 'interieur', 7.9600, -6.6700, 'Worodougou'], ['Soubré', 'interieur', 5.7800, -6.6000, 'Nawa'],
    ['Yamoussoukro', 'interieur', 6.8200, -5.2800, 'Bélier']
  ],

  /* Options du « mini-brief » (proposées par CIBLE) */
  brief: {
    objectifs: ['Développer la notoriété de ma marque', 'Lancer un nouveau produit ou service', 'Générer du trafic vers un point de vente', 'Augmenter mes ventes', 'Communiquer sur un événement', 'Valoriser mon entreprise', 'Recruter', 'Communication institutionnelle', 'Autre'],
    cibles: ['Grand public', 'Professionnels (B2B)', 'Jeunes', 'Femmes', 'Entreprises', 'Institutions', 'Décideurs', 'Étudiants', 'Autre'],
    zones: ['Plateau', 'Cocody', 'Marcory', 'Yopougon', 'Abobo', 'Grand Abidjan', 'Intérieur du pays', 'National', 'À définir avec vos équipes'],
    periodes: ['Dès que possible', 'Dans moins d’un mois', 'Dans 1 à 3 mois', 'Plus tard'],
    /* Services proposés, regroupés par expertise (les deux seules proposées à ce jour) */
    servicesOOH: ['Affichage grand format et mobilier urbain', 'DOOH (écrans digitaux)', 'Transports et gares', 'Dispositifs spéciaux et street marketing', 'Création des visuels'],
    servicesMobile: ['Camions publicitaires', 'Motos publicitaires', 'Branding véhicules, taxis et cars', 'Chevalets et dispositifs de proximité', 'Roadshows et caravanes'],
    servicesExperience: ['Stratégie de communication et de marque', 'Création graphique et contenus', 'Digital et réseaux sociaux', 'Street marketing et activations', 'Production audiovisuelle', 'Architecture événementielle', 'Relations presse et influence'],
    servicesData: ['Sélection des emplacements', 'Suivi terrain et preuves de pose', 'Reporting de campagne'],
    servicesAutres: ['Je souhaite être conseillé'],
    budgets: ['Moins de 500 000 FCFA', '500 000 à 2 M FCFA', '2 à 5 M FCFA', '5 à 10 M FCFA', 'Plus de 10 M FCFA', 'Je ne sais pas encore'],
    provenances: ['Google', 'LinkedIn', 'Facebook', 'Instagram', 'Recommandation', 'Client', 'Événement', 'Panneau CIBLE', 'Autre'],
    recevrez: ['Une recommandation média adaptée à vos objectifs', 'Une sélection des meilleurs emplacements', 'Une stratégie de visibilité personnalisée', 'Des propositions d’expériences de marque adaptées à vos audiences', 'Un calendrier de déploiement', 'Une estimation budgétaire', 'Des recommandations pour maximiser l’impact de votre campagne'],
    pourquoi: ['+400 panneaux répartis sur le territoire ivoirien', '31 communes et villes couvertes', 'Plus de 30 ans d’expertise', 'Une offre intégrée : régie, communication mobile, expérience de marque et Media Intelligence', 'Un suivi de chaque campagne, de la pose au bilan', 'Des preuves terrain avec photos horodatées et géolocalisées', 'Un interlocuteur unique de la stratégie au reporting']
  }
};
