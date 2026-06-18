# REFONTE ESPACE TECHNICIEN PANORA — Plan complet

> 📍 Document de référence pour la refonte du module Espace Technicien.
> Basé sur l'audit complet (704 lignes) du 2026-06-18 et 3 décisions
> structurantes validées avec l'utilisateur :
>
> - **Décision 1** : Unifier vers `/tech/{token}/poses` + redirect
>   301 depuis `/pige/{token}`
> - **Décision 2** : Stack Blade découpée en partials + JS extrait en
>   fichiers séparés (pas de Livewire/Vue)
> - **Décision 3** : UI minimaliste par défaut + révélation
>   progressive des options avancées

---

## 1. Philosophie de la refonte

### Le principe directeur : "1 tech, 1 écran, 3 actions"

Un tech terrain qui ouvre son lien WhatsApp doit voir **immédiatement** :

1. **Où aller maintenant** (1 carte focus, pas une liste de 200)
2. **Comment y aller** (1 bouton vert "Y aller" → Google Maps)
3. **Comment prouver qu'il y est** (1 bouton orange "Photo" → caméra)

Tout le reste (filtres, carte, TSP, historique, paramètres) est **accessible mais pas visible par défaut**.

### Les 4 règles non négociables

| Règle | Conséquence |
|---|---|
| **Mobile-first vrai** | Test sur Android Go (1 Go RAM) en 2G. Si ça rame, c'est rejeté. |
| **Offline-first vrai** | Page chargée 1 fois = utilisable 8h sans réseau (sauf upload photo). |
| **Lecture sans français** | Icônes + couleurs primaires. Texte = secondaire. |
| **Zéro choix initial** | Le tech ne configure rien. L'UI s'adapte à son usage. |

### Adaptation au volume — révélation progressive (Décision 3)

Pas de "switch d'UI" entre simple et avancé (source de bugs). Le principe :

- **Toujours** afficher la "Focus card" en haut (= la prochaine pose à faire)
- **Toujours** afficher la liste des poses du jour (groupées par commune)
- **Cacher par défaut** les options avancées (carte, TSP, recherche, filtres)
- **Révéler** via un bouton "+" discret qui ouvre un panneau d'outils

Effet pratique :
- Un tech avec 3 poses : ne voit que la Focus card + 3 lignes en dessous. Net.
- Un tech avec 200 poses : voit la Focus card + 20 lignes paginées + bouton "+" en évidence (parce que là, les outils avancés deviennent utiles).

Le serveur sait combien de poses le tech a sur la journée et adapte la mise en avant du bouton "+" (badge animé si > 30 poses).

---

## 2. Architecture cible

### 2.1 URL unifiée

Une seule URL canonique côté tech : `/tech/{token}/poses`

- `/pige/{token}` → **redirect 301** vers la même URL (qui résout en interne le token PoseTask → token User → page tech)
- `/pose/{token}` → idem (rétrocompat déjà en place)
- Les WhatsApp historiques continuent de fonctionner via les redirects

### 2.2 Découpage de la vue Blade

Au lieu d'un fichier `tech-space.blade.php` de 3125 lignes, on crée une vue squelette de ~150 lignes qui inclut des partials :

```
resources/views/public/tech/
├── index.blade.php                    [~150 l] Vue squelette + JS bootstrap
├── partials/
│   ├── _topbar.blade.php              [~60 l] Header + KPIs progress
│   ├── _focus_card.blade.php          [~120 l] Carte "prochaine pose"
│   ├── _pose_list.blade.php           [~80 l] Liste groupée par commune
│   ├── _pose_card.blade.php           [~150 l] Une ligne de pose (réutilisable)
│   ├── _drawer_tools.blade.php        [~100 l] Panneau outils avancés
│   ├── _drawer_history.blade.php      [~80 l] Drawer historique photos
│   ├── _drawer_detail.blade.php       [~140 l] Drawer détail d'une pose
│   ├── _modal_report.blade.php        [~90 l] Modale signalement
│   ├── _modal_photo.blade.php         [~70 l] Modale après capture photo
│   └── _banner_*.blade.php            [~30 l each] Bandeaux (rejet, news)
├── route-sheet.blade.php              [conservée — feuille de route A4]
└── map.blade.php                      [conservée — carte Leaflet]
```

