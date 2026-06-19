# DOSSIER DE SPÉCIFICATION — REFONTE ESPACE TECHNICIEN + DASHBOARD ADMIN LIVE

> 📍 Document de référence unique pour les 3 sous-missions SM2a, SM2b, SM2c.
> À garder ouvert pendant toute l'exécution. Chaque brief de sous-mission
> renvoie à ce dossier pour les détails visuels et techniques.

---

## 0. CONTEXTE ET DÉCISIONS HÉRITÉES

### 0.1 État avant SM2

À la fin de la SM1.5 :

- `tech-space.blade.php` ~150 lignes (squelette pur)
- 12+ modules JS dans `public/js/tech/` (~1500 lignes)
- 11 partials Blade dans `resources/views/public/tech/partials/`
- `TechUrlResolverService` en place (shadow, sera branché en SM2)
- 0 régression terrain depuis SM1

### 0.2 Philosophie de la refonte (rappel)

**1 tech, 1 écran, 3 actions** : voir où aller, y aller, prouver qu'il y est.

**Couleur = sens** dans toute l'app :
- 🟠 Orange = à faire MAINTENANT (action attendue)
- 🟢 Vert = fait, validé, succès
- 🔴 Rouge = problème, refus, urgent
- 🔵 Bleu = à venir, neutre, informationnel
- 🟡 Jaune = aide, conseil, attention douce

**Icônes ET texte ensemble** partout (lecture sans français possible).

**Phrases complètes** sur les boutons (pas "Photo" → "Je suis arrivé → photo").

### 0.3 Niveau numérique cible : très varié

Du tech qui n'utilise que WhatsApp jusqu'au tech à l'aise avec Google Maps.
L'UI doit fonctionner pour les deux extrêmes : **simple par défaut, riche
si besoin**.

### 0.4 Cible technique

