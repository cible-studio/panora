# Audit complet — Module Espace Technicien Panora

> **Contexte** : Panora est l'app SaaS de gestion d'affichage extérieur (OOH) de
> CIBLE SARL (régie ivoirienne). Le module "Espace Technicien" couvre le flux
> complet **attribution admin → planification terrain → pose physique → preuve
> photo (pige) → vérification superviseur**. Il est aujourd'hui jugé **trop
> compliqué par les techniciens terrain**, d'où ce dossier d'audit destiné à
> nourrir une proposition de refonte complète par un autre Claude.
>
> Stack : Laravel 12.56 · PHP 8.3 · MySQL · Blade · DomPDF · Twilio (WhatsApp).
> Date d'audit : 2026-06-18.

---

## 0. Vue d'ensemble — flux de bout en bout

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌────────────────────┐
│ ADMIN PANORA    │    │ TECHNICIEN      │    │ TECHNICIEN      │    │ SUPERVISEUR        │
│                 │    │ (WhatsApp/SMS)  │    │ (terrain mobile)│    │ (Panora admin)     │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤    ├────────────────────┤
│ 1. Crée tech    │    │                 │    │                 │    │                    │
│    /pose-tasks/ │    │                 │    │                 │    │                    │
│    techniciens  │    │                 │    │                 │    │                    │
│    /create      │    │                 │    │                 │    │                    │
│ 2. ensureTech-  │───>│ 4. Reçoit lien  │    │                 │    │                    │
│    PublicToken  │    │    /tech/{tok}/ │    │                 │    │                    │
│    (32 chars)   │    │    poses        │    │                 │    │                    │
│ 3. Crée pose    │    │                 │    │                 │    │                    │
│    /pose-tasks/ │    │                 │    │                 │    │                    │
│    /create      │    │                 │    │                 │    │                    │
│    → notifie    │    │                 │    │                 │    │                    │
│    WhatsApp     │    │                 │    │                 │    │                    │
│                 │    │                 │    │ 5. Ouvre URL    │    │                    │
│                 │    │                 │    │    /tech/{tok}/ │    │                    │
│                 │    │                 │    │    poses        │    │                    │
│                 │    │                 │    │ 6. Voit groupé  │    │                    │
│                 │    │                 │    │    par commune  │    │                    │
│                 │    │                 │    │ 7. Tap "Y aller"│    │                    │
│                 │    │                 │    │    → Google Maps│    │                    │
│                 │    │                 │    │ 8. Tap "J'y suis│    │                    │
│                 │    │                 │    │    " → status   │    │                    │
│                 │    │                 │    │    "en_cours"   │    │                    │
│                 │    │                 │    │ 9. Tap photo →  │    │                    │
│                 │    │                 │    │    capture cam  │    │                    │
│                 │    │                 │    │ 10. Auto-upload │    │                    │
│                 │    │                 │    │    → crée Pige  │    │                    │
│                 │    │                 │    │    status       │    │                    │
│                 │    │                 │    │    'en_attente' │    │                    │
│                 │    │                 │    │ 11. Geo check : │    │                    │
│                 │    │                 │    │    distance     │    │                    │
│                 │    │                 │    │    panneau ↔    │    │                    │
│                 │    │                 │    │    photo (anti- │    │                    │
│                 │    │                 │    │    fraude)      │    │                    │
│                 │    │                 │    │                 │    │ 12. Voit pige      │
│                 │    │                 │    │                 │    │     dans /admin/   │
│                 │    │                 │    │                 │    │     piges/         │
│                 │    │                 │    │                 │    │     index          │
│                 │    │                 │    │                 │    │ 13. "Vérifier" ou  │
│                 │    │                 │    │                 │    │     "Rejeter"      │
│                 │    │                 │    │                 │    │ 14. Si rejet :     │
│                 │    │                 │    │                 │    │     motif visible  │
│                 │    │                 │    │                 │    │     pour le tech   │
│                 │    │                 │    │ 15. Bandeau     │    │                    │
│                 │    │                 │    │    rouge "photo │    │                    │
│                 │    │                 │    │    refusée" →   │    │                    │
│                 │    │                 │    │    refaire      │    │                    │
│                 │    │                 │    │ 16. Pose mark   │    │                    │
│                 │    │                 │    │    "réalisée"   │    │                    │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └────────────────────┘
```

---

## 1. Acteurs et URLs publiques

### 1.1 Le technicien
- **Pas de compte Laravel classique** — n'utilise jamais `/login`
- Un user `role='technique'` est créé dans `users` mais avec un mot de passe random jamais utilisé
- L'email peut être auto-généré (`tech_xxxx@cible-ci.com`) si non fourni
- Code agent auto-généré au format `TT-001`, `TT-002`… (préfixe par rôle, cf. `User::generateAgentCode()`)
- Lien personnel permanent : `/tech/{tech_public_token}/poses` (token 32 chars dans `users.tech_public_token`)

### 1.2 URLs publiques tech
| Route name | URL | Type | Throttle | Description |
|---|---|---|---|---|
| `tech.space` | GET `/tech/{token}/poses` | Page | 60/min | Dashboard tech principal (liste des poses) |
| `tech.space.piges` | GET `/tech/{token}/piges` | Page | 60/min | Historique des photos envoyées |
| `tech.space.heartbeat` | GET `/tech/{token}/heartbeat` | JSON | 60/min | Polling 20s pour KPIs live |
| `tech.space.search` | GET `/tech/{token}/poses/search` | JSON | 60/min | Recherche AJAX paginée (Select2) |
| `tech.space.route-sheet` | GET `/tech/{token}/poses/route-sheet` | Page A4 | 60/min | Feuille de route imprimable |
| `tech.space.map` | GET `/tech/{token}/poses/map` | Page | 60/min | Carte Leaflet + cluster |
| `tech.space.optimize` | GET `/tech/{token}/poses/optimize` | JSON | 60/min | TSP nearest-neighbor (greedy) |
| `tech.space.status` | POST `/tech/{token}/poses/{task}/status` | JSON | 60/min | Changement statut (en_route, en_cours, etc.) |
| `tech.space.photo` | POST `/tech/{token}/poses/{task}/photo` | JSON | 30/min | Upload photo terrain → crée Pige |
| `tech.space.report` | POST `/tech/{token}/poses/{task}/report` | JSON | 10/min | Signalement problème (souci) |

### 1.3 URLs publiques pige (legacy + cohabitation)
Un **second système** coexiste : `/pige/{token}` où `token` peut être :
- **32 chars** → résout vers une `pose_tasks.public_token` (intervention sur 1 panneau précis)
- **48 chars** → résout vers une `campaigns.pige_token` (legacy : voir tous les panneaux d'une campagne)

| Route name | URL | Controller | Description |
|---|---|---|---|
| `pige.public.show` | GET `/pige/{token}` | `PublicPigeController::show` | Dispatcher → essaie PoseTask, fallback Campaign |
| `pige.public.intervention.update` | POST `/pige/{token}/update` | `PoseTaskPublicController::update` | Met à jour `progress_percent` |
| `pige.public.intervention.done` | POST `/pige/{token}/done` | `PoseTaskPublicController::markDone` | Marque tâche réalisée |
| `pige.public.intervention.photo` | POST `/pige/{token}/photo` | `PoseTaskPublicController::uploadPhoto` | Crée Pige |
| `pige.public.intervention.photo.replace` | POST `/pige/{token}/photo/{pigeId}/replace` | idem | Remplace photo refusée |
| `pige.public.intervention.photo.delete` | DELETE `/pige/{token}/photo/{pigeId}` | idem | Supprime pige (avant vérification) |
| `pige.public.intervention.status` | POST `/pige/{token}/status` | `setStatus` | Changement statut générique |
| `pige.public.intervention.report` | POST `/pige/{token}/report` | `reportProblem` | Signalement terrain |
| `pige.public.upload` | POST `/pige/{token}/upload` | `PublicPigeController::upload` | Upload pige (mode campagne) |
| `pige.public.posed` | POST `/pige/{token}/posed` | `PublicPigeController::markPosed` | Marque "pose effectuée" panneau (mode campagne) |
| `pose.public.show` | GET `/pose/{token}` | redirect 301 → `/pige/{token}` | Rétrocompat anciens liens WhatsApp |

→ **Dette technique majeure** : 2 systèmes UX coexistent (`tech.space.*` ET `pige.public.intervention.*`), avec une logique partagée mais 2 vues mobiles différentes (`tech-space.blade.php` 3125 lignes et `pose-task.blade.php` 1492 lignes). Les techs reçoivent parfois l'un, parfois l'autre selon le contexte d'envoi WhatsApp.

---

## 2. Structure base de données

### 2.1 Table `users` (extrait tech)

```sql
CREATE TABLE users (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(191) NOT NULL,
    email           VARCHAR(191) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL, -- inutilisé pour les techs (random)
    role            ENUM('admin','commercial','mediaplanner','technique','comptable') NOT NULL,
    agent_code      VARCHAR(50) UNIQUE,    -- format TT-001 pour technique
    is_active       BOOLEAN DEFAULT 1,
    whatsapp_number VARCHAR(20),           -- format international sans + (ex: 2250707070707)
    tech_public_token VARCHAR(32) UNIQUE,  -- généré via User::ensureTechPublicToken()
    pose_team_id    BIGINT UNSIGNED REFERENCES pose_teams(id) ON DELETE SET NULL, -- M2 (juin 2026)
    created_at, updated_at, deleted_at
);
```

### 2.2 Table `pose_tasks` (migration originale + 10 ALTER successives)

```sql
CREATE TABLE pose_tasks (
    id                BIGINT UNSIGNED PRIMARY KEY,
    panel_id          BIGINT UNSIGNED NOT NULL REFERENCES panels(id) ON DELETE CASCADE,
    campaign_id       BIGINT UNSIGNED REFERENCES campaigns(id) ON DELETE SET NULL,
    assigned_user_id  BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    team_name         VARCHAR(50),  -- libre, snapshot du nom d'équipe au moment de l'attribution
    scheduled_at      DATETIME NOT NULL,
    done_at           DATETIME,
    status            ENUM('planifiee','en_route','en_cours','realisee','annulee') DEFAULT 'planifiee',
    notes             TEXT,
    -- Ajouts ultérieurs :
    progress_percent  INT DEFAULT 0,
    estimated_minutes INT,
    real_minutes      INT,
    started_at        DATETIME,
    whatsapp_sent_at  DATETIME,
    public_token      VARCHAR(32) UNIQUE, -- pour /pige/{token}, généré via PoseTask::ensurePublicToken()
    -- Identité tech saisie en page publique (cas tech non créé en User) :
    tech_name_self     VARCHAR(100),
    tech_name_self_at  DATETIME,
    tech_name_self_ip  VARCHAR(45),
    created_at, updated_at
);
```

### 2.3 Table `piges` (preuves photo terrain)

```sql
CREATE TABLE piges (
    id              BIGINT UNSIGNED PRIMARY KEY,
    panel_id        BIGINT UNSIGNED NOT NULL REFERENCES panels(id) ON DELETE CASCADE,
    campaign_id     BIGINT UNSIGNED REFERENCES campaigns(id) ON DELETE SET NULL,
    pose_task_id    BIGINT UNSIGNED REFERENCES pose_tasks(id) ON DELETE SET NULL,
    user_id         BIGINT UNSIGNED REFERENCES users(id) ON DELETE CASCADE,
    photo_path      VARCHAR(255) NOT NULL,
    photo_thumb     VARCHAR(255),
    gps_lat         DECIMAL(10,7),
    gps_lng         DECIMAL(10,7),
    geo_distance_m  INT,  -- distance EXIF photo ↔ panel.lat/lng en mètres
    geo_check       ENUM('ok','warn','out','no_gps','no_panel_gps'),
    taken_at        DATETIME NOT NULL,
    verified_at     DATETIME,
    verified_by     BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    status          ENUM('en_attente','verifie','rejete') DEFAULT 'en_attente',
    rejection_reason VARCHAR(500),
    notes           TEXT,
    client_uuid     VARCHAR(36),  -- pour idempotence côté upload retry
    archived_at     DATETIME,     -- soft delete logique (campagne supprimée)
    created_at, updated_at
);
```

### 2.4 Table `pose_task_actions` (audit/log)

Trace les actions sur une PoseTask (status_changed, progress_updated, problem_reported, photo_uploaded, motif_modified). Utilisée pour le module **SLA & Retards** (`/admin/sla/retards`) qui analyse les motifs de retard. Schema :

```sql
CREATE TABLE pose_task_actions (
    id              BIGINT UNSIGNED PRIMARY KEY,
    pose_task_id    BIGINT UNSIGNED NOT NULL REFERENCES pose_tasks(id) ON DELETE CASCADE,
    action          VARCHAR(50) NOT NULL, -- 'status_changed', 'problem_reported', 'progress_updated'...
    payload         JSON,                  -- ['old_status' => 'planifiee', 'new_status' => 'en_route', ...]
    actor           VARCHAR(255),          -- nom du tech (saisi en page publique si pas d'user)
    ip              VARCHAR(45),
    motif           ENUM(...) -- DelayReason enum (9 valeurs)
    resolved_at     DATETIME,
    maintenance_id  BIGINT UNSIGNED REFERENCES maintenances(id) ON DELETE SET NULL,
    created_at, updated_at
);
```

### 2.5 Tables connexes

- `panels` : référentiel des panneaux (référence, nom, GPS, photos, format, commune…)
- `panel_photos` : photos de référence d'un panneau (utilisées en miniature côté tech)
- `campaigns` : campagnes publicitaires (lient client + panneaux + dates)
- `campaign_panels` : pivot N-N
- `pose_teams` : équipes de pose (Bloc M2 — juin 2026), 1 leader + N membres

---

## 3. Modèle PoseTask (logique métier critique)

### 3.1 Hiérarchie d'identité tech

```php
public function technicianDisplay(): array
{
    // 1. Tech assigné formellement (User Panora)
    if ($this->technicien) return ['name' => $u->name, 'type' => 'user', 'color' => '#16a34a'];
    // 2. Saisi en page publique (sans assigned_user_id)
    if ($this->tech_name_self) return ['name' => ..., 'type' => 'declared', 'color' => '#3b82f6'];
    // 3. Équipe (chaîne libre, snapshot du PoseTeam.name au moment de l'attribution)
    if ($this->team_name) return ['name' => ..., 'type' => 'team', 'color' => '#8b5cf6'];
    return ['name' => '— Non assigné —', 'type' => 'none', 'color' => '#9ca3af'];
}
```

### 3.2 Observer auto-sync `team_name` (M2)

```php
protected static function booted(): void
{
    static::saving(function (PoseTask $task) {
        // Quand assigned_user_id change, copie le User.poseTeam->name dans team_name
        // (snapshot historique préservé — si l'équipe change plus tard, l'ancienne
        // tâche garde son ancien team_name)
        if ($task->isDirty('assigned_user_id') && $task->assigned_user_id) {
            $user = User::with('poseTeam:id,name')->find($task->assigned_user_id);
            if ($user?->poseTeam) {
                $task->team_name = $user->poseTeam->name;
            }
        }
    });
}
```

### 3.3 Machine à états (PoseTaskStatus enum)

```php
PLANNED → EN_ROUTE / IN_PROGRESS / CANCELLED
EN_ROUTE → IN_PROGRESS / CANCELLED
IN_PROGRESS → COMPLETED / CANCELLED
COMPLETED → terminal
CANCELLED → terminal
```

Validé par `PoseTaskStatus::allowedTransitions()` dans le controller `setStatus`.

---

## 4. Contrôleurs côté tech

### 4.1 `TechSpaceController` (1399 lignes — module principal)

Méthodes publiques :
- `show($token)` : page dashboard (`tech-space.blade.php`)
- `searchPoses($req, $token)` : recherche AJAX paginée (Select2)
- `routeSheet($token)` : feuille de route A4 imprimable
- `map($token)` : vue carte Leaflet
- `optimizeTour($req, $token)` : TSP nearest-neighbor (greedy, JSON)
- `updateStatus($req, $token, $taskId)` : changement statut depuis cards
- `heartbeat($token)` : JSON polling 20s (KPIs live)
- `piges($req, $token)` : historique photos (page `tech-piges.blade.php`)
- `uploadPhoto($req, $token, $taskId)` : crée une Pige (multipart)
- `report($req, $token, $taskId)` : signalement terrain (motif DelayReason)

Cœur de `show()` — méthode `buildPayload(User $tech)` :
1. Charge poses non-terminales du tech avec eager-load lourd (`panel`, `panel.commune`, `panel.format`, `panel.photos`, `campaign`, `campaign.client`, `lastProblemReport`, `latestRejectedPige`)
2. Cap SSR à **200 cartes** (config `tech_space.ssr_cap`) au-delà la recherche AJAX prend le relais
3. Calcule `isLate` / `isToday` / groupage par commune
4. KPIs : `totalActive`, `totalDone`, `progressPct`, `doneByCommune`, `zonesTodayCount`, `pigesSentToday`, `pigesRejected`
5. Détermine `nextTask` (focus card en haut) : retard d'abord, puis aujourd'hui, puis prochain
6. Retourne payload géant ~15 clés

### 4.2 `PoseTaskPublicController` (641 lignes — version `/pige/{token}` legacy unifiée)

Méthodes :
- `show($token)` : page `pose-task.blade.php`
- `update($req, $token)` : update `progress_percent`
- `setStatus($req, $token)` : changement statut
- `markDone($req, $token)` : marque terminé
- `uploadPhoto($req, $token)` : crée Pige
- `replacePhoto($req, $token, $pigeId)` : remplace photo refusée
- `deletePhoto($req, $token, $pigeId)` : supprime pige (avant verif)
- `reportProblem($req, $token)` : signalement

### 4.3 `PublicPigeController` (258 lignes — dispatcher + mode campagne legacy)

Méthodes :
- `show($token)` : dispatcher — essaie PoseTask (32 chars) avant Campaign (48 chars)
- `upload($req, $token)` : upload multi-panneaux (mode campagne)
- `markPosed($req, $token)` : marque "pose effectuée" panneau

---

## 5. Pages mobile (Blade publiques)

### 5.1 `public/tech-space.blade.php` — 3125 lignes

Surface monstrueuse. Contient :
- Header sticky avec logo + `progress-staged-track` (paliers 10/25/50/75/100%)
- 4 KPI cards cliquables (À faire / Aujourd'hui / Photos / Zones) — filtrent la liste
- TOC zones (chips sticky)
- "Next pose" focus card (la plus prioritaire mise en haut)
- Controls bar : recherche Select2 + carte + "près de moi" + "mon chemin" + papier
- Filtres chips : late / today / problem / reject / en_route / en_cours
- Liste cards regroupées par commune (`day-section`) avec :
  - Bandeau "photo refusée" (rouge) si `latestRejectedPige`
  - Bandeau "déjà signalé" si `lastProblemReport`
  - Photo de référence du panneau (thumbnail)
  - Référence + nom + commune + campagne
  - Status dot coloré
  - Actions : Y aller (Google Maps) · J'y suis · Souci
  - Camera input direct sur tap (capture environment)
- Modale signalement (motifs DelayReason : 9 valeurs)
- Bandeau "nouvelle pose assignée" (heartbeat)
- Service Worker (PWA) + manifeste pour install écran d'accueil
- JavaScript inline ~1500 lignes : Select2, géolocalisation (haversine), TSP, upload XHR, retry queue offline (localStorage), polling heartbeat, gestion filtres, photo capture + preview, modale signalement, etc.

### 5.2 `public/pose-task.blade.php` — 1492 lignes

Version "1 panneau précis" reçue via `/pige/{token}` :
- Topbar sombre
- Carte du panneau + photo de référence
- Bouton "Marquer pose effectuée"
- Boutons signalement
- Galerie photos déjà envoyées + status par photo
- Slider progress (0-100%)
- Note texte libre

**Doublon UX énorme** avec tech-space card mais isolé sur 1 task.

### 5.3 `public/tech-piges.blade.php` — 422 lignes

Historique des photos envoyées :
- Cards par photo avec status (en_attente / verifié / rejeté)
- Motif de rejet visible direct
- Lien pour re-uploader si rejeté
- Pagination

### 5.4 `public/tech-map.blade.php` — 482 lignes

Vue carte Leaflet :
- Markers colorés par statut (planifiée jaune, en route violet, en cours bleu, etc.)
- Cluster (markerCluster.js)
- Popups avec actions "Y aller" + "Voir la pose"
- Filtres en bas (statut + commune)

### 5.5 `public/tech-route-sheet.blade.php` — 304 lignes

Feuille de route A4 imprimable :
- Groupée par commune
- 1 carte par panneau (ref, nom, adresse, GPS, scheduled_at, statut)
- Photo référence panneau
- Optimisée print (CSS media print)
- Filet offline : tech peut imprimer chez lui le matin et travailler sans réseau

---

## 6. Côté admin — attribution + suivi

### 6.1 Pages admin tech

| URL | Controller | Description |
|---|---|---|
| `/admin/pose-tasks` | `PoseController::index` | Liste poses (filtres : status, technicien, équipe, campagne, dates) |
| `/admin/pose-tasks/create` | `PoseController::create` | Création multi-panneaux |
| `/admin/pose-tasks/{id}` | `PoseController::show` | Détail pose |
| `/admin/pose-tasks/{id}/edit` | `PoseController::edit` | Édition |
| `/admin/pose-tasks/map` | `PoseController::map` | Carte admin |
| `/admin/pose-tasks/calendar` | `PoseController::calendar` | Calendrier semaine |
| `/admin/pose-tasks/suggest-tech` | `PoseController::suggestTech` | Suggestion auto |
| `/admin/pose-tasks/techniciens` | `TechnicienController::index` | Sous-module techniciens |
| `/admin/pose-tasks/techniciens/create` | idem | Création tech (génère token public + agent_code TT-XXX) |
| `/admin/pose-tasks/techniciens/{id}/edit` | idem | Édition tech (incl. `pose_team_id` depuis 2026-06-18) |
| `/admin/pose-tasks/techniciens/{id}/regenerate-token` | idem | Rotation token (invalide ancien lien) |
| `/admin/piges` | `PigeController::index` | Validation/rejet des photos terrain |
| `/admin/piges/{id}/verify` | idem | Marque vérifiée |
| `/admin/piges/{id}/reject` | idem | Marque rejetée + motif (visible au tech) |
| `/admin/sla/retards` | `SlaDelaysController::index` | Analyse motifs de retard (9 DelayReason) |
| `/admin/signalements` | `SignalementController::index` | Signalements terrain ouverts |
| `/admin/teams` | `PoseTeamController` | CRUD équipes de pose |
| `/admin/performance/techniciens` | `TechnicianPerformanceController` | Leaderboard tech |
| `/admin/performance/equipes` | `TeamPerformanceController` | Leaderboard équipes |

### 6.2 Création d'un technicien (flow admin)

`TechnicienController::store` :
1. Valide `name` (required), `email`/`whatsapp_number`/`agent_code` (optionnels), `pose_team_id` (optionnel)
2. Si email vide → auto-génère `tech_{random}@cible-ci.com` (contrainte UNIQUE)
3. Si agent_code vide → `User::generateAgentCode('technique')` → `TT-001`, `TT-002`…
4. Crée User avec password random (jamais utilisé)
5. `$tech->ensureTechPublicToken()` → génère le token 32 chars
6. Redirige vers liste avec flash "Technicien créé. Lien public généré."

### 6.3 Attribution d'une pose

`PoseService::createBatch($data, User $creator)` :
1. Valide campagne (si fournie) : pas terminale + panneaux appartiennent à la campagne
2. Pour chaque panel_id :
   - Vérifie qu'il n'existe pas déjà une PoseTask non-annulée (sinon warning)
   - Crée PoseTask avec `assigned_user_id`, `team_name`, `scheduled_at`, `status='planifiee'`
   - `$task->ensurePublicToken()` immédiatement (pour pouvoir envoyer le lien WhatsApp)
3. Envoi WhatsApp best-effort post-commit :
   - Si 2+ tâches pour le même tech sur la même campagne → message digest UNIQUE avec lien campagne
   - Sinon → message détaillé avec lien `/pige/{public_token}`
4. Notification AlertService côté admin

### 6.4 Vérification des photos (admin)

`PigeController` : page `/admin/piges` avec filtres (status / panel / campagne / commune / tech).

Pour chaque pige : afficher photo, comparer aux photos de référence du panel, vérifier `geo_check` (badge cohérence GPS), bouton "✓ Vérifier" ou "✗ Rejeter" (avec motif texte). Si rejeté → le motif apparaît automatiquement au tech sur sa page `/tech/{token}/poses` dans un bandeau rouge "Photo refusée" + bouton pour re-uploader.

---

## 7. Sécurité

### 7.1 Auth token-only (pas de login)

- Le `tech_public_token` (32 chars random alphanumeric) sert de **secret partagé**
- Aucun cookie de session côté tech
- Throttle Laravel route-level :
  - Pages GET : 60 req/min (dont heartbeat polling 20s)
  - Upload photo : 30 req/min
  - Signalement : 10 req/min
- Le compte désactivé (`is_active=false`) bloque l'accès immédiatement (vérification dans `show()`)
- Rotation token : bouton admin `regenerateToken` invalide l'ancien lien (tech doit recevoir le nouveau lien par WhatsApp)
- CSRF token classique pour tous les POST (meta head + axios/XHR auto)

### 7.2 Anti-fraude photo

- À l'upload : extraction EXIF GPS de la photo (si dispo)
- Calcul distance haversine vers `panels.latitude/longitude`
- Stocké dans `piges.gps_lat`, `gps_lng`, `geo_distance_m`
- `geo_check` enum :
  - `ok` : distance ≤ seuil OK (50m typique)
  - `warn` : 50-200m
  - `out` : > 200m
  - `no_gps` : pas d'EXIF dans la photo
  - `no_panel_gps` : pas de GPS sur le panneau de référence
- Badge affiché au superviseur dans `/admin/piges` pour faciliter la vérif

### 7.3 Limites historiques

- **Token dans WhatsApp** : si le tech perd son téléphone ou si quelqu'un voit l'écran, il peut accéder à toutes les poses. Atténué par : rotation manuelle + désactivation compte + throttle.
- **Pas de TLS pinning** : le tech sur réseau hostile peut voir requêtes inspectées (mais HTTPS standard).
- **EXIF GPS** facile à truquer par un tech avec ses outils — pas une vraie protection anti-fraude, juste un signal au superviseur.

---

## 8. Notifications WhatsApp (Twilio)

`PoseService::notifyTechnicianOnWhatsApp(PoseTask $task)` envoie un message tradi :

```
Bonjour [Tech Name],

Tu as une nouvelle pose à réaliser :
📢 Campagne : [Campaign Name]
📍 [Panel reference] — [Commune]
📅 Prévue : [scheduled_at format d/m H:i]

Lien pour la pose :
https://panora-cible.com/pige/[public_token]
```

Et `notifyTechnicianBatch($tasks)` pour le digest multi-poses (lien unique vers `/tech/{tech_public_token}/poses`).

**État Twilio** : compte en Trial (sandbox), prod en pause. Cf. CLAUDE.md + memory `project_whatsapp_prod.md`. Ne pas activer sans upgrade Twilio + sender + content template.

---

## 9. JavaScript côté tech (highlights)

### 9.1 Upload photo offline-resilient

Le tech terrain est souvent en 2G/edge ou sans réseau. Le `tech-space.blade.php` implémente :
- Capture caméra via `<input type="file" capture="environment">` (mobile uniquement)
- Compression côté client (Canvas, resize 1920px max + JPEG 80%)
- Upload XHR avec retry queue dans `localStorage` (clé `ts-upload-queue`)
- Badge "📤 N à envoyer" en topbar quand des photos sont en attente
- Tentative auto-renvoi quand le réseau revient (event `online`)
- Indicateur visuel sur la card "Envoi en cours…" / "Envoyé ✓" / "Erreur, on réessaiera"

### 9.2 Géolocalisation + TSP

- Bouton "Près de moi" : `navigator.geolocation` → tri haversine côté JS
- Bouton "Mon chemin" : POST `/tech/{token}/poses/optimize` avec lat/lng → serveur calcule nearest-neighbor greedy → renvoie ordre optimal → cards réordonnées

### 9.3 Polling heartbeat

- Toutes les 20s, GET `/tech/{token}/heartbeat`
- Retour JSON : `{totalActive, activeToday, doneToday, pigesSentToday, hasNewTask}`
- Si `hasNewTask` → bandeau "🆕 On t'a donné un nouveau panneau" + son optionnel
- Met à jour KPIs en douceur (transition CSS)

### 9.4 PWA

- Manifeste `tech.webmanifest`
- Service Worker (?) — à vérifier si réellement implémenté
- Permet d'installer sur écran d'accueil iOS/Android comme une app
- Splash screen + theme color orange `#e8a020` (couleur CIBLE)

---

## 10. Points de friction UX identifiés

### 10.1 Surface trop grande
- `tech-space.blade.php` = **3125 lignes** dans une seule vue Blade (mélange HTML/CSS/JS inline)
- Beaucoup de filtres (6 chips), 4 KPI cards, controls bar (5 boutons), TOC zones, focus card "next pose", liste groupée par commune avec 2 bandeaux possibles par card, 3 boutons d'action par card…
- **Cognitive overload** pour un tech qui veut juste : voir où aller, prendre la photo, signaler un problème
- Tech sur téléphone bas de gamme (Android Go, écran 4.7") = scroll infini

### 10.2 Doublon `/tech/{token}/poses` vs `/pige/{token}`
- Selon comment l'admin envoie le lien (création unitaire = pige token / batch = tech token), le tech reçoit 2 UX différentes
- `pose-task.blade.php` = focus 1 panneau (UI sombre, "intervention")
- `tech-space.blade.php` = liste multi (UI claire, "dashboard")
- **Le même tech peut alterner entre les 2** dans la même journée

### 10.3 Hiérarchie d'identité confuse
- `assigned_user_id` vs `tech_name_self` vs `team_name` — 3 façons d'identifier qui fait la pose
- Le `tech_name_self` est saisi en page publique sans validation → typos, faux noms
- `team_name` est un **snapshot** texte au moment de l'attribution (pas une FK) → si l'équipe est renommée, l'ancienne pose garde l'ancien nom

### 10.4 Workflow photo confus
- Photo upload → crée Pige `en_attente`
- Admin doit vérifier dans `/admin/piges` (page séparée, pas une notif évidente)
- Si rejet → motif visible côté tech via bandeau rouge MAIS le tech doit savoir aller sur sa page pour le voir (pas de push notif WhatsApp automatique sur rejet)
- Pas de système de "feedback" sur l'acceptation (le tech ne sait pas si sa photo est OK avant que l'admin la valide manuellement)

### 10.5 Signalement terrain peu mis en valeur
- Bouton "⚠️ Souci" sur chaque card = ajoute une `pose_task_actions` avec motif DelayReason
- Mais ça n'arrête pas la pose, c'est juste un signal au superviseur
- Tech ne sait pas si l'admin a traité son signalement (pas de retour)
- La modale signalement liste 9 motifs en GRILLE → trop de choix pour un mobile

### 10.6 Pas de mode hors-ligne complet
- L'upload retry queue fonctionne, mais si le tech ouvre la page sans réseau pour la première fois → page blanche
- Aucun cache du payload initial dans le SW
- Filet offline = seulement la "feuille de route" papier imprimable (manuellement)

### 10.7 Trop d'options de tri/filtrage
- 6 filter chips, 4 controls bar buttons, recherche Select2, géolocalisation, TSP
- Pour un tech qui a 5 poses dans sa journée, c'est over-engineered
- Pour un tech qui a 200+ poses, c'est ce qui sauve

→ **Pas d'adaptation au nombre de poses** : la UI est la même pour 3 ou 300 poses.

### 10.8 Pas de tutorial / onboarding
- Le tech ouvre le lien WhatsApp pour la première fois → 3125 lignes de UI à digérer
- Aucun popup "comment ça marche" / "vos 3 actions principales"
- Aucune capture des préférences (ordre tri par défaut, notif son ON/OFF…)

---

## 11. Dette technique connue

Sources : `docs/TECHNICAL_DEBT.md` + `CLAUDE.md` + code comments.

### 11.1 Migrations non sqlite-portable
- ~20 migrations utilisent `ALTER TABLE … MODIFY COLUMN` ou `SHOW INDEX` MySQL-specific
- Conséquence : Feature tests `RapportsFilterTest`, `CaRealServiceConsistencyTest`, `M2TeamAndPerfTest` → `markTestSkipped` sur sqlite (driver par défaut de PHPUnit)
- Action prévue : mission séparée pour rendre toutes les migrations sqlite-portable

### 11.2 CA réel non filtrable géographiquement
- Le `CaRealService` (juin 2026) ignore commune/zone/category car la facturation suit le client, pas le panneau
- Pas d'impact sur le module tech directement

### 11.3 Coexistence `/pige/{token}` vs `/tech/{token}/poses`
- Reconnue mais non priorisée. Le ménage demanderait :
  - Migration des envois WhatsApp historiques pour pointer tous vers `/tech/{tech_public_token}/poses`
  - Refonte `pose-task.blade.php` en une vue détail intégrée dans `tech-space.blade.php` (drawer ?)
  - Garder `/pige/{token}` côté serveur pour rétrocompat URLs déjà envoyées

### 11.4 Pas d'authentification forte
- Token-only sans 2FA, sans confirmation device
- Acceptable pour Trial mais à revoir si la régie veut scaler à 50+ techs

### 11.5 Service Worker incomplet
- PWA manifest présent mais SW pas finalisé (à vérifier)
- Pas de cache de la coquille HTML/CSS pour offline first paint
- Pas de push notification native (sortie WhatsApp Twilio à la place)

---

## 12. Métriques actuelles (mesurées sur prod)

À demander à l'utilisateur si refonte budgétée :
- Nombre moyen de poses par tech par jour : ?
- Nombre moyen de poses actives en même temps par tech : ? (cap SSR à 200 utile ?)
- Taux de rejet des photos par les superviseurs : ?
- Taux de signalements terrain : ?
- % de techs qui utilisent vraiment la carte (`tech-map`) : ?
- % de techs qui utilisent la feuille de route papier : ?
- Temps moyen entre `started_at` et `done_at` par pose : ?

---

## 13. Stack technique précis

```
Backend  : Laravel 12.56.0 / PHP 8.3.31 / MySQL 8 (prod) / SQLite (tests)
Frontend : Blade · Vanilla JS + CSS inline (pas de Vue/React)
Mobile   : PWA (manifest présent, SW à vérifier) — iOS Safari + Android Chrome
Cartes   : Leaflet 1.9 + Leaflet.markercluster
Search   : Select2 v4 AJAX paginé
HTTP     : Axios (XHR upload photo), fetch (heartbeat polling)
WhatsApp : Twilio Conversations API (sandbox Trial, prod en pause)
Storage  : Disk 'public' (photos panneau + piges)
Image    : Intervention\Image (GD driver) — resize + compress upload
GPS      : EXIF parsing manuel côté PHP (extension exif requise)
Auth tech: Token 32 chars permanent dans users.tech_public_token (pas de login)
Auth admin: Sessions Laravel classiques + 2FA optionnel (UserController)
Roles    : enum UserRole {admin, commercial, mediaplanner, technique, comptable}
Tests    : PHPUnit (Unit + Feature) — skip sqlite si migrations MySQL-only
Logs     : Stack file Laravel + audit owen-it/laravel-auditing (modèles métier)
```

---

## 14. Briefing pour la refonte

### Ce qui marche bien (à conserver)
- Token permanent → pas de friction login pour le tech
- Auto-capture caméra arrière sur tap
- Groupement par commune (cohérent avec la tournée terrain)
- Bandeau "photo refusée" avec motif direct
- Throttle bien dimensionné
- Retry queue offline pour upload

### Ce qui doit changer (priorités)
1. **Réduire la surface visuelle** — 3125 lignes Blade est ingérable. Découper en partials ou refondre en SPA (Inertia/Livewire/Vue ?)
2. **Unifier `/pige/` et `/tech/`** — choisir UNE URL canonique, déprécier l'autre
3. **Adapter à la charge** — UI minimaliste pour < 10 poses, UI riche pour > 50
4. **Onboarding 1ère ouverture** — coach mark / popup intro
5. **Notifications inverse** — push WhatsApp / SMS sur rejet photo + sur traitement signalement
6. **Service Worker complet** — offline first paint + cache photos référence panneau
7. **Hiérarchie identité tech simplifiée** — privilégier `assigned_user_id`, déprécier `tech_name_self` ou le valider strictement
8. **Workflow signalement → action** — quand le tech signale, l'admin doit avoir un trigger automatique de re-planification ou maintenance

### Contraintes incontournables
- Le tech terrain a parfois **un téléphone bas de gamme** (Android Go, 1 Go RAM, 720p) → JS lourd KO
- Le tech a parfois **edge / 2G** sur certaines communes (intérieur) → offline first
- Le tech ne sait pas forcément lire le français → icônes + couleurs > texte
- Le superviseur veut une **traçabilité forte** (audit log + geo_check + EXIF) → pas question de simplifier la pige
- Twilio en Trial → pas de migration massive WhatsApp dans cette refonte (cf. mémoire)

---

## 15. Fichiers clés à investiguer en profondeur

| Fichier | Lignes | Description |
|---|---|---|
| `app/Http/Controllers/TechSpaceController.php` | 1399 | Dashboard tech moderne `/tech/{token}/poses` |
| `app/Http/Controllers/PoseTaskPublicController.php` | 641 | Page detail pige `/pige/{token}` |
| `app/Http/Controllers/PublicPigeController.php` | 258 | Dispatcher + mode campagne legacy |
| `app/Http/Controllers/Admin/PoseController.php` | 880+ | CRUD pose tasks côté admin |
| `app/Http/Controllers/Admin/TechnicienController.php` | 175+ | CRUD techniciens (sous-module pose-tasks) |
| `app/Http/Controllers/Admin/PigeController.php` | ? | Vérification piges |
| `app/Services/PoseService.php` | 600+ | createBatch + bulkUpdate + WhatsApp |
| `app/Models/PoseTask.php` | 400+ | Modèle métier central |
| `app/Models/Pige.php` | 180 | Modèle pige (preuve photo) |
| `app/Models/User.php` | 200+ | User incl. `ensureTechPublicToken()` |
| `app/Enums/PoseTaskStatus.php` | ~80 | Machine à états |
| `app/Enums/DelayReason.php` | ~120 | 9 motifs signalement (M3 SLA) |
| `resources/views/public/tech-space.blade.php` | **3125** | Vue dashboard tech (à refondre) |
| `resources/views/public/pose-task.blade.php` | 1492 | Vue page 1 pige (à refondre / merger) |
| `resources/views/public/tech-piges.blade.php` | 422 | Historique photos |
| `resources/views/public/tech-map.blade.php` | 482 | Vue carte Leaflet |
| `resources/views/public/tech-route-sheet.blade.php` | 304 | Feuille de route A4 |
| `routes/admin.php` (lignes 40-180) | — | Routes publiques tech + pige |
| `database/migrations/2026_03_11_153339_create_pose_tasks_table.php` + 9 alter | — | Schéma pose_tasks |
| `database/migrations/2026_03_11_153339_create_piges_table.php` + 5 alter | — | Schéma piges |
| `database/migrations/2026_05_21_162251_add_tech_public_token_to_users.php` | 42 | Token public tech |

---

**Fin du dossier d'audit. Ce document est destiné à être lu par une autre instance Claude pour proposer une refonte ergonomique et technique du module Espace Technicien.**
