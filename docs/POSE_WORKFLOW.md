# Pose-Tasks & Techniciens — État des lieux + Brief de refonte

> Ce document a deux parties :
> - **Partie 1** : l'état actuel du système (factuel, pour comprendre l'existant).
> - **Partie 2** : un brief de mission pour repenser le système de zéro — workflow plus simple, logique claire, interface technicien ultra-accessible, capture photo native + GPS, et fonctionnalités avancées.
>
> **À l'IA qui lira ce document :** la Partie 1 décrit ce qui existe, mais tu n'es **pas tenu de la préserver**. Lis-la pour comprendre les contraintes métier réelles, puis propose la meilleure solution possible — quitte à tout réinventer. Challenge chaque choix existant.

---

# PARTIE 1 — État actuel

## 1. Acteurs & concept

- **Tâche de pose (`PoseTask`)** = ordre d'installation/collage d'**un panneau** pour **une campagne**, confié à **un technicien**.
- **Technicien** = un `User` avec `role = 'technique'` + un `whatsapp_number`. Pas de modèle séparé.
- **Media Planner / Admin** = créent, assignent, suivent, valident. Seuls eux ont accès à l'admin (policy MP/Admin only).
- **Pige** = la **photo-preuve** que le panneau est bien posé. Uploadée par le tech, validée par le MP.

## 2. Modèle de données (`pose_tasks`)

| Champ | Rôle |
|---|---|
| `panel_id`, `campaign_id` | quoi poser, pour qui |
| `assigned_user_id` | le technicien (FK users) |
| `team_name` | nom d'équipe libre (alternatif au user) |
| `tech_name_self` | nom saisi par le tech via le lien public |
| `scheduled_at`, `started_at`, `done_at` | planning / exécution |
| `status` | voir §3 |
| `progress_percent` | 0–100, piloté par le tech |
| `estimated_minutes`, `real_minutes` | mesure de durée |
| `whatsapp_sent_at` | dernière notif envoyée |
| `public_token` (32c) | lien d'intervention **spécifique à cette tâche** |

Le tech a aussi un `users.tech_public_token` (32c) = **son espace personnel permanent** (toutes ses poses).

## 3. Statuts & transitions (`PoseTaskStatus`)

```
PLANNED (planifiee) ─┬─> EN_ROUTE (en_route) ──> IN_PROGRESS (en_cours) ──> COMPLETED (realisee)
                     ├─> IN_PROGRESS                                    └──> CANCELLED (annulee)
                     └─> CANCELLED
```
- COMPLETED & CANCELLED = terminaux.
- `progress_percent` pilote les transitions auto : **1 % → IN_PROGRESS** (+ `started_at`), **100 % → COMPLETED** (+ `done_at`).

## 4. Flux actuel de bout en bout