### 2.3 Découpage du JavaScript

Au lieu de ~1500 lignes inline, on crée :

```
resources/js/tech/
├── tech-app.js                        Point d'entrée + bootstrap
├── core/
│   ├── api.js                         Helpers fetch + CSRF
│   ├── state.js                       État global (Proxy + localStorage)
│   ├── offline.js                     Détection réseau + retry queue
│   └── sw-register.js                 Service Worker registration
├── features/
│   ├── upload.js                      Capture photo + compression + upload
│   ├── geolocate.js                   Près de moi + TSP
│   ├── heartbeat.js                   Polling 20s
│   ├── search.js                      Recherche AJAX paginée
│   ├── drawer.js                      Open/close drawers (Web Components ?)
│   └── report.js                      Signalement modal
└── sw.js                              Service Worker complet
```

**Avantage clé** : chaque fichier ≤ 200 lignes, testable indépendamment, cache navigateur efficace. **Pas de bundler nécessaire** au démarrage (les fichiers sont servis tels quels par Laravel), bundler Vite optionnel en phase 4 si besoin.

### 2.4 Controllers — pas de big bang

On **garde** `TechSpaceController` (1399 lignes) mais on **extrait progressivement** :

- `TechSpaceController` reste le point d'entrée des routes
- Délégation vers des services :
  - `TechPosesService` : `getActivePoses($tech)`, `getFocusPose($tech)`, `getDailyStats($tech)`
  - `TechPhotoService` : `uploadAndCreatePige($task, $request)` (déjà en partie isolé)
  - `TechReportService` : `submitReport($task, $request)` (déjà en partie isolé)
  - `TechHeartbeatService` : `buildHeartbeatPayload($tech)`

Réutilisation maximale du code existant. **Pas de réécriture aveugle.**

---

## 3. Maquettes ASCII des écrans cibles

### 3.1 Écran principal — "Focus mode" (par défaut)

```
╔════════════════════════════════════════════════╗
║  ▣ Panora    Bonjour Koffi      [📤 0]  [⚙]    ║  ← topbar 56px
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║  ← progress bar tendue (jaune→vert)
║                                                ║
║  ┌──────────────────────────────────────────┐  ║
║  │  PROCHAINE POSE                          │  ║
║  │                                          │  ║
║  │  ┌─────┐  PAN-002847                    │  ║  ← thumbnail panneau
║  │  │ 📷  │  Carrefour Yopougon-Niangon   │  ║
║  │  └─────┘  Coca-Cola · 4×3 m            │  ║
║  │                                          │  ║
║  │  ┌──────────────┐  ┌──────────────┐     │  ║
║  │  │ 🗺 Y ALLER   │  │ 📷 PHOTO     │     │  ║  ← 2 boutons MASSIFS (56px)
║  │  └──────────────┘  └──────────────┘     │  ║
║  │                                          │  ║
║  │  [⚠ Souci]   [✓ Déjà fait]             │  ║  ← actions secondaires petites
║  └──────────────────────────────────────────┘  ║
║                                                ║
║  AUJOURD'HUI · 5 RESTANTES                     ║  ← titre section discret
║                                                ║
║  📍 Yopougon                                   ║  ← chip commune sticky
║  ┌──────────────────────────────────────────┐  ║
║  │ ⬤ PAN-002848  Carrefour Yopo. Toits     │  ║  ← ligne ultra-compacte
║  │ ⬤ PAN-002849  Bd Yopougon Avocatier      │  ║
║  └──────────────────────────────────────────┘  ║
║                                                ║
║  📍 Cocody                                     ║
║  ┌──────────────────────────────────────────┐  ║
║  │ ⬤ PAN-002851  II Plateaux Vallons        │  ║
║  │ ⬤ PAN-002852  Riviera Bonoumin           │  ║
║  │ ⬤ PAN-002853  Cocody Centre              │  ║
║  └──────────────────────────────────────────┘  ║
║                                                ║
║                              [   + Outils   ]  ║  ← FAB révélation drawer
╚════════════════════════════════════════════════╝

Légende status dot ⬤ :
  🟡 planifiée   🟣 en route   🔵 en cours   🟢 réalisée
```