- Téléphones bas de gamme (Android Go, 1 Go RAM)
- Réseau 2G/Edge fréquent (intérieur Côte d'Ivoire)
- Caméra arrière des téléphones bas de gamme
- Tactile imprécis (boutons minimum 44x44 px)
- Polling heartbeat 20 s déjà en place (à étendre côté admin)
- Twilio Trial : pas de WhatsApp inverse pour l'instant

---

## 1. INVENTAIRE DES 14 ÉCRANS

### Écrans tech (9 — SM2a)

| # | Nom | Usage |
|---|-----|-------|
| T1 | Accueil — carnet du jour | Page d'entrée du tech |
| T2 | Détail d'une pose | Vue d'une pose individuelle |
| T3 | Photo prise — validation GPS | Après capture photo, avant envoi |
| T4 | Succès + pose suivante | Après envoi photo |
| T5 | Signalement — choisir motif | Tap sur "Souci" |
| T6 | Signalement — détails | Après choix de motif |
| T7 | Y aller — confirmation | Tap sur "Y aller" avant Google Maps |
| T8 | Aide / tutoriel | Tap sur "?" |
| T9 | Photo refusée | Quand l'admin a refusé une photo |

### Écrans admin (5 — SM2b)

| # | Nom | Usage |
|---|-----|-------|
| A1 | Dashboard live — activité du jour | Vue d'ensemble superviseur |
| A2 | Fiche détaillée d'un tech | Tap sur un nom de tech |
| A3 | Carte temps réel | Vue géographique des techs en activité |
| A4 | Validation photo (recto-verso de T9) | L'admin valide/refuse une photo |
| A5 | Vue équipe | Pour un chef d'équipe avec 3-5 techs |

### Écrans bonus (3 — SM2c)

| # | Nom | Usage |
|---|-----|-------|
| B1 | Pose démarrée hors heure | Confirmation si tech ouvre pose hors créneau |
| B2 | Fin de journée tech | Récap "tu as fait 12/12" + déconnexion propre |
| B3 | Notifications centre | Liste de toutes les notifs tech (refus, nouvelle pose, etc.) |

---

## 2. ARCHITECTURE TECHNIQUE

### 2.1 Polling / synchronisation

**Côté tech (existant SM1)** : polling 20 s sur `tech.space.heartbeat`
→ rafraîchit KPIs locaux. À garder tel quel.

**Côté admin (NOUVEAU SM2b)** : polling 20 s sur une nouvelle route
`admin.dashboard.live` → retourne un payload JSON avec :

```json
{
  "as_of": "2026-06-17T14:32:42Z",
  "kpis": {
    "total_poses_today": 124,
    "done": 68,
    "in_progress": 12,
    "pending_validation": 8,
    "problems_open": 3
  },
  "techs_active": [
    {
      "id": 42,
      "initials": "KT",
      "full_name": "Koffi Tanguy",
      "is_online": true,
      "last_seen_at": "2026-06-17T14:32:34Z",
      "current_status": "uploading_photo",
      "current_location_label": "Cocody · Carrefour Niangon",
      "progress": { "done": 6, "total": 12 },
      "last_event": {
        "type": "photo_sent",
        "label": "Photo envoyée",
        "at": "2026-06-17T14:32:34Z"
      }
    }
  ],
  "live_events": [
    {
      "type": "photo_sent",
      "tech_initials": "KT",
      "tech_full_name": "Koffi Tanguy",
      "location_label": "Cocody",
      "at": "2026-06-17T14:32:34Z",
      "task_id": 1024,
      "actionable_url": "/admin/poses/1024/validate"
    }
  ]
}
```

### 2.2 Les 7 actions tech qui déclenchent un changement chez l'admin

| Action tech | Effet BDD | KPI admin impacté | Type live_event |
|---|---|---|---|
| Tap "Y aller" | `PoseTask.status = 'en_route'` | KPI "techs en route" +1 | `tech_en_route` |
| Caméra ouverte | `PoseTask.status = 'en_cours'` + GPS | "poses en cours" +1 | `tech_arrived` |
| Photo envoyée | Création `Pige` + GPS | "à valider" +1 | `photo_sent` |
| Tap "Signaler" | Création `ProblemReport` | "signalements" +1 | `problem_reported` |
| Tap "Refaire photo" | `Pige` refusée → nouvelle `Pige` | "à valider" +1, "refusées" -1 | `photo_resent` |
| Heartbeat tech (20s) | `User.last_seen_at` mis à jour | pastille verte/grise | (pas un event) |
| Validation photo par admin | `Pige.validated_at` → notif tech | "à valider" -1 | `photo_validated` |

### 2.3 Nouveaux endpoints à créer (SM2b)

```
GET  /admin/dashboard/live         → payload JSON ci-dessus
GET  /admin/tech/{id}/timeline     → historique chronologique d'un tech
POST /admin/pige/{id}/validate     → valide une photo
POST /admin/pige/{id}/reject       → refuse une photo (motif + commentaire)
GET  /admin/map/live               → positions GPS des techs actifs
```

### 2.4 Stack confirmée

- Pas de WebSocket / Pusher / Echo (trop lourd à mettre en place)
- Polling 20 s suffit (déjà éprouvé côté tech)
- Pas de Vue / React / Livewire (décision SM1 maintenue)
- Blade partials + JS modulaire (continuité SM1)

---

## 3. SPÉCIFICATIONS VISUELLES — ÉCRANS TECH (T1-T9)

### T1 — Accueil carnet du jour

**Layout** : portrait mobile 360×640, défilement vertical.

**Sections (de haut en bas)** :
1. **Header** (sticky) : "Mardi 17 juin · Bonjour Koffi 👋" + bouton aide rond jaune (44px) en haut droite
2. **Barre de progression** : barre verte 10px de haut, "5/12" en gras vert à droite, message motivant en dessous ("Continue, tu avances bien !")
3. **Card MAINTENANT** : bordure orange 2px, fond orange très clair `#fff7ed`, contient :
   - Étiquette "MAINTENANT" en orange avec icône flamme
   - Thumbnail panneau 60×60 px
   - Nom du panneau + commune + distance
   - Format + campagne
   - 2 boutons côte à côte : "Y aller" (vert) + "Photo" (orange), 50/50
4. **Pour chaque commune** :
   - Titre commune avec icône pin + nombre restant ("2 autres")
   - 1 ligne par pose : pastille 🔵 + nom + chevron `>`
5. **Section "Déjà faites"** (verte, pliée par défaut) :
   - "🟢 5 panneaux faits" + bouton "Voir ▾"
6. **Bouton secondaire** : "Voir ma tournée sur la carte" (gris, plein largeur)

**Variables Blade attendues** :
- `$tech` (User)
- `$todayDate` (Carbon)
- `$currentPose` (PoseTask|null) — la pose orange
- `$posesByCommune` (Collection groupée)
- `$donePoses` (Collection des terminées)
- `$progress` = ['done' => 5, 'total' => 12, 'pct' => 42]

### T2 — Détail d'une pose

**Sections** :
1. Bouton "← Retour" en haut gauche
2. Étiquette commune (orange) + nom panneau en gros
3. Photo de référence en grand (aspect 4:3)
4. Bandeau jaune d'aide contextuelle (si tech débutant, détecté via flag localStorage)
5. 2 gros boutons empilés : "🗺 Y aller en voiture" (vert) + "📷 Je suis arrivé → photo" (orange)
6. Bouton tertiaire rouge léger : "⚠ Il y a un problème"

### T3 — Photo prise — validation GPS

**Sections** :
1. Étiquette commune + nom panneau
2. Photo capturée en grand (aspect 3:4 portrait), avec bandeau noir en bas affichant GPS + heure
3. **Boîte de validation** (verte si OK, orange si distance >100m, rouge si >500m) :
   - VERT (`#f0fdf4`) : "✓ Position GPS bien enregistrée — Tu es à 23 m du panneau"
   - ORANGE (`#fff7ed`) : "⚠ Tu es à 250 m du panneau, vérifie que c'est le bon"
   - ROUGE (`#fef2f2`) : "✗ Tu es à 800 m du panneau, c'est probablement pas le bon"
4. 2 boutons : "Refaire" (gris, 1/3) + "Envoyer la photo" (vert, 2/3)

### T4 — Succès + pose suivante

**Sections** :
1. **Bandeau vert plein écran** :
   - Cercle blanc avec icône check géante (48px)
   - "Bravo Koffi !" en gros
   - "Photo bien envoyée"
   - Badge progression "6 / 12 panneaux faits"
2. **Section pose suivante** :
   - Étiquette "POSE SUIVANTE"
   - Card orange clair avec thumbnail + commune + distance + nom + format
3. Bouton principal vert : "→ Continuer avec celle-ci"
4. Bouton secondaire blanc : "📋 Choisir une autre"

### T5 — Signalement choisir motif

**Sections** :
1. Bouton "✗ Annuler" en haut gauche
2. Titre "C'est quoi le souci ?" + sous-titre avec nom de la pose
3. **Liste verticale de 6 motifs** (1 ligne = 1 motif) :
   - 🔨 Panneau cassé
   - 🚧 Je n'arrive pas à accéder
   - 📍 Mauvaise adresse
   - ☔ Mauvais temps (pluie)
   - 📭 Matériel manquant
   - 💬 Autre — je vais expliquer

Chaque ligne : emoji 24px + texte 14px, fond blanc, bordure fine, radius 14px, padding 14px.

### T6 — Signalement détails

**Sections** :
1. Bouton "← Changer de motif" en haut gauche
2. **Rappel visuel du motif choisi** : fond jaune `#fef3c7`, emoji 32px + "Tu as choisi" + nom du motif en gras
3. Section "Une photo du souci ?" + bouton dashed border "Ajouter une photo (optionnel)"
4. Section "Un commentaire ?" + textarea avec placeholder "Ex: le côté droit est arraché par le vent..."
5. Bouton final rouge : "📤 Envoyer le signalement"

### T7 — Y aller — confirmation

**Sections** :
1. Bouton "✗ Annuler" en haut gauche
2. Titre "Je t'emmène là-bas" + sous-titre avec nom de la pose
3. **Mini-carte stylisée** (140px de haut) : pas une vraie carte, juste une vignette dégradée bleu clair avec :
   - Badge blanc "Toi" en haut gauche (point bleu)
   - Badge orange "Panneau" en bas droite (icône pin)
   - Ligne pointillée bleue reliant les deux
4. **Bloc 2 stats** : "5 min À PIED" | "200 m DISTANCE"
5. Bouton principal vert : "🗺 Ouvrir Google Maps"
6. Bouton tertiaire blanc : "← Je connais le chemin, retour"

### T8 — Aide / tutoriel

**Sections** :
1. **Header jaune** (`#fef3c7`) avec bouton "✗ Fermer" + emoji 💡 + titre "Besoin d'aide ?" + sous-titre "3 choses simples à savoir"
2. **3 cards horizontales** (cercle avec emoji + titre + description) :
   - 📍 Pour aller au panneau → "Tape Y aller. Google Maps s'ouvre."
   - 📸 Pour envoyer la photo → "Devant le panneau, tape Photo. La caméra s'ouvre."
   - ⚠️ Si tu as un souci → "Tape Il y a un problème. Choisis le motif."
3. Bouton vert : "▶ Voir le tutoriel (1 min)"
4. Bouton blanc : "📞 Appeler mon chef"

### T9 — Photo refusée

**Sections** :
1. **Bandeau rouge épinglé** en haut :
   - Cercle rouge 44px avec icône alerte
   - "À REFAIRE" + "Photo refusée"
   - Sous-titre "Le chef a refusé ta photo de **Carrefour Niangon**"
2. **Bulle message du chef** (style WhatsApp) :
   - Avatar icône bulle
   - Étiquette "MESSAGE DU CHEF"
   - Texte du commentaire de refus en italique
3. **Mini photo refusée** + bloc envoi (côte à côte) :
   - Vignette photo avec badge rouge "REFUSÉE"
   - Bloc gris : "ENVOYÉE Hier à 9:53" + "À 200 m du panneau"
4. Bouton principal rouge : "📷 Refaire la photo"
5. Bouton tertiaire : "📞 Appeler le chef pour comprendre"

**Variante intégrée dans l'accueil T1** : si une ou plusieurs photos sont refusées, un **bandeau rouge épinglé tout en haut** apparaît avec compteur ("1 photo à refaire") et chevron qui ouvre T9.

---

## 4. SPÉCIFICATIONS VISUELLES — ÉCRANS ADMIN (A1-A5)

### A1 — Dashboard live activité du jour

**Layout** : desktop 1280px, card pleine largeur.

**Sections** :
1. **Header** : "PILOTAGE TERRAIN" + date + badge "Mise à jour il y a 4 s" (pastille verte pulsante)
2. **4 KPIs en grid** (1/4 chacun) :
   - Progression (68/124 + barre verte + 55%)
   - En cours (12 — fond orange clair)
   - À valider (8 — fond jaune clair)
   - Signalements (3 — fond rouge clair)
3. **Bandeau notification live** (s'affiche 30s puis disparaît) : fond orange clair, pastille pulsante, message + bouton "Valider →"
4. **Titre section** "Techs en activité (12)"
5. **Liste des techs** (1 ligne par tech) :
   - Avatar 36px avec pastille présence verte/grise
   - Nom + statut courant
   - Progression individuelle "6/12"
   - "il y a Xs"
   - Badge coloré du statut ("📸 Photo envoyée" / "🚗 En route" / "⚠ Souci" / "⏸ Inactif")
6. Bouton "Voir les X autres techs ↓" si beaucoup

### A2 — Fiche détaillée d'un tech

**Sections** :
1. **Header** : bouton retour + avatar + nom + statut "En ligne · Dernière action il y a 8 s" + badge Live
2. **4 KPIs personnels** (1/4 chacun) : Faites (6) · En cours (1) · Restant (5) · En activité (2h 14)
3. **Card orange "EN CE MOMENT"** : icône caméra + "Photographie Carrefour Niangon" + "Cocody · démarré il y a 1 min" + bouton "Voir →"
4. **Frise chronologique** (timeline verticale) :
   - Ligne verticale grise
   - Points colorés (orange = en cours, vert = terminé, rouge = signalement, gris = démarrage)
   - Point orange avec anneau pulsant pour l'événement courant
   - Chaque entrée : heure relative + description + détails (GPS, distance, motif)
5. **3 actions rapides en bas** : Appeler / WhatsApp / Localiser

### A3 — Carte temps réel

**Layout** : pleine page, carte Leaflet en background.

**Sections** :
1. **Header flottant** en haut : titre + sélecteur commune + badge "Live"
2. **Marqueurs sur la carte** :
   - Points colorés pour chaque tech actif (avatar avec initiales)
   - Cluster si plusieurs proches
   - Click sur marqueur → popup avec mini-fiche (initiales + nom + dernière action + bouton "Ouvrir fiche")
3. **Panneau latéral droit** (collapsible) :
   - Liste compacte des techs visibles
   - Tap → centrer la carte sur ce tech
4. **Légende en bas** : 🟢 actif · 🟠 en pose · 🔴 problème · ⚫ hors ligne

### A4 — Validation photo (recto-verso de T9)

**Sections** :
1. **Header** : "Validation photo" + bouton fermer
2. **Photo principale** (grande, 16:9) avec overlay GPS en bas
3. **Bloc infos** (sous la photo) :
   - Tech qui a envoyé + heure
   - Distance au panneau (mesurée par GPS)
   - Référence panneau + campagne
4. **Photo de référence** (petite, en accordéon) pour comparer
5. **2 boutons côte à côte** :
   - "✗ Refuser" (rouge) → ouvre modale motif
   - "✓ Valider" (vert)
6. **Modale refus** (si tap "Refuser") :
   - Titre "Pourquoi refuser ?"
   - Choix rapide : "Floue" / "Mauvais panneau" / "GPS trop loin" / "Autre"
   - Textarea pour commentaire (sera envoyé au tech via T9)
   - Bouton "Envoyer le refus"

### A5 — Vue équipe

**Sections** :
1. **Header** : "Équipe Pose" + bouton retour + nb membres
2. **Stats équipe globales** : Total fait · Total restant · Vitesse moyenne (pose/heure)
3. **Membres** en grille de cards (3 colonnes desktop, 2 mobile) :
   - Avatar 56px + nom
   - Mini-barre de progression + "X/Y"
   - Statut courant + heure dernière activité
   - Tap → ouvre A2 (fiche tech)
4. **Bouton bas** : "Comparer les performances de l'équipe" (lien vers rapport SLA)

---

## 5. SPÉCIFICATIONS VISUELLES — ÉCRANS BONUS (B1-B3)

### B1 — Pose démarrée hors heure

Modale qui apparaît si tech ouvre une pose en dehors du créneau prévu
(ex. pose prévue 14h, le tech la démarre à 9h ou à 18h).

**Sections** :
1. Icône horloge orange en haut
2. Titre "Tu commences cette pose tôt / tard"
3. Sous-titre "Elle était prévue à 14h. Tu peux la faire maintenant mais elle apparaîtra comme hors créneau pour le chef."
4. 2 boutons : "✓ Oui, je continue" (orange) + "← Non, je reviens" (gris)

### B2 — Fin de journée tech

S'affiche quand le tech a fait 100% de ses poses.

**Sections** :
1. Bandeau vert plein écran avec confettis (animation simple CSS)
2. "🎉 Bravo Koffi !"
3. "Tu as fini toutes tes poses du jour (12/12)"
4. Stats journée : Temps total · Distance parcourue (si GPS) · Poses moyennes/heure
5. Bouton vert : "🏠 Retour à l'accueil"
6. Bouton secondaire : "📤 Demander une nouvelle tournée"

### B3 — Centre de notifications

Liste centralisée de toutes les notifs reçues par le tech.

**Sections** :
1. Header "Mes notifications" + bouton fermer
2. **Filtre rapide** : "Toutes" / "Photos refusées" / "Nouvelles poses"
3. **Liste chronologique** (1 ligne par notif) :
   - Icône colorée + titre + détail + heure relative
   - Point bleu si non lue
   - Tap → ouvre l'écran correspondant (T9, T2, etc.)
4. Bouton bas : "✓ Tout marquer comme lu"

---

## 6. RÈGLES VISUELLES TRANSVERSALES

### 6.1 Palette de couleurs

```css
/* Sémantiques universelles */
--c-orange-action: #ea580c;       /* MAINTENANT, en cours, attention */
--c-orange-bg: #fff7ed;
--c-orange-border: #fed7aa;
--c-orange-text: #c2410c;

--c-green-success: #16a34a;       /* Fait, validé, "Y aller", succès */
--c-green-bg: #f0fdf4;
--c-green-border: #bbf7d0;
--c-green-text: #166534;

--c-red-problem: #b91c1c;         /* Problème, refus, urgent */
--c-red-bg: #fef2f2;
--c-red-border: #fecaca;
--c-red-text: #7f1d1d;

--c-blue-info: #1e40af;           /* À venir, neutre, informationnel */
--c-blue-bg: #e0e7ff;
--c-blue-border: #c7d2fe;
--c-blue-text: #3730a3;

--c-yellow-help: #f59e0b;         /* Aide, conseil, attention douce */
--c-yellow-bg: #fef3c7;
--c-yellow-border: #fcd34d;
--c-yellow-text: #92400e;
```

### 6.2 Typo

```css
--font-family: 'Inter', -apple-system, sans-serif;
--text-xs: 11px;      /* labels, badges, sous-titres minimes */
--text-sm: 13px;      /* texte standard secondaire */
--text-base: 14px;    /* texte principal */
--text-lg: 16px;      /* titres de cards */
--text-xl: 18px;      /* titres de sections */
--text-2xl: 22px;     /* hero text */
--text-3xl: 28px;     /* KPIs gros chiffres */

--font-normal: 400;
--font-medium: 500;
--font-bold: 600;     /* uniquement pour gros chiffres */
```

### 6.3 Espacement et radius

```css
--radius-sm: 8px;     /* petits éléments */
--radius-md: 12px;    /* cards standard */
--radius-lg: 14px;    /* boutons larges */
--radius-xl: 16px;    /* modales */
--radius-full: 50%;   /* avatars, pastilles */

--space-1: 4px;
--space-2: 8px;
--space-3: 12px;
--space-4: 16px;      /* padding standard cards */
--space-5: 20px;
--space-6: 24px;      /* padding sections */
```

### 6.4 Tailles cibles tactiles

- Boutons principaux : **min 48px hauteur**, plus pour boutons critiques
- Boutons secondaires : min 44px hauteur
- Espace entre boutons : min 8px
- Cards tappables : min 56px hauteur totale

### 6.5 États interactifs

- Bouton au tap : `transform: scale(0.97)` + `transition: 0.1s`
- Card au tap : fond légèrement plus foncé
- Loading : skeleton screens, pas de spinner plein écran
- Erreur réseau : toast en bas, pas modale bloquante

### 6.6 Animations minimales (économie batterie)

- Aucune animation continue (pas de loaders infinis)
- Pulsations pour pastilles "live" : 2s ease-in-out, opacité seulement
- Transitions : 100ms à 200ms max
- Pas de parallaxe, pas de blur, pas de gradient animé

---

## 7. CHECKLIST POST-IMPLÉMENTATION

À cocher avant chaque commit important :

### Tech (SM2a)
- [ ] T1 — Carnet du jour rendu OK sur 360×640 (Android Go)
- [ ] T2 — Détail pose accessible depuis T1 et T1 bandeau refus
- [ ] T3 — Validation GPS calculée côté serveur via Haversine
- [ ] T4 — Succès affiché avec compteur correct
- [ ] T5 — Les 6 motifs cliquables ouvrent T6
- [ ] T6 — Photo optionnelle + commentaire envoyés correctement
- [ ] T7 — Mini-carte stylisée affichée (pas Leaflet, juste vignette)
- [ ] T8 — Modale aide accessible partout via bouton "?"
- [ ] T9 — Bandeau rouge s'affiche en T1 si refus pending

### Admin (SM2b)
- [ ] A1 — Polling 20s sur `/admin/dashboard/live` fonctionnel
- [ ] A1 — Bandeau notification live disparaît après 30s
- [ ] A2 — Frise chronologique correcte avec ancres temps
- [ ] A3 — Marqueurs carte mis à jour toutes les 20s
- [ ] A4 — Validation et refus créent les bonnes entrées BDD
- [ ] A5 — Vue équipe filtre les techs par `pose_team_id`

### Bonus (SM2c)
- [ ] B1 — Modale hors heure s'affiche si tech ouvre hors créneau
- [ ] B2 — Écran fin de journée si 100% des poses faites
- [ ] B3 — Centre de notifications avec filtre opérationnel

### Transverse
- [ ] Aucun appel synchrone bloquant > 500ms
- [ ] Mode offline : pages déjà chargées restent utilisables
- [ ] Polling se met en pause si onglet inactif (performance batterie)
- [ ] Tests Feature pour les 5 nouveaux endpoints
- [ ] CSS extrait dans fichier .css externe (dette SM1 résolue ?)
- [ ] CLAUDE.md règle N°1 d'harmonisation respectée (grep global avant toute modification)

---

## 8. NOTES POUR CLAUDE CODE

### 8.1 Ordre d'exécution recommandé

1. **SM2a en premier** (refonte UI tech)
   - Tu fonces sur du connu (extension de la SM1)
   - Bénéfice visible immédiatement pour les techs
   - Peu de dépendances backend nouvelles

2. **SM2b ensuite** (dashboard admin live)
   - Nouveaux endpoints + nouvelle vue admin
   - Plus de risque (polling, gestion live events)
   - À faire après que SM2a soit stable

3. **SM2c en dernier** (écrans bonus)
   - Petits raffinements
   - Aucune dépendance critique
   - Peut être skippé si manque de temps

### 8.2 Règles méthodo (rappel CLAUDE.md)

- Grep global avant toute modification touchant un concept existant
- Snapshot ground truth (5 captures PNG) avant chaque sous-mission
- Découpage en lots avec commits atomiques (max 200 lignes / commit)
- Mini-rapport tous les 2 lots
- Tests Feature pour chaque nouvelle route
- Garde-fou `node --check` après chaque extraction JS
- Garde-fou `curl` smoke test après chaque modification de route

### 8.3 Dépendances entre écrans (à respecter dans l'ordre de code)

```
T1 (accueil) → dépend de T9 (bandeau rouge intégré)
T2 (détail) → ouvre T3 (photo), T5 (signalement), T7 (Y aller)
T3 (photo prise) → ouvre T4 (succès)
T4 (succès) → ouvre T2 du prochain panneau
T5 (motif) → ouvre T6 (détails)
T9 (refus) → bouton refaire → ouvre T3

A1 (dashboard) → ouvre A2 (fiche tech), A4 (validation)
A2 (fiche tech) → ouvre A4 (validation d'une photo de ce tech)
A3 (carte) → marqueurs ouvrent A2 (fiche tech)
A5 (équipe) → cards ouvrent A2 (fiche tech)
```

---

## 9. RÉSUMÉ EXÉCUTIF

| Sous-mission | Écrans | Effort estimé | Risque |
|---|---|---|---|
| SM2a | T1-T9 (9 écrans tech) | 10-14h | Moyen |
| SM2b | A1-A5 (5 écrans admin) | 10-14h | Élevé (polling + events live) |
| SM2c | B1-B3 (3 écrans bonus) | 4-6h | Faible |
| **Total SM2** | **17 écrans** | **24-34h** | |

Entre chaque sous-mission : validation manuelle utilisateur (snapshot + tests fonctionnels).