1. **Création** — MP crée 1 ou N tâches via `/admin/pose-tasks/create` (batch multi-panneaux d'une campagne ou pose libre). → `PoseService::createBatch()` génère un `public_token` par tâche.
2. **Assignation** — choix manuel d'un technicien (ou suggestion auto basée zone 90j + charge + perf 30j, scoring 0.5/0.3/0.2).
3. **Notification auto** — `PoseTaskObserver` détecte l'`assigned_user_id` → envoie un **WhatsApp** au tech (anti-spam 60 s). Si 2+ tâches même tech×campagne → 1 seul message digest.
4. **Le tech reçoit le lien** → ouvre **son espace** `/tech/{token}/poses` (sans login) : voit toutes ses poses groupées (En retard / Aujourd'hui / Demain / Semaine / Plus tard).
5. **Exécution** — sur la fiche `/pige/{token}` : slider de progression, boutons statut (en route / en cours / terminé), **upload photo(s) pige**.
6. **Preuve** — photo uploadée → `Pige` créée en `en_attente` → `PigeObserver` envoie une **alerte au MP**. ⚠️ La tâche n'est **pas** auto-complétée : le tech clique « Marquer terminée ».
7. **Validation** — MP va dans `/admin/piges`, **valide** (`verifie`) ou **rejette**. Le rejet **relance automatiquement** la tâche en IN_PROGRESS + renvoie un WhatsApp au tech avec le motif.

## 5. Pages concernées

### Admin (`/admin/pose-tasks/*`)
| Page | Rôle |
|---|---|
| **index** | tableau principal, groupé par campagne (collapsible), filtres, KPI, **bulk** (assigner tech / équipe / replanifier / statut), polling progression 30 s, alertes « en retard » & « réalisée sans pige » |
| **create / edit** | formulaire batch (sélection panneaux virtualisée, tech, date, statut initial) |
| **show** | fiche détail : infos, bloc WhatsApp (lien tech + copier + wa.me), stats piges, actions rapides |
| **calendar** | planning hebdo technicien × jours, indicateur de charge (OK / Chargé / Surchargé) |
| **map** | carte Leaflet des poses, marqueurs colorés par statut |
| **sla** | KPI perf : taux on-time, médiane retard, validation 1ʳᵉ fois, délai validation, top techs/communes |

### Technicien (public, sans login)
| Page | Rôle |
|---|---|
| **tech-space** (`/tech/{token}/poses`) | **lien envoyé par WhatsApp** — dashboard perso, toutes ses poses, recherche |
| **pose-task** (`/pige/{token}`) | fiche mobile d'une pose : progression + statut + upload photos |
| **pige-collect** | page campagne pour uploader/valider les photos par commune |

### Navigation admin
Menu « Opérations » → **Gestion Pose OOH** + « Piges Photos ». Menu « Analyse » → **SLA**. Boutons Calendrier / Carte en haut de l'index.

## 6. WhatsApp (`WhatsAppService`)

- 2 providers : **CallMeBot** (gratuit, défaut historique) et **Twilio** (prod).
- Twilio a 2 modes : free-form (sandbox) et **Content Template** (obligatoire en prod Meta).
- **État prod en attente** : le compte Twilio est encore en Trial — à upgrader avant le sender de prod + le content template.
- 3 messages : assignation unique, assignation lot (digest), rejet + re-pige.

## 7. Sécurité des liens

- Accès **par connaissance du token** (pas d'auth), regex `[A-Za-z0-9]{32}`, throttle 60 req/min.
- **Pas d'expiration** sur les tokens pose/tech (permanents tant que la tâche/user existe).
- Toutes les actions publiques sont journalisées (`PoseTaskAction` : action + IP + nom tech).

## 8. Frictions / manques actuels

- **Deux liens parallèles** pour le tech (`/tech/{token}` espace perso *vs* `/pige/{token}` fiche unique) → confusion possible.
- **Statut `EN_ROUTE`** existe mais peu exploité dans l'UI.
- **Marquage « terminé » manuel** alors qu'une pige uploadée pourrait suffire.
- **Suggestion auto de tech** existe mais l'assignation reste surtout manuelle.
- **Aucune expiration de token** (sécurité faible si un lien fuit).
- Pas de **vue dédiée « profil technicien »** côté admin.
- Pas d'**historique/audit visuel** par tâche (données présentes dans `PoseTaskAction`, non affichées).
- **La photo n'est pas géolocalisée** : rien ne prouve que la photo a été prise *devant le panneau*.
- **L'interface tech suppose la lecture** : beaucoup de texte, peu d'icônes universelles, pas de support audio.

## 9. Fichiers clés

- Modèle : `app/Models/PoseTask.php` · Statuts : `app/Enums/PoseTaskStatus.php`
- Contrôleur admin : `app/Http/Controllers/Admin/PoseController.php`
- Contrôleurs publics : `PoseTaskPublicController.php`, `TechSpaceController.php`
- Services : `PoseService.php`, `PigeService.php`, `WhatsAppService.php`
- Observers : `PoseTaskObserver.php` (WhatsApp auto), `PigeObserver.php` (alerte MP)
- Vues admin : `resources/views/admin/poses/*` · Vues tech : `resources/views/public/{tech-space,pose-task,pige-collect}.blade.php`
- Modèle métier : `app/Models/Pige.php`, `app/Models/User.php`, `app/Models/PoseTaskAction.php`

---

# PARTIE 2 — Brief de refonte (à l'attention de l'IA)

## 🎯 Mission

Repense **de zéro** le système de gestion des poses côté **technicien terrain** et son pilotage côté **media planner**. L'objectif n'est pas d'améliorer l'existant à la marge, mais de proposer **le workflow le plus simple et le plus fiable possible**, avec une interface terrain utilisable par **n'importe qui, y compris une personne qui ne sait pas lire**.

Tu as **liberté totale** sur le workflow, les statuts, les écrans, les interactions. Tu dois seulement respecter les **contraintes métier dures** ci-dessous. Pour tout le reste : **propose mieux, justifie, et n'hésite pas à supprimer ce qui complique**.

## 🔒 Contraintes métier dures (à respecter)

1. **Une pose = un panneau physique installé pour une campagne.** La preuve d'une pose réussie est une **photo du panneau en place**.
2. Le **technicien n'a pas de compte/login** : il accède via un **lien** (WhatsApp/SMS). Garder ce principe « zéro friction d'authentification », mais le sécuriser mieux.
3. Le **media planner doit valider** la photo (ou la rejeter avec motif → le tech reprend une photo).
4. La **traçabilité** est obligatoire : qui a fait quoi, quand, où (audit conservé).
5. Le **stack est imposé** : Laravel + Blade + MySQL, mobile-first, pas de SPA lourde (PWA acceptable). WhatsApp via Twilio/CallMeBot existe déjà.

## 🟢 Liberté totale (à réinventer si pertinent)

- Le nombre et le nom des **statuts** (les 5 actuels sont peut-être trop / mal nommés).
- L'existence de **deux liens** (`/tech` vs `/pige`) — proposer un point d'entrée unique si c'est plus simple.
- La notion de **progression en %** (est-elle utile pour une pose, qui est plutôt binaire « fait / pas fait » ?).
- La **séparation pose / pige** (faut-il deux concepts ou un seul geste « je pose → je photographie → c'est fait » ?).
- Le **modèle de données** : propose les colonnes/tables que tu juges nécessaires (migrations à l'appui).
- L'**assignation** : manuelle, auto-suggérée, ou auto-optimisée (tournée géographique ?).

## 👷 Objectif UX technicien — « utilisable même sans savoir lire »

Conçois l'écran technicien pour le **plus faible dénominateur** : ouvrier sur le terrain, téléphone d'entrée de gamme, soleil, gants, peu ou pas alphabétisé, connexion 3G instable. Pistes à explorer (non exhaustif, ajoute les tiennes) :

- **Tout en icônes + couleurs + photos**, le texte est secondaire. Une tâche = la **photo du panneau cible** (pour qu'il reconnaisse visuellement où aller) + une **grosse pastille de couleur** d'état.
- **Un seul bouton géant** par étape (« 📷 Prendre la photo », « ✅ C'est posé »). Pas de formulaire.
- **Repères audio** : énoncé vocal de l'adresse/instruction (text-to-speech FR), bip de confirmation.
- **Carte / itinéraire** en un tap (ouvre Google Maps / Waze vers le panneau).
- **Mode hors-ligne** : si pas de réseau, la photo + GPS sont mis en file et synchronisés dès que ça revient (pose terrain = zones blanches fréquentes).
- **Feedback impossible à rater** : grand check vert plein écran + vibration quand c'est validé/envoyé.
- **Langue** : FR simple + éventuellement langues locales / pictogrammes.
- **Accessibilité** : très gros tap targets, fort contraste, fonctionne à une main.

## 📷 Capture photo native + GPS (fonctionnalité demandée)

Le cœur de la refonte. La photo de pige doit :

1. **Ouvrir directement l'appareil photo** du téléphone (pas le sélecteur de galerie). En web : `<input type="file" accept="image/*" capture="environment">` ouvre la caméra arrière. Évaluer une **PWA** ou `getUserMedia` pour une expérience plein écran avec overlay.
2. **Capturer le GPS au moment du déclenchement** (`navigator.geolocation.getCurrentPosition`) et l'attacher à la pige.
3. **Vérifier la cohérence géographique** : comparer le GPS de la photo aux **coordonnées connues du panneau** (le modèle `Panel` a-t-il lat/lng ? sinon, à ajouter). Lever un drapeau si la photo est prise à > X mètres du panneau (anti-fraude : empêche un tech de tout photographier depuis chez lui).
4. **Horodatage serveur** (ne pas faire confiance à l'horloge du téléphone).
5. Idéalement : **EXIF** (orientation, timestamp natif) + boussole/orientation si dispo, pour reconstituer la scène.
6. Gérer le **poids des images** (compression côté client avant upload, 3G).

## 🚀 Fonctionnalités avancées à explorer (penser large)

Propose celles qui ont du sens, en priorisant impact/effort :

- **Tournée optimisée** : ordonner les poses d'un tech par trajet géographique (problème du voyageur de commerce simplifié) → « ta prochaine pose la plus proche ».
- **Détection auto du panneau** : comparer la photo à une photo de référence du panneau / lire un QR ou code sur le mât pour confirmer le bon panneau.
- **Preuve renforcée** : photo + GPS + horodatage serveur = « certificat de pose » infalsifiable, exportable en PDF pour le client.
- **Validation assistée** côté MP : pré-tri auto (GPS OK / flou / hors-zone) pour ne faire valider à l'humain que les cas douteux.
- **Notifications bidirectionnelles** : le tech peut signaler un **problème** en 1 tap (panneau cassé, accès bloqué, mauvaise adresse) avec photo → crée une alerte MP.
- **Statut temps réel** pour le MP : carte live des techs/poses, ETA.
- **Mode équipe** : plusieurs techs sur une grosse campagne, répartition et suivi.
- **Gamification légère** : score de ponctualité, badges — pour motiver les équipes terrain.
- **Décap (fin de campagne)** : même flux inversé pour prouver le retrait du panneau (le système gère déjà un « décappage » — à intégrer au même parcours).

## ❓ Questions à te poser avant de proposer

- La progression en % est-elle pertinente, ou une pose est-elle binaire ? Quid d'une pose multi-faces / multi-jours ?
- Faut-il fusionner « PoseTask » et « Pige » en un seul geste utilisateur ?
- Comment garantir qu'une photo correspond *au bon panneau* et *au bon endroit* ?
- Comment gérer la **sécurité des liens** sans imposer de login (expiration ? lien à usage limité ? rotation ?) ?
- Comment fonctionner **hors-ligne** de façon fiable et resynchroniser sans doublon ?
- Quel est le **strict minimum d'écrans** côté tech ? (idéalement : 1 liste → 1 tâche → 1 photo → fini)

## 📦 Livrables attendus

1. **Schéma du nouveau workflow** (diagramme texte états + transitions) avec justification des choix vs l'existant.
2. **Modèle de données proposé** (tables/colonnes/migrations), en précisant ce qu'on garde, modifie, supprime de l'existant.
3. **Maquettes des écrans technicien** (ASCII/description) : liste des poses → tâche → capture photo+GPS → confirmation. Pensées « sans lecture ».
4. **Maquettes des écrans MP** : pilotage, validation assistée, carte temps réel.
5. **Spécification technique de la capture photo + GPS** (web/PWA, hors-ligne, anti-fraude géo).
6. **Plan de migration** depuis l'existant (données + bascule progressive, sans casser la prod).
7. **Liste priorisée** (MVP vs avancé) avec estimation d'effort.

> Rappel : vise la **simplicité radicale** côté terrain et la **fiabilité de la preuve** (photo + GPS + horodatage). Tout le reste est négociable.

---

# PARTIE 3 — Annexe technique (code RÉEL, ne rien supposer)

> Cette annexe donne l'état vérifié du code/DB pour que l'IA **ne réinvente pas ce qui existe** et se concentre sur les vrais manques. **Surprise importante : la capture photo native + GPS existe déjà.** Le chantier n'est donc PAS « ajouter la photo+GPS » mais « fiabiliser, vérifier et simplifier ».

## A. Schéma réel des tables (DESCRIBE en prod-staging)

### `piges` (la photo-preuve) — **GPS déjà présent**
```
id              bigint PK
panel_id        bigint NOT NULL  (FK)
campaign_id     bigint NULL      (FK)
pose_task_id    bigint NULL      (FK)
user_id         bigint NULL
photo_path      varchar(191) NOT NULL
photo_thumb     varchar(191) NULL
taken_at        timestamp NULL          ← horodatage
gps_lat         decimal(10,7) NULL      ← GPS DÉJÀ STOCKÉ
gps_lng         decimal(10,7) NULL      ← GPS DÉJÀ STOCKÉ
verified_by     bigint NULL
verified_at     timestamp NULL
notes           text NULL
status          enum('en_attente','verifie','rejete') NOT NULL
rejection_reason text NULL
archived_at     timestamp NULL
created_at / updated_at
```

### `pose_tasks` — **aucune colonne offline/sync**
```
id, panel_id(FK), campaign_id(FK), assigned_user_id(FK)
team_name varchar(50), tech_name_self varchar(100), tech_name_self_at, tech_name_self_ip varchar(45)
scheduled_at datetime NOT NULL, done_at datetime NULL, started_at timestamp NULL
whatsapp_sent_at timestamp NULL
public_token varchar(32) UNIQUE
status enum('planifiee','en_route','en_cours','realisee','annulee') NOT NULL [indexé]
progress_percent tinyint unsigned NOT NULL
estimated_minutes / real_minutes smallint NULL
notes text NULL
```
→ **Manque pour l'offline** : pas de `client_uuid` (déduplication), pas de `synced_at`, pas de file d'attente.

### `panels` — **géolocalisé**
```
... latitude decimal(10,7) NULL [indexé], longitude decimal(10,7) NULL ...
adresse, quartier, axe_routier, commune_id, zone_id ...
```
→ On a **les coordonnées du panneau** ET **les coordonnées de la photo** : tout est en place pour une **vérification de cohérence géographique** (anti-fraude) — mais elle **n'est pas codée**.

## B. `PigeStatus` (enum complet)
```
PENDING  = 'en_attente'  (⏳ orange #f97316)
VERIFIED = 'verifie'     (✅ vert  #22c55e)
REJECTED = 'rejete'      (❌ rouge #ef4444)
Transitions : PENDING → VERIFIED | REJECTED ; VERIFIED/REJECTED = terminaux.
```

## C. Capture photo + GPS — **DÉJÀ IMPLÉMENTÉE** (3 vues tech)

`pose-task.blade.php`, `tech-space.blade.php`, `pige-collect.blade.php` font toutes :

1. **Ouverture caméra arrière native** :
   ```html
   <input type="file" accept="image/*" capture="environment">
   ```
2. **Capture GPS au déclenchement** (extrait `pose-task.blade.php:1043`) :
   ```js
   navigator.geolocation.getCurrentPosition(
     pos => resolve({lat: pos.coords.latitude, lng: pos.coords.longitude}),
     ()  => resolve({lat: null, lng: null}),
     { enableHighAccuracy: true, timeout: 4000, maximumAge: 30000 }
   );
   ```
3. **Compression client avant upload** (3G-friendly) puis POST multipart avec `gps_lat`/`gps_lng`.

Backend `PoseTaskPublicController::uploadPhoto()` (l.286) :
```php
$data = $request->validate([
    'photo'   => ['required','image','mimes:jpeg,jpg,png,webp,heic,heif','max:51200'],
    'gps_lat' => 'nullable|numeric|between:-90,90',   // ← NULLABLE
    'gps_lng' => 'nullable|numeric|between:-180,180', // ← NULLABLE
    ...
]);
Pige::create([..., 'taken_at'=>now(), 'gps_lat'=>..., 'gps_lng'=>..., 'status'=>'en_attente']);
```

## D. Validation côté MP (`PigeService`)
- `verify(Pige, User)` (l.82) : lock optimiste → `status='verifie'`, `verified_by/at`.
- `reject(Pige, User, reason)` (l.115) : motif **obligatoire** → `status='rejete'`. Le rejet **relance la PoseTask en IN_PROGRESS** + renvoie un WhatsApp au tech (cf. `PigeService` + `PoseService`).

## E. ⚠️ Recentrage du brief — ce qui EXISTE vs ce qui MANQUE

| Sujet | Statut réel | Action pour l'IA |
|---|---|---|
| Ouvrir la caméra native | ✅ existe (`capture="environment"`) | **NE PAS reconstruire** — éventuellement passer en plein écran/PWA |
| Capturer le GPS | ✅ existe (`getCurrentPosition`) | fiabiliser (voir ci-dessous) |
| Stocker GPS + horodatage | ✅ `piges.gps_lat/lng/taken_at` | OK |
| Panneau géolocalisé | ✅ `panels.latitude/longitude` | exploiter pour l'anti-fraude |
| **Cohérence géo photo↔panneau** | ❌ **n'existe pas** | **VRAI CHANTIER** : haversine + seuil (ex. 100 m) → flag pige `hors_zone`, pré-tri MP |
| **GPS obligatoire** | ❌ `nullable` → photo passe sans GPS si refus/échec | décider : bloquer, ou accepter + marquer « non géolocalisée » |
| **Mode hors-ligne** | ❌ aucun (pas de sync, pas de SW) | file d'attente locale + `client_uuid` anti-doublon |
| **UX sans lecture** | ⚠️ gros boutons mais beaucoup de texte FR, pas de TTS/pictos | refonte « illettrisme » : icônes, couleurs, photo cible, voix |
| Horodatage serveur | ✅ `taken_at = now()` (serveur) | OK (ne pas faire confiance à l'horloge tel) |
| Timeout GPS 4 s | ⚠️ parfois court en zone difficile | augmenter / réessayer / indicateur de précision |

**Conclusion pour l'IA :** le socle technique photo+GPS est là. Concentre la valeur sur **(1) la vérification anti-fraude géographique** (le plus gros manque, et c'est « gratuit » côté données), **(2) le mode hors-ligne**, **(3) l'UX pour analphabètes**, et **(4) la simplification du double-lien et des statuts**.

## F. Fichiers à lire en priorité pour la refonte
- `app/Http/Controllers/PoseTaskPublicController.php` — `uploadPhoto()` l.286, `markDone()` l.224, `setStatus()` l.136
- `app/Services/PoseService.php` — `createBatch()` l.24, `notifyTechnicianOnWhatsApp()` l.433
- `app/Services/PigeService.php` — `verify()` l.82, `reject()` l.115 (re-pige auto)
- `resources/views/public/pose-task.blade.php` — capture+GPS l.866 / l.1043-1080
- `resources/views/public/tech-space.blade.php` — dashboard tech l.368 / l.408-511
- `app/Models/{PoseTask,Pige,User}.php`, `app/Enums/{PoseTaskStatus,PigeStatus,UserRole}.php`