**Différences clés vs l'existant** :
- ✅ Plus de focus card "next pose" mais agrandie et avec **2 boutons massifs** (au lieu de 3 boutons moyens dans une chip line)
- ✅ Liste des autres poses **ultra-compacte** (1 ligne = 1 pose) au lieu de cards riches
- ✅ Bouton "+ Outils" en FAB en bas à droite (révélation progressive — Décision 3)
- ✅ Plus de filtres chips visibles par défaut (ils sont dans le drawer Outils)
- ✅ KPIs réduits à une **progress bar tendue** en haut (jaune → vert progression journée)

### 3.2 Drawer "Outils" (ouvert)

```
╔════════════════════════════════════════════════╗
║  ╳ OUTILS                                      ║
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║
║                                                ║
║  🔍 Rechercher une pose…                       ║  ← input recherche
║                                                ║
║  ┌──────────────┐  ┌──────────────┐            ║
║  │ 📍 Près moi  │  │ 🛣 Mon chemin│            ║  ← géoloc + TSP
║  └──────────────┘  └──────────────┘            ║
║                                                ║
║  ┌──────────────┐  ┌──────────────┐            ║
║  │ 🗺 Carte     │  │ 🖨 Feuille  │            ║  ← carte + impression
║  └──────────────┘  └──────────────┘            ║
║                                                ║
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║
║  FILTRER                                       ║
║                                                ║
║  [⏰ En retard] [📅 Aujourd'hui]                ║
║  [⚠ Avec souci] [🔴 Photo refusée]              ║
║  [⊘ Annuler filtres]                            ║
║                                                ║
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║
║  📷 Mes photos envoyées (12)                   ║  ← lien historique
║  ⓘ  Aide / Tutoriel                            ║  ← onboarding
╚════════════════════════════════════════════════╝
```

Drawer slide-up depuis le bas. Toutes les options actuelles sont là, mais **invisibles par défaut**. Un tech qui n'en a pas besoin n'est jamais dérangé par ces options.

### 3.3 Drawer "Détail pose" (tap sur une ligne de la liste)

```
╔════════════════════════════════════════════════╗
║  ╳ DÉTAIL                                      ║
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║
║                                                ║
║  ┌─────────────────┐                          ║
║  │                 │                          ║
║  │  📷 Photo réf.  │                          ║
║  │                 │                          ║
║  └─────────────────┘                          ║
║                                                ║
║  PAN-002848                                    ║
║  Carrefour Yopougon Toits Rouges               ║
║  Yopougon · 4×3 m                              ║
║                                                ║
║  📢 Campagne : Coca-Cola Coupe du Monde        ║
║  📅 Prévue : Mar 18 juin 14:00                 ║
║  📍 GPS : 5.34521, -4.02431                    ║
║                                                ║
║  ┌──────────────┐  ┌──────────────┐            ║
║  │ 🗺 Y ALLER   │  │ 📷 PHOTO     │            ║
║  └──────────────┘  └──────────────┘            ║
║                                                ║
║  [⚠ Signaler un souci]                         ║
║                                                ║
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║
║  HISTORIQUE                                    ║
║  ─ 17/06 14:32  Photo envoyée (en attente)     ║
║  ─ 17/06 14:15  Pose démarrée                  ║
║  ─ 16/06 09:00  Attribué à toi                 ║
╚════════════════════════════════════════════════╝
```

**Remplace** la page séparée `/pige/{token}` : tout est dans le drawer du dashboard. Plus de friction navigationnelle.

### 3.4 Modale photo (après capture)

