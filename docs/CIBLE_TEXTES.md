# Textes du site vitrine CIBLE — à corriger

> **Comment utiliser ce document :**
> 1. Chaque texte a un identifiant unique entre crochets `[EX.SECTION.CHAMP]`
> 2. Modifie directement le texte à droite du `→`
> 3. **Ne touche pas aux identifiants entre crochets** (ils me permettent de retrouver l'emplacement exact)
> 4. Renvoie-moi le fichier corrigé, j'appliquerai les changements
> 5. Pour un texte à supprimer : mets `[SUPPRIMER]` à la place
> 6. Pour un nouveau bloc à ajouter : dis-le en commentaire libre à la fin
>
> **Fichiers sources concernés :**
> - `resources/views/public/cible/_layout.blade.php` (header/footer partout)
> - `resources/views/public/cible/home.blade.php`
> - `resources/views/public/cible/qui-sommes-nous.blade.php`
> - `resources/views/public/cible/services.blade.php`
> - `resources/views/public/cible/reseau.blade.php`
> - `resources/views/public/cible/references.blade.php`
> - `resources/views/public/cible/contact.blade.php`

---

## 🧭 LAYOUT (header + footer — sur toutes les pages)

### Navigation principale (header)

| ID | Texte actuel |
|---|---|
| `[NAV.QUI]` | → Qui sommes-nous |
| `[NAV.SERVICES]` | → Nos services |
| `[NAV.REALISATIONS]` | → Nos réalisations |
| `[NAV.RESEAU]` | → Notre réseau |
| `[NAV.CONTACT]` | → Contact |
| `[NAV.CTA]` | → Demander un devis *(bouton rouge)* |

### Footer — colonne 1 (à gauche)

| ID | Texte actuel |
|---|---|
| `[FOOTER.SLOGAN]` | → Régie publicitaire et studio créatif. 30 ans à rendre les marques visibles en Côte d'Ivoire. |

### Footer — colonne "Explorer"

| ID | Texte actuel |
|---|---|
| `[FOOTER.TITRE.EXPLORER]` | → Explorer |
| `[FOOTER.LINK.QUI]` | → Qui sommes-nous |
| `[FOOTER.LINK.SERVICES]` | → Nos services |
| `[FOOTER.LINK.REALISATIONS]` | → Réalisations |
| `[FOOTER.LINK.RESEAU]` | → Notre réseau |

### Footer — colonne "Contact"

| ID | Texte actuel |
|---|---|
| `[FOOTER.TITRE.CONTACT]` | → Contact |
| `[FOOTER.CONTACT.TEL]` | → +225 07 00 78 06 28 |
| `[FOOTER.CONTACT.MAIL]` | → commercial@cible-ci.com |
| `[FOOTER.CONTACT.ADRESSE]` | → Rue des Ambassadeurs<br>Riviera M'Badon<br>10 BP 1029 Abidjan 10 |

### Footer — colonne "Reconnaissances"

| ID | Texte actuel |
|---|---|
| `[FOOTER.TITRE.RECON]` | → Reconnaissances |
| `[FOOTER.RECON.2016]` | → 2016 · 2ᵉ prix meilleur publicitaire |
| `[FOOTER.RECON.2019]` | → 2019 · Chevalier de l'Ordre du Mérite de la Communication |
| `[FOOTER.RECON.2020]` | → 2020 · Officier de l'Ordre du Mérite National |

### Footer — copyright (bas)

| ID | Texte actuel |
|---|---|
| `[FOOTER.COPY]` | → © 2026 CIBLE · Tous droits réservés |
| `[FOOTER.COPY.SUB]` | → Régie publicitaire · Côte d'Ivoire |

---

## 🏠 PAGE HOME (`/cible`)

### Hero (haut de page)

| ID | Texte actuel |
|---|---|
| `[HOME.HERO.SURTITRE]` | → Régie & studio · Côte d'Ivoire · depuis 1994 |
| `[HOME.HERO.TITRE.L1]` | → Votre marque, |
| `[HOME.HERO.TITRE.L2]` | → partout où vit |
| `[HOME.HERO.TITRE.L3]` | → votre audience. *(en rouge)* |
| `[HOME.HERO.SOUS_TITRE]` | → Régie publicitaire en Côte d'Ivoire — 364 panneaux d'affichage dans 31 communes, publicité mobile et communication 360°. |
| `[HOME.HERO.ACCROCHE]` | → Nous n'offrons pas seulement de la visibilité : nous offrons une **performance mesurable, orientée résultats** — de l'exposition dans la rue jusqu'à la visite en point de vente. |
| `[HOME.HERO.CTA1]` | → Rendre ma marque visible *(bouton rouge)* |
| `[HOME.HERO.CTA2]` | → Voir nos réalisations *(bouton ligne)* |

### Bandeau défilant (ticker)

| ID | Texte actuel |
|---|---|
| `[HOME.TICKER.1]` | → 364 panneaux |
| `[HOME.TICKER.2]` | → 31 communes |
| `[HOME.TICKER.3]` | → 30 ans d'expertise |
| `[HOME.TICKER.4]` | → 5 territoires de visibilité |
| `[HOME.TICKER.5]` | → 3 distinctions d'État |
| `[HOME.TICKER.6]` | → De la rue au digital |

### Section "Marque" (fond violet + parrots)

| ID | Texte actuel |
|---|---|
| `[HOME.MARQUE.SURTITRE]` | → Opération plume rouge |
| `[HOME.MARQUE.TITRE]` | → Se faire remarquer, c'est un métier. |
| `[HOME.MARQUE.INTRO]` | → Trente ans à imposer les marques dans le paysage ivoirien — de l'affiche peinte à la campagne 360° mesurée en temps réel. |
| `[HOME.MARQUE.01.TITRE]` | → Notre origine — maîtres de la visibilité extérieure |
| `[HOME.MARQUE.01.TEXTE]` | → Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire : panneaux grand format, signalétique de carrefour, camions de parade, habillage de véhicules. Un patrimoine de 364 emplacements, construit un panneau à la fois. |
| `[HOME.MARQUE.02.TITRE]` | → Notre évolution — la visibilité devient mesurable |
| `[HOME.MARQUE.02.TEXTE]` | → Les usages ont changé, les attentes des annonceurs aussi. Le digital 360° ne remplace pas notre réseau : il le prolonge et le rend mesurable. Nous n'offrons plus seulement de la visibilité, mais une performance vérifiable, orientée résultats. |
| `[HOME.MARQUE.03.TITRE]` | → Notre force — le terrain et la donnée |
| `[HOME.MARQUE.03.TEXTE]` | → Trente ans de connaissance du terrain ivoirien fusionnés avec une approche moderne et une exigence de résultat. Nous sommes la seule régie du pays à posséder à la fois son réseau et l'outil qui le pilote. |
| `[HOME.MARQUE.SIGNATURE]` | → Créer l'impact. **Construire la notoriété.** |

### Section "Territoires" (onglets 5 couleurs)

| ID | Texte actuel |
|---|---|
| `[HOME.TERR.SURTITRE]` | → Où votre marque apparaît |
| `[HOME.TERR.TITRE]` | → Cinq territoires de visibilité, une seule audience. |
| `[HOME.TERR.INTRO]` | → Les cinq couleurs de notre symbole représentent des panneaux publicitaires. Elles représentent aussi les cinq espaces que traverse une journée abidjanaise — et dans lesquels nous vous rendons présent. |

#### Onglet 1 · La rue (rouge)
| ID | Texte actuel |
|---|---|
| `[HOME.TERR.RUE.ONGLET]` | → La rue |
| `[HOME.TERR.RUE.CHIFFRE]` | → 364 |
| `[HOME.TERR.RUE.SURTITRE]` | → Panneaux en exploitation |
| `[HOME.TERR.RUE.TITRE]` | → Affichage grand format : le seul média qu'on ne peut pas fermer. |
| `[HOME.TERR.RUE.TEXTE]` | → L'affichage grand format reste le seul média que personne ne peut sauter, bloquer ou faire défiler. Nous exploitons notre propre patrimoine : nous maîtrisons donc les emplacements, les délais et la preuve de pose. |
| `[HOME.TERR.RUE.TAGS]` | → Classiques \| Lumipub \| Trivision \| Panoramiques \| Écrans digitaux \| Affichage en magasin |
| `[HOME.TERR.RUE.PREUVE]` | → Chaque campagne se termine par une pige photo horodatée depuis le terrain. La visibilité se constate, elle ne se promet pas. |

#### Onglet 2 · Le mouvement (jaune)
| ID | Texte actuel |
|---|---|
| `[HOME.TERR.MVT.ONGLET]` | → Le mouvement |
| `[HOME.TERR.MVT.CHIFFRE]` | → 31 |
| `[HOME.TERR.MVT.SURTITRE]` | → Communes atteintes |
| `[HOME.TERR.MVT.TITRE]` | → Publicité mobile : la ville devient votre support. |
| `[HOME.TERR.MVT.TEXTE]` | → Camions publicitaires, tricycles, motos, taxis, chevalets. Le message va chercher l'audience là où elle est immobile et captive : embouteillages, marchés, sorties d'école, abords des zones commerciales. |
| `[HOME.TERR.MVT.TAGS]` | → Camions publicitaires \| Branding véhicules \| Taxis & motos \| Chevalets \| Régie mobile événementielle |
| `[HOME.TERR.MVT.PREUVE]` | → Itinéraires et créneaux définis avec vous, tracés et rapportés après diffusion. |

#### Onglet 3 · L'écran (vert)
| ID | Texte actuel |
|---|---|
| `[HOME.TERR.ECRAN.ONGLET]` | → L'écran |
| `[HOME.TERR.ECRAN.CHIFFRE]` | → Studio |
| `[HOME.TERR.ECRAN.SURTITRE]` | → Production interne |
| `[HOME.TERR.ECRAN.TITRE]` | → Production audiovisuelle : l'image qui porte le message. |
| `[HOME.TERR.ECRAN.TEXTE]` | → Films institutionnels, spots TV et radio, motion design, contenus de marque. Le studio produit ce que le réseau diffuse : une même équipe, de la conception jusqu'à l'affichage. |
| `[HOME.TERR.ECRAN.TAGS]` | → Films institutionnels \| Spots TV & audio \| Motion design \| Identité visuelle \| Contenu de marque |
| `[HOME.TERR.ECRAN.PREUVE]` | → Film institutionnel réalisé pour le Groupe Cofina. |

#### Onglet 4 · Le digital (bleu)
| ID | Texte actuel |
|---|---|
| `[HOME.TERR.DIGITAL.ONGLET]` | → Le digital |
| `[HOME.TERR.DIGITAL.CHIFFRE]` | → 24/7 |
| `[HOME.TERR.DIGITAL.SURTITRE]` | → Présence continue |
| `[HOME.TERR.DIGITAL.TITRE]` | → Communication digitale : le prolongement naturel du panneau. |
| `[HOME.TERR.DIGITAL.TEXTE]` | → Une campagne d'affichage sans relais digital perd la moitié de son effet. Social media ads, SEO/SEA, activations interactives, drive-to-store : nous transformons l'exposition en interaction, puis en visite. |
| `[HOME.TERR.DIGITAL.TAGS]` | → Social media ads \| SEO / SEA \| Campagnes virales \| Activations interactives \| Drive-to-store |
| `[HOME.TERR.DIGITAL.PREUVE]` | → Conception graphique et gestion des réseaux pour SGS / SICTA. |

#### Onglet 5 · Le terrain (violet)
| ID | Texte actuel |
|---|---|
| `[HOME.TERR.TERRAIN.ONGLET]` | → Le terrain |
| `[HOME.TERR.TERRAIN.CHIFFRE]` | → Face à face |
| `[HOME.TERR.TERRAIN.SURTITRE]` | → Le dernier mètre |
| `[HOME.TERR.TERRAIN.TITRE]` | → Street marketing : là où la marque devient une rencontre. |
| `[HOME.TERR.TERRAIN.TEXTE]` | → Street marketing, pop-up stores, roadshows, stands expérientiels, architecture événementielle. Le moment où l'audience ne regarde plus la marque : elle lui parle. |
| `[HOME.TERR.TERRAIN.TAGS]` | → Street marketing \| Pop-up store \| Roadshow \| Stand expérientiel \| Architecture événementielle |
| `[HOME.TERR.TERRAIN.PREUVE]` | → Brand experience déployée pour Orange · stand expérientiel pour IFG. |

### Section "Mission" (fond gris)

| ID | Texte actuel |
|---|---|
| `[HOME.MISSION.P1]` | → Nous conceptualisons des campagnes audacieuses et créatives, pensées pour votre public, afin de maximiser visibilité et impact. Nous déposons votre message au cœur de l'expérience urbaine et digitale, là où il résonne le plus. Chaque levier est pensé pour engager vos audiences et amplifier votre visibilité. |
| `[HOME.MISSION.FORT]` | → Notre mission : transformer chaque message en une rencontre mémorable, et chaque campagne en un moteur de croissance — jusqu'au point de vente. |

### Section "Réalisations" (grille 6 cards)

| ID | Texte actuel |
|---|---|
| `[HOME.TRAVAUX.SURTITRE]` | → Réalisations |
| `[HOME.TRAVAUX.TITRE]` | → Nos campagnes, en vrai. |
| `[HOME.TRAVAUX.CTA]` | → Voir toutes les réalisations |
| `[HOME.TRAVAUX.C1.NOM]` | → Orange |
| `[HOME.TRAVAUX.C1.CAT]` | → Brand experience |
| `[HOME.TRAVAUX.C2.NOM]` | → Cofina |
| `[HOME.TRAVAUX.C2.CAT]` | → Film institutionnel |
| `[HOME.TRAVAUX.C3.NOM]` | → Snedai |
| `[HOME.TRAVAUX.C3.CAT]` | → Stratégie 360° |
| `[HOME.TRAVAUX.C4.NOM]` | → SGS · SICTA |
| `[HOME.TRAVAUX.C4.CAT]` | → Création & réseaux sociaux |
| `[HOME.TRAVAUX.C5.NOM]` | → IFG |
| `[HOME.TRAVAUX.C5.CAT]` | → Stand expérientiel |
| `[HOME.TRAVAUX.C6.NOM]` | → SIGFU |
| `[HOME.TRAVAUX.C6.CAT]` | → Design & architecture |

### Section "Réseau" (fond bleu)

| ID | Texte actuel |
|---|---|
| `[HOME.RESEAU.SURTITRE]` | → La preuve |
| `[HOME.RESEAU.TITRE]` | → Un réseau d'affichage en propre, à Abidjan et dans tout le pays. |
| `[HOME.RESEAU.TEXTE]` | → Une agence loue l'espace d'un tiers. Nous exploitons le nôtre : 364 panneaux répartis dans 31 communes, de Bouaké à San-Pédro. C'est ce qui nous permet de vous garantir un emplacement, une date de pose et une preuve photo — pas une estimation. |
| `[HOME.RESEAU.STAT1.V]` | → 180 |
| `[HOME.RESEAU.STAT1.L]` | → Panneaux · Abidjan<br>14 communes |
| `[HOME.RESEAU.STAT2.V]` | → 184 |
| `[HOME.RESEAU.STAT2.L]` | → Panneaux · Intérieur<br>17 villes |
| `[HOME.RESEAU.CTA]` | → Explorer la carte du réseau |
| `[HOME.RESEAU.NOTE]` | → Réseau détaillé et carte interactive sur la page Notre réseau. |

### Section "Distinctions" (fond blanc)

| ID | Texte actuel |
|---|---|
| `[HOME.DIST.SURTITRE]` | → Reconnaissances officielles |
| `[HOME.DIST.TITRE]` | → Trois distinctions de l'État ivoirien. |
| `[HOME.DIST.1.AN]` | → 2016 |
| `[HOME.DIST.1.TITRE]` | → 2ᵉ prix du meilleur publicitaire |
| `[HOME.DIST.1.TEXTE]` | → Distinction professionnelle du secteur de la publicité ivoirienne. |
| `[HOME.DIST.2.AN]` | → 2019 |
| `[HOME.DIST.2.TITRE]` | → Chevalier de l'Ordre du Mérite de la Communication |
| `[HOME.DIST.2.TEXTE]` | → Reconnaissance de la contribution à la structuration du métier. |
| `[HOME.DIST.3.AN]` | → 2020 |
| `[HOME.DIST.3.TITRE]` | → Officier de l'Ordre du Mérite National |
| `[HOME.DIST.3.TEXTE]` | → Distinction républicaine pour services rendus au pays. |

### Section "Contact" (fond jaune, en bas)

| ID | Texte actuel |
|---|---|
| `[HOME.CONTACT.TITRE]` | → Entrons en contact. |
| `[HOME.CONTACT.TEXTE]` | → Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la journée ouvrée avec une proposition chiffrée. |
| `[HOME.CONTACT.CTA]` | → Demander un devis |
| `[HOME.CONTACT.LINK1]` | → Formulaire de contact |

---

## 👥 PAGE QUI-SOMMES-NOUS (`/cible/qui-sommes-nous`)

### Hero

| ID | Texte actuel |
|---|---|
| `[QUI.HERO.SURTITRE]` | → Depuis 1994 |
| `[QUI.HERO.TITRE]` | → Trente ans à faire rayonner les marques. |
| `[QUI.HERO.TEXTE]` | → Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire. De l'artisanat du panneau grand format à la campagne 360° mesurée en temps réel, notre métier a évolué — notre exigence, jamais. |

### Section "Récit" (4 articles)

| ID | Texte actuel |
|---|---|
| `[QUI.RECIT.1.TITRE]` | → Notre origine — maîtres de la visibilité extérieure |
| `[QUI.RECIT.1.TEXTE]` | → Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire : panneaux grand format, signalétique de carrefour, camions de parade, habillage de véhicules. Un patrimoine de 364 emplacements, construit un panneau à la fois, dans 31 communes du pays. |
| `[QUI.RECIT.2.TITRE]` | → Notre évolution — la visibilité devient mesurable |
| `[QUI.RECIT.2.TEXTE]` | → Les usages ont changé, les attentes des annonceurs aussi. Le digital 360° ne remplace pas notre réseau : il le prolonge et le rend mesurable. Nous n'offrons plus seulement de la visibilité, mais une performance vérifiable, orientée résultats. |
| `[QUI.RECIT.3.TITRE]` | → Notre force — le terrain et la donnée |
| `[QUI.RECIT.3.TEXTE]` | → Trente ans de connaissance du terrain ivoirien fusionnés avec une approche moderne et une exigence de résultat. Nous sommes la seule régie du pays à posséder à la fois son propre réseau et l'outil qui le pilote — de la commande à la preuve de pose. |
| `[QUI.RECIT.4.TITRE]` | → Notre engagement — la preuve, pas la promesse |
| `[QUI.RECIT.4.TEXTE]` | → Chaque campagne se termine par un dossier de preuves : photos horodatées depuis le terrain, planning de pose, rapports de diffusion. Vous savez exactement où votre marque a été vue, quand, et par combien de personnes. |

### Section "Stats" (fond noir)

| ID | Texte actuel |
|---|---|
| `[QUI.STATS.SURTITRE]` | → En chiffres |
| `[QUI.STATS.TITRE]` | → Trente ans concentrés en quatre nombres. |
| `[QUI.STATS.1.V]` | → 30 |
| `[QUI.STATS.1.L]` | → Ans d'expertise |
| `[QUI.STATS.2.V]` | → 364 |
| `[QUI.STATS.2.L]` | → Panneaux en propre |
| `[QUI.STATS.3.V]` | → 31 |
| `[QUI.STATS.3.L]` | → Communes couvertes |
| `[QUI.STATS.4.V]` | → 3 |
| `[QUI.STATS.4.L]` | → Distinctions d'État |

### Section "Distinctions" (fond gris)

| ID | Texte actuel |
|---|---|
| `[QUI.DIST.SURTITRE]` | → Reconnaissances officielles |
| `[QUI.DIST.TITRE]` | → Trois distinctions de l'État ivoirien. |
| `[QUI.DIST.1.AN]` | → 2016 |
| `[QUI.DIST.1.TITRE]` | → 2ᵉ prix du meilleur publicitaire |
| `[QUI.DIST.1.TEXTE]` | → Distinction professionnelle du secteur de la publicité ivoirienne. |
| `[QUI.DIST.2.AN]` | → 2019 |
| `[QUI.DIST.2.TITRE]` | → Chevalier de l'Ordre du Mérite de la Communication |
| `[QUI.DIST.2.TEXTE]` | → Reconnaissance de la contribution à la structuration du métier en Côte d'Ivoire. |
| `[QUI.DIST.3.AN]` | → 2020 |
| `[QUI.DIST.3.TITRE]` | → Officier de l'Ordre du Mérite National |
| `[QUI.DIST.3.TEXTE]` | → Distinction républicaine pour services rendus au pays. |

### CTA final (fond blanc)

| ID | Texte actuel |
|---|---|
| `[QUI.CTA.TITRE]` | → Prêt à écrire le prochain chapitre avec nous ? |
| `[QUI.CTA.BTN1]` | → Demander un devis *(bouton rouge)* |
| `[QUI.CTA.BTN2]` | → Voir nos réalisations *(bouton ligne)* |

---

## 🛠 PAGE SERVICES (`/cible/services`)

### Hero

| ID | Texte actuel |
|---|---|
| `[SERV.HERO.SURTITRE]` | → Nos services |
| `[SERV.HERO.TITRE]` | → Trois pôles complémentaires, **un seul interlocuteur.** *(fin en rouge)* |
| `[SERV.HERO.TEXTE]` | → Peu importe la forme que prendra votre campagne — panneau statique, camion mobile, écran digital, opération street ou dispositif global — vous travaillez avec la même équipe, du premier brief à la pige photo finale. |

### Pôle 01 · Régie publicitaire (rouge)

| ID | Texte actuel |
|---|---|
| `[SERV.P1.TAG]` | → Pôle 01 · Régie publicitaire |
| `[SERV.P1.TITRE]` | → La force d'un **réseau national.** |
| `[SERV.P1.TEXTE]` | → Notre cœur de métier depuis 30 ans. **364 panneaux stratégiquement placés** couvrent **31 communes du pays**. Chaque emplacement est référencé, contrôlé, maintenu — et disponible en visibilité temps réel pour votre équipe marketing. |
| `[SERV.P1.FORMATS.TITRE]` | → Formats disponibles |
| `[SERV.P1.FORMATS.SOUS]` | → Six catégories, treize formats — du petit format 2×1m au panoramique 14×5m. |
| `[SERV.P1.FORMAT.1]` | → Petit format · 2×1m à 5×2m · 2 → 10 m² |
| `[SERV.P1.FORMAT.2]` | → Classique · 4×3m · 12 m² |
| `[SERV.P1.FORMAT.3]` | → Grande dimension · 6×3m à 6×4m · 18 → 24 m² |
| `[SERV.P1.FORMAT.4]` | → Grand format · 8×4m à 6×6m · 32 → 36 m² |
| `[SERV.P1.FORMAT.5]` | → Très grand format · 10×5m à 9×6m · 50 → 54 m² |
| `[SERV.P1.FORMAT.6]` | → Panoramique · 14×5m · 70 m² |
| `[SERV.P1.DISP.TITRE]` | → 6 dispositifs affichage |
| `[SERV.P1.DISP.1]` | → Panneaux classiques |
| `[SERV.P1.DISP.2]` | → Lumipub (caissons éclairés) |
| `[SERV.P1.DISP.3]` | → Trivision (3 visuels en rotation) |
| `[SERV.P1.DISP.4]` | → Panoramiques grand format |
| `[SERV.P1.DISP.5]` | → Écrans digitaux |
| `[SERV.P1.DISP.6]` | → Écrans en magasins |

### Pôle 02 · Communication mobile (jaune)

| ID | Texte actuel |
|---|---|
| `[SERV.P2.TAG]` | → Pôle 02 · Communication mobile |
| `[SERV.P2.TITRE]` | → Votre message **en mouvement.** |
| `[SERV.P2.TEXTE]` | → Là où le panneau statique attend son public, la publicité mobile va vers lui. Traversée d'Abidjan aux heures de pointe, présence pendant un événement, saturation d'un quartier commerçant en fin de semaine — chaque dispositif mobile a son usage stratégique. |
| `[SERV.P2.DISP.1.TITRE]` | → 🚛 Camions publicitaires |
| `[SERV.P2.DISP.1.TEXTE]` | → Grandes surfaces d'affichage roulantes. Idéal pour lancement produit, opérations événementielles, saturation d'un axe. |
| `[SERV.P2.DISP.2.TITRE]` | → 🏍 Motos publicitaires |
| `[SERV.P2.DISP.2.TEXTE]` | → Agilité pour circuler dans le trafic dense. Zones difficiles d'accès en camion, campagnes de proximité. |
| `[SERV.P2.DISP.3.TITRE]` | → 🚗 Branding véhicules |
| `[SERV.P2.DISP.3.TEXTE]` | → Habillage complet de flottes d'entreprise ou de véhicules dédiés à la campagne. |
| `[SERV.P2.DISP.4.TITRE]` | → 🚕 Branding taxis & cars |
| `[SERV.P2.DISP.4.TEXTE]` | → Publicité sur les transports en commun urbains et inter-urbains — visibilité massive à coût maîtrisé. |
| `[SERV.P2.DISP.5.TITRE]` | → 🪧 Chevalets publicitaires |
| `[SERV.P2.DISP.5.TEXTE]` | → Petits dispositifs pour événements, marchés, points de vente. Rapides à installer, économiques. |

### Pôle 03 · Communication 360° (violet)

| ID | Texte actuel |
|---|---|
| `[SERV.P3.TAG]` | → Pôle 03 · Communication 360° |
| `[SERV.P3.TITRE]` | → De l'idée **à l'exécution.** |
| `[SERV.P3.TEXTE]` | → Un panneau vide ne dit rien. Un panneau mal conçu dit mal. Nous proposons une chaîne complète : **stratégie, création graphique, production visuelle, présence digitale** — pour que votre message soit aussi fort dans la rue que dans les fils d'actualité. |
| `[SERV.P3.OFFRE.1.TITRE]` | → Création graphique |
| `[SERV.P3.OFFRE.1.TEXTE]` | → Studio interne : visuels d'affichage, adaptation aux formats, production print et digital. |
| `[SERV.P3.OFFRE.2.TITRE]` | → Stratégie de communication |
| `[SERV.P3.OFFRE.2.TEXTE]` | → Recommandation d'un plan média, choix des emplacements, calendrier de diffusion. |
| `[SERV.P3.OFFRE.3.TITRE]` | → Street marketing |
| `[SERV.P3.OFFRE.3.TEXTE]` | → Opérations terrain de proximité : distribution, animation commerciale, échantillonnage. |
| `[SERV.P3.OFFRE.4.TITRE]` | → Digital & réseaux sociaux |
| `[SERV.P3.OFFRE.4.TEXTE]` | → Extension de votre campagne outdoor vers les plateformes numériques. |
| `[SERV.P3.OFFRE.5.TITRE]` | → Relations presse |
| `[SERV.P3.OFFRE.5.TEXTE]` | → Mise en contact avec les médias ivoiriens, coordination d'annonces institutionnelles. |
| `[SERV.P3.OFFRE.6.TITRE]` | → Production audiovisuelle |
| `[SERV.P3.OFFRE.6.TEXTE]` | → Films institutionnels, spots TV et radio, motion design, contenus de marque. |

### Section "Workflow" (fond noir)

| ID | Texte actuel |
|---|---|
| `[SERV.WORK.SURTITRE]` | → Notre méthode |
| `[SERV.WORK.TITRE]` | → De la demande **à l'affichage.** |
| `[SERV.WORK.INTRO]` | → Huit étapes, un interlocuteur unique, une traçabilité complète. Vous recevez la pige photo horodatée dès que votre affichage est en place — plus besoin de dépêcher quelqu'un pour vérifier. |
| `[SERV.WORK.1.TITRE]` | → Demande |
| `[SERV.WORK.1.TEXTE]` | → Vous exposez besoin, cible, budget. |
| `[SERV.WORK.2.TITRE]` | → Sélection |
| `[SERV.WORK.2.TEXTE]` | → Emplacements proposés selon zone et format. |
| `[SERV.WORK.3.TITRE]` | → Proposition |
| `[SERV.WORK.3.TEXTE]` | → Devis détaillé, tarifs, calendrier. |
| `[SERV.WORK.4.TITRE]` | → Validation |
| `[SERV.WORK.4.TEXTE]` | → Signature de l'accord. |
| `[SERV.WORK.5.TITRE]` | → Planification |
| `[SERV.WORK.5.TEXTE]` | → Équipes terrain assignées. |
| `[SERV.WORK.6.TITRE]` | → Pose |
| `[SERV.WORK.6.TEXTE]` | → Techniciens sur site. |
| `[SERV.WORK.7.TITRE]` | → Pige photo |
| `[SERV.WORK.7.TEXTE]` | → Preuve horodatée envoyée. |
| `[SERV.WORK.8.TITRE]` | → Suivi |
| `[SERV.WORK.8.TEXTE]` | → Espace client dédié en ligne. |

### CTA final

| ID | Texte actuel |
|---|---|
| `[SERV.CTA.SURTITRE]` | → Prochaine étape |
| `[SERV.CTA.TITRE]` | → Un projet à faire décoller ? |
| `[SERV.CTA.TEXTE]` | → Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la journée ouvrée avec une proposition chiffrée. |
| `[SERV.CTA.BTN1]` | → Demander un devis *(bouton rouge)* |
| `[SERV.CTA.BTN2]` | → Voir nos réalisations *(bouton ligne)* |

---

## 🗺 PAGE RÉSEAU (`/cible/reseau`)

### Hero (fond bleu)

| ID | Texte actuel |
|---|---|
| `[RES.HERO.SURTITRE]` | → La preuve terrain |
| `[RES.HERO.TITRE]` | → Un réseau d'affichage en propre, à Abidjan et dans tout le pays. |
| `[RES.HERO.TEXTE]` | → Une agence loue l'espace d'un tiers. Nous exploitons le nôtre : 364 panneaux répartis dans 31 communes, de Bouaké à San-Pédro. C'est ce qui nous permet de vous garantir un emplacement, une date de pose et une preuve photo — pas une estimation. |
| `[RES.HERO.STAT1.V]` | → 364 |
| `[RES.HERO.STAT1.L]` | → Panneaux au total<br>31 communes |
| `[RES.HERO.STAT2.V]` | → 180 |
| `[RES.HERO.STAT2.L]` | → Panneaux · Abidjan<br>14 communes |
| `[RES.HERO.STAT3.V]` | → 184 |
| `[RES.HERO.STAT3.L]` | → Panneaux · Intérieur<br>17 villes |

### Section carte Leaflet

| ID | Texte actuel |
|---|---|
| `[RES.MAP.LOADING]` | → Chargement de la carte… |
| `[RES.MAP.NOTE]` | → 💡 Cliquez sur un pin pour voir le nombre de panneaux par commune. Détail complet auprès de votre commercial. |

### Section "Communes" (fond gris — 2 zones)

| ID | Texte actuel |
|---|---|
| `[RES.COMM.SURTITRE]` | → Détail par zone |
| `[RES.COMM.TITRE]` | → Là où votre marque peut apparaître. |
| `[RES.COMM.ABIDJAN.TITRE]` | → Zone Abidjan |
| `[RES.COMM.ABIDJAN.SOUS]` | → 180 panneaux · 14 communes du Grand Abidjan |
| `[RES.COMM.ABIDJAN.LISTE]` | → Plateau, Cocody, Yopougon, Abobo, Marcory, Treichville, Koumassi, Port-Bouët, Attécoubé, Adjamé, Riviera, Angré, Bingerville, Songon |
| `[RES.COMM.INT.TITRE]` | → Zone Intérieur |
| `[RES.COMM.INT.SOUS]` | → 184 panneaux · 17 villes stratégiques du pays |
| `[RES.COMM.INT.LISTE]` | → Bouaké, San-Pédro, Yamoussoukro, Korhogo, Man, Daloa, Gagnoa, Divo, Bondoukou, Odienné, Séguéla, Ferkessédougou, Dabou, Anyama, Grand-Bassam, Aboisso, Soubré |

### Section "Qualité" (3 cards)

| ID | Texte actuel |
|---|---|
| `[RES.QUAL.SURTITRE]` | → Ce qui distingue notre réseau |
| `[RES.QUAL.TITRE]` | → Un patrimoine géré, pas revendu. |
| `[RES.QUAL.C1.TITRE]` | → Emplacements en propre |
| `[RES.QUAL.C1.TEXTE]` | → Nous n'agrégeons pas des panneaux tiers : nous exploitons notre patrimoine. Vous savez exactement où votre affiche apparaîtra, dans quel angle, à quel moment. |
| `[RES.QUAL.C2.TITRE]` | → Maintenance permanente |
| `[RES.QUAL.C2.TEXTE]` | → Équipes de pose sur toutes les zones. Une affiche déchirée ou taguée est remplacée dans les 48h. La qualité de votre visibilité ne dépend pas d'un sous-traitant. |
| `[RES.QUAL.C3.TITRE]` | → Preuve photo horodatée |
| `[RES.QUAL.C3.TEXTE]` | → Chaque pose est documentée sur le terrain : photo, date, heure, GPS. Vous recevez le dossier complet à la fin de la campagne. |

### CTA final

| ID | Texte actuel |
|---|---|
| `[RES.CTA.TITRE]` | → Envie de repérer les emplacements pour votre marque ? |
| `[RES.CTA.TEXTE]` | → Envoyez-nous vos critères (zone, format, période) — nous vous préparons une sélection dans la journée. |
| `[RES.CTA.BTN]` | → Demander une sélection *(bouton rouge)* |

---

## 🏆 PAGE RÉFÉRENCES (`/cible/references`)

### Hero

| ID | Texte actuel |
|---|---|
| `[REF.HERO.SURTITRE]` | → Nos réalisations |
| `[REF.HERO.TITRE]` | → Nos campagnes, en vrai. |
| `[REF.HERO.TEXTE]` | → Trente ans de campagnes concentrés en quelques exemples récents. Des marques qui ont choisi CIBLE parce qu'elles voulaient être vues — vraiment vues. |

### Grille 6 réalisations *(mêmes textes que HOME)*

| ID | Texte actuel |
|---|---|
| `[REF.C1.NOM]` | → Orange |
| `[REF.C1.CAT]` | → Brand experience |
| `[REF.C2.NOM]` | → Cofina |
| `[REF.C2.CAT]` | → Film institutionnel |
| `[REF.C3.NOM]` | → Snedai |
| `[REF.C3.CAT]` | → Stratégie 360° |
| `[REF.C4.NOM]` | → SGS · SICTA |
| `[REF.C4.CAT]` | → Création & réseaux sociaux |
| `[REF.C5.NOM]` | → IFG |
| `[REF.C5.CAT]` | → Stand expérientiel |
| `[REF.C6.NOM]` | → SIGFU |
| `[REF.C6.CAT]` | → Design & architecture |

### Section clients (logos)

| ID | Texte actuel |
|---|---|
| `[REF.CLI.SURTITRE]` | → Ils nous font confiance |
| `[REF.CLI.TITRE]` | → Les marques qui ont grandi avec nous. |

### CTA final (fond noir)

| ID | Texte actuel |
|---|---|
| `[REF.CTA.TITRE]` | → Votre marque, la prochaine sur cette page ? |

---

## 📞 PAGE CONTACT (`/cible/contact`)

### Colonne gauche — intro + coordonnées

| ID | Texte actuel |
|---|---|
| `[CONT.SURTITRE]` | → Parlons de votre projet |
| `[CONT.TITRE]` | → Entrons en contact. |
| `[CONT.TEXTE]` | → Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la **journée ouvrée** avec une proposition chiffrée. |
| `[CONT.COORD.TEL.LBL]` | → Téléphone |
| `[CONT.COORD.TEL.VAL]` | → +225 07 00 78 06 28 |
| `[CONT.COORD.MAIL.LBL]` | → Email commercial |
| `[CONT.COORD.MAIL.VAL]` | → commercial@cible-ci.com |
| `[CONT.COORD.ADR.LBL]` | → Adresse |
| `[CONT.COORD.ADR.VAL]` | → Rue des Ambassadeurs |
| `[CONT.COORD.ADR.SUB]` | → Riviera M'Badon · 10 BP 1029 Abidjan 10 |

### Colonne droite — formulaire de devis

| ID | Texte actuel |
|---|---|
| `[CONT.FORM.TITRE]` | → Demander un devis |
| `[CONT.FORM.SUB]` | → Toutes les demandes reçoivent une réponse chiffrée dans les 24h ouvrées. |
| `[CONT.FORM.NOM]` | → Votre nom * |
| `[CONT.FORM.NOM.PH]` | → Prénom Nom *(placeholder)* |
| `[CONT.FORM.ENT]` | → Entreprise * |
| `[CONT.FORM.ENT.PH]` | → Votre société |
| `[CONT.FORM.EMAIL]` | → Email * |
| `[CONT.FORM.EMAIL.PH]` | → vous@entreprise.ci |
| `[CONT.FORM.TEL]` | → Téléphone * |
| `[CONT.FORM.TEL.PH]` | → +225 … |
| `[CONT.FORM.POSTE]` | → Votre poste |
| `[CONT.FORM.POSTE.PH]` | → Ex : Directeur Marketing |

### Message après envoi

| ID | Texte actuel |
|---|---|
| `[CONT.SUCCESS.TITRE]` | → ✓ Message reçu ! |
| `[CONT.SUCCESS.TEXTE]` | → Merci — notre équipe vous rappelle dans la journée ouvrée. |
| `[CONT.ERROR.GEN]` | → ⚠ Merci de corriger les champs marqués en rouge ci-dessous. |
| `[CONT.ERROR.MAIL_FAIL]` | → Envoi impossible. Réessayez ou appelez le 07 98 49 66 74. |

---

## 📝 Espace libre pour tes remarques / ajouts

Ajoute ici tout ce qui manque, à supprimer, ou tes commentaires libres :

- ...
- ...
- ...