```
╔════════════════════════════════════════════════╗
║                                                ║
║  PHOTO PRISE                                   ║
║                                                ║
║  ┌──────────────────────────────────────────┐  ║
║  │                                          │  ║
║  │      [preview photo prise]               │  ║
║  │                                          │  ║
║  └──────────────────────────────────────────┘  ║
║                                                ║
║  Vérifications :                               ║
║  ✓ GPS dans la photo                           ║
║  ✓ Distance au panneau : 23 m (OK)             ║
║                                                ║
║  ┌──────────────┐  ┌──────────────┐            ║
║  │ ✗ Refaire    │  │ ✓ Envoyer    │            ║
║  └──────────────┘  └──────────────┘            ║
║                                                ║
║  Note (optionnel) :                            ║
║  ┌──────────────────────────────────────────┐  ║
║  │                                          │  ║
║  └──────────────────────────────────────────┘  ║
╚════════════════════════════════════════════════╝
```

**Nouveauté** : avant envoi, le tech voit les vérifications GPS. Si "out" (>200m) → bandeau rouge "Tu as l'air loin du panneau, vérifie que c'est le bon". Évite des allers-retours superviseur.

### 3.5 Modale signalement (refonte de la grille 9 motifs)

```
╔════════════════════════════════════════════════╗
║  ╳ SIGNALER UN SOUCI                           ║
║  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   ║
║                                                ║
║  Quel est le souci sur ce panneau ?            ║
║                                                ║
║  ┌──────────────────────────────────────────┐  ║
║  │ 🔨  Panneau cassé / abîmé                │  ║  ← 1 motif = 1 ligne PLEINE
║  ├──────────────────────────────────────────┤  ║
║  │ 🚧  Accès bloqué / impossible            │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ 📍  Mauvaise adresse / introuvable       │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ 🙅  Technicien absent                    │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ 📭  Matériel indisponible                │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ ☔  Météo défavorable                    │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ ⏱  Retard impression                    │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ 📝  Retard validation client             │  ║
║  ├──────────────────────────────────────────┤  ║
║  │ …  Autre (préciser ci-dessous)           │  ║
║  └──────────────────────────────────────────┘  ║
║                                                ║
║  Commentaire (optionnel) :                     ║
║  ┌──────────────────────────────────────────┐  ║
║  │                                          │  ║
║  └──────────────────────────────────────────┘  ║
║                                                ║
║  [        ✓ Envoyer le signalement        ]    ║
╚════════════════════════════════════════════════╝
```

**Refonte clé** : remplace la grille de 9 chips (cognitive overload sur mobile) par une **liste de 9 lignes pleines** (1 tap = 1 motif). Plus accessible, plus tactile.

---

## 4. Améliorations fonctionnelles ciblées

Au-delà de la refonte visuelle, 6 améliorations importantes (pas du nice-to-have, du **vraiment utile**).

### 4.1 Feedback photo immédiat
- **Maintenant** : tech upload → attend que l'admin vérifie. Aucun retour.
- **Après** : pré-validation côté serveur immédiate :
  - GPS de la photo cohérent avec le panneau ? (geo_check existant)
  - Photo nette (basique : check taille fichier > 50 KB, dimensions > 400px) ?
  - Pas une photo dupliquée d'une pose précédente ? (hash perceptuel léger)
- Si tout est OK → message vert "Photo bien reçue, en attente de validation"
- Si problème détecté → bandeau orange "⚠ Vérifie : GPS éloigné. Tu peux quand même envoyer ou refaire."
- **Bénéfice** : le tech évite 30-50% des rejets en se corrigeant lui-même.

### 4.2 Notification WhatsApp inverse (sur rejet)
- **Maintenant** : si l'admin rejette une photo, le tech ne sait que quand il rouvre sa page.
- **Après** : WhatsApp automatique envoyé via Twilio :
  ```
  ⚠ Photo refusée — PAN-002848
  Motif : photo floue, panneau pas centré.
  Retourne sur le lien pour refaire.
  ```
- **Contrainte Twilio Trial** : déjà identifiée dans l'audit. À activer SEULEMENT quand Twilio passe en prod. En attendant, badge en topbar `[🔴 1 photo refusée]` qui apparaît au prochain refresh / heartbeat.

### 4.3 Onboarding 1ère ouverture
- **Maintenant** : le tech ouvre 3125 lignes d'UI sans aide.
- **Après** : à la 1ère ouverture (détectée via flag `localStorage`), 3 popups successifs en plein écran :
  1. "Voici ta prochaine pose. Tape 'Y aller' pour ouvrir Google Maps."
  2. "Quand tu es sur place, tape 'Photo'. Ta caméra s'ouvre."
  3. "Si ça ne va pas, tape '⚠ Souci' et choisis pourquoi."
- Bouton "Compris" sur chaque, possible de revoir via Drawer Outils > Aide.

### 4.4 Service Worker complet (offline first paint)
- **Maintenant** : SW manifest présent mais incomplet (audit).
- **Après** :
  - Cache de la coquille HTML/CSS/JS (stratégie "Cache, falling back to network")
  - Cache des thumbnails panneaux des poses actives
  - File d'attente upload offline (déjà existante en localStorage)
  - Au prochain ouverture sans réseau → écran chargé instantanément avec données de la dernière session
- **Bénéfice** : un tech qui ouvre son lien dans un endroit sans réseau (interieur CI) peut quand même voir où il va.

### 4.5 Auto-marquage "en route"
- **Maintenant** : tech doit taper "Y aller" puis "J'y suis". Double action.
- **Après** : taper "Y aller" change automatiquement le statut en `en_route`. Pas besoin de revenir confirmer.
- Le statut `en_cours` (= sur place, en train de poser) reste manuel via... la capture photo elle-même. Quand le tech ouvre la caméra → `en_cours` auto. Quand il valide la photo → `realisee` auto si pas de problème.
- **Bénéfice** : 2 tags manuels en moins par pose. Sur 200 poses/mois × 30 sec gagnées = 100 min/mois économisées.

### 4.6 Confirmation simple sans WhatsApp (Twilio Trial)
- **Maintenant** : sur rejet photo, pas de notif (Twilio Trial).
- **Après** :
  - Polling heartbeat existant (déjà 20s) sert aussi à détecter rejet → badge `[🔴]` apparaît
  - **+ Service Worker** : envoi d'une notif Web Push si le tech a installé la PWA (gratuit, indépendant de Twilio)
  - Pas obligatoire mais bonus pour les techs équipés

---

## 5. Plan de migration progressif (6 phases)

**Principe** : à chaque phase, l'app reste 100% fonctionnelle. Si la phase échoue, on revient en arrière sans utilisateurs impactés.

### Phase 1 — Préparation (4-6h)
**Objectif** : préparer le terrain sans casser l'existant.

1. Créer la nouvelle structure de dossiers (`resources/views/public/tech/`, `resources/js/tech/`)
2. Mettre en place le redirect 301 `/pige/{token}` → résout vers `/tech/{user_token}/poses` après lookup
3. Bootstrap minimal du nouveau `tech-app.js` (mais pas encore utilisé)
4. Tests Feature : tous les anciens parcours passent toujours
5. **Snapshot ground truth** : capturer 5 scénarios actuels (admin envoie pige, tech upload, rejet, signalement, etc.)

→ **Risque** : 0. Aucun code prod modifié. ✅

### Phase 2 — Découpage Blade en partials (8-10h)
**Objectif** : passer de 3125 lignes à un squelette + partials, sans changer le rendu.

1. Extraire `_topbar`, `_focus_card`, `_pose_card`, `_pose_list`, `_drawer_*`, `_modal_*`
2. La vue principale `index.blade.php` devient un squelette qui include les partials
3. Le rendu visuel doit être **strictement identique** avant/après
4. Comparaison automatisée : screenshot Puppeteer/Playwright avant/après (sinon visuelle manuelle sur 5 pages)
5. Tests Feature inchangés, tous passent

→ **Risque** : faible. C'est un refactor pur, validation visuelle obligatoire. ✅

### Phase 3 — Extraction JS en fichiers séparés (6-8h)
**Objectif** : passer des ~1500 lignes inline à des fichiers modulaires.

1. Créer `tech-app.js` + modules dans `core/` et `features/`
2. Migration par feature : upload d'abord, heartbeat, search, geolocate, drawer, report
3. Charger les JS dans `index.blade.php` via balises classiques (pas de bundler Vite obligatoire)
4. Versionnement via `?v={app.version}` pour invalider le cache navigateur
5. Tests : aucune régression fonctionnelle. Validation manuelle sur 5 scénarios.

→ **Risque** : moyen. Les events JS sont sensibles. Tests manuels approfondis. ⚠️

### Phase 4 — Refonte UI Focus mode (10-14h)
**Objectif** : appliquer les nouvelles maquettes (Focus card massive, liste compacte, drawer Outils, modale photo enrichie).

1. Refonte `_focus_card.blade.php` selon maquette 3.1
2. Refonte `_pose_card.blade.php` en version compacte (1 ligne au lieu d'une card riche)
3. Création `_drawer_tools.blade.php` (carte, TSP, recherche, filtres, historique)
4. Création `_drawer_detail.blade.php` (remplace la page `/pige/{token}` legacy)
5. Création `_modal_photo.blade.php` enrichie avec pré-validation GPS
6. Refonte modale signalement (liste verticale au lieu de grille)
7. Tests utilisateur réels : 2 techs CIBLE utilisent la nouvelle UI pendant 2 jours, feedback consigné

→ **Risque** : élevé. C'est là que les techs vont vraiment voir un changement. Phase critique. ⚠️⚠️

### Phase 5 — Service Worker complet + onboarding (6-8h)
**Objectif** : offline-first vrai + 1ère ouverture guidée.

1. SW complet (`sw.js`) : cache shell + photos panneaux actifs
2. Tests offline réels (DevTools → Network throttling → Offline)
3. Onboarding 3 popups au flag localStorage `tech_first_seen`
4. Bouton "Revoir l'aide" dans Drawer Outils
5. Tests Feature : flag localStorage isole bien le comportement

→ **Risque** : faible. Le SW peut être désactivé en 1 commit si problème. ✅

### Phase 6 — Auto-statuts + feedback photo + finitions (4-6h)
**Objectif** : améliorations UX fines (4.1 + 4.5).

1. Auto-statut `en_route` au tap "Y aller"
2. Auto-statut `en_cours` à l'ouverture caméra
3. Auto-statut `realisee` si photo validée serveur sans warning
4. Pré-validation photo : taille, dimensions, hash perceptuel (lib `php-image-hash` ou équivalent)
5. Bandeaux contextuels selon résultat
6. Rapport final + checklist tests utilisateurs

→ **Risque** : moyen. Toucher à la machine à états demande de bien re-vérifier les transitions valides. ⚠️

---

## 6. Estimation totale & ROI

| Phase | Heures | Risque | Bénéfice immédiat |
|---|---|---|---|
| 1 — Préparation | 4-6 h | 0 | Aucun (préparatoire) |
| 2 — Découpage Blade | 8-10 h | Faible | Lisibilité dev, pas vu par tech |
| 3 — Extraction JS | 6-8 h | Moyen | Idem |
| 4 — Refonte UI Focus | 10-14 h | Élevé | ★★★★★ — gros |
| 5 — SW + onboarding | 6-8 h | Faible | ★★★ |
| 6 — Auto-statuts + feedback | 4-6 h | Moyen | ★★★ |
| **TOTAL** | **38-52 h** | | |

**ROI estimé** :
- Sur 200 poses/mois × 30 sec/pose économisées (auto-statuts + UI plus rapide) = **100 min/mois** par tech
- Sur 30-50% de rejets photos en moins (pré-validation) = **15-25 photos/mois** retraitées en moins par l'admin
- Onboarding 3 popups = formation tech divisée par 3 (passage de "1h coaching" à "20 min self-service")

→ **Retour sur investissement environ 3 mois** pour une équipe de 8-10 techs.

---

## 7. Contraintes & règles d'or

### 7.1 Contraintes Twilio Trial (à respecter scrupuleusement)
- **Pas** d'envoi WhatsApp massif depuis l'app pendant cette refonte (déjà en pause)
- Toute notification ajoutée doit être **déclenchable manuellement** ou via heartbeat, pas via Twilio
- Garder Twilio "ready" pour le jour où prod sera activée (template messages préparés mais désactivés)
- L'amélioration 4.2 (WhatsApp inverse sur rejet) est **planifiée mais désactivée par défaut** (flag config `tech.notify_on_reject` = false)

### 7.2 Téléphones bas de gamme (Android Go 1 Go RAM)
- **Pas** de bibliothèque JS lourde ajoutée (Leaflet 1.9 reste, mais on ne lui ajoute pas de plugins)
- **Pas** de framework SPA (Vue/React/Livewire) — la décision 2 est ferme
- Bundle JS final < 50 KB gzippé sur la home tech (sans le mode carte qui charge Leaflet à la demande)
- Tests obligatoires sur un vrai Android Go ou équivalent émulé (Chrome DevTools throttling)

### 7.3 Edge / 2G fréquente (intérieur CI)
- SW first paint < 1 seconde après installation
- Pas d'images > 100 KB sur le shell initial (thumbnails ≤ 30 KB)
- Retry queue offline préservée (existante) et améliorée si besoin

### 7.4 Règles d'harmonisation (CLAUDE.md règle N°1)
- Chaque feature touchée = grep global pour trouver tous les endroits concernés
- Pas de "j'ai changé un truc ici, j'oublie là"
- Source unique de vérité par concept (status, identité tech, motifs retard)

### 7.5 Snapshot ground truth entre chaque phase
- Capturer les chiffres clés (nb poses actives, nb piges, nb signalements) avant chaque phase
- Comparer après la phase → 0 dérive accepté

---

## 8. Décisions ouvertes à clarifier APRÈS validation du plan

À ne pas trancher avant que la patronne / le terrain ait validé les maquettes 3.1 à 3.5 :

1. **Auto-marquage "en cours" à l'ouverture caméra** : confort pour le tech, mais peut piéger un tech qui ouvre la caméra par erreur. Garder le tap explicite "J'y suis" ?
2. **Mode jour/nuit** : les techs travaillent parfois tôt le matin. Un mode sombre serait apprécié. À budgéter en phase 6 ou plus tard ?
3. **Multi-language** : interface 100% icônes peut éviter le besoin, mais "Y aller", "Photo", "Souci" restent en français. Garder ou ajouter une couche i18n ?
4. **Préférences tech** (filtres par défaut, ordre tri) : stockées en `localStorage` ou en BDD via un nouveau champ `users.tech_preferences` JSON ?

---

## 9. Recommandation finale d'exécution

**Ne pas tout lancer en une mission Claude Code.** Découper en 3 sous-missions :

- **Sous-mission 1** : Phases 1-2-3 (préparation + découpage Blade + extraction JS).
  → ~18-24 h. **Zero impact visible côté tech.** Refactor pur.
  → Livré stable, tests Feature passent, snapshot OK.

- **Sous-mission 2** : Phase 4 (refonte UI Focus mode).
  → ~10-14 h. **Impact visible côté tech.** Tests utilisateurs réels sur 2 techs CIBLE pendant 2 jours.
  → Si feedback OK → merge. Sinon → ajustements.

- **Sous-mission 3** : Phases 5-6 (SW + onboarding + auto-statuts + feedback photo).
  → ~10-14 h. **Améliorations fines.** Activables progressivement (feature flags).

Entre chaque sous-mission : pause de 1 à 2 semaines pour observation en conditions réelles. Un dev tout seul ne devrait pas faire toute la refonte d'affilée — c'est un marathon, pas un sprint.
