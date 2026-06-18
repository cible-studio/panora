# Checklist fonctionnelle tech-space — référentiel pré-refonte SM1

**Date** : 2026-06-18.
**Source** : audit du code de `resources/views/public/tech-space.blade.php` (3125 lignes) ainsi que des routes `routes/admin.php:128-179`.
**Objectif** : à la fin de chaque phase (1, 2, 3 de SM1), chacune des actions
listées ci-dessous doit toujours fonctionner à l'identique. Cocher au fur et à
mesure.

> 📸 Les captures PNG accompagnant cette checklist doivent être prises
> **manuellement par un humain** (Chrome DevTools desktop + responsive
> 360×640) avant de valider la Phase 1 — Claude n'a pas accès au navigateur.
> Stocker dans `docs/snapshots/tech-space-before-refonte/` au format
> `{viewport}-{scenario}.png` (ex. `mobile-0poses.png`, `desktop-50poses.png`).

---

## 0. Captures attendues (5 scénarios)

| Fichier attendu | URL | Description |
|---|---|---|
| `mobile-0poses.png` + `desktop-0poses.png` | `/tech/{token}/poses` (tech sans pose active) | État vide |
| `mobile-3poses.png` + `desktop-3poses.png` | `/tech/{token}/poses` (tech avec 3 poses) | Charge normale |
| `mobile-50poses.png` + `desktop-50poses.png` | `/tech/{token}/poses` (tech avec 50+ poses) | Stress test (cap SSR 200) |
| `mobile-piges.png` | `/tech/{token}/piges` | Historique photos envoyées |
| `mobile-legacy-pige.png` | `/pige/{token}` (32 ou 48 chars) | Page legacy à comparer post-redirect |

---

## 1. Header / KPIs (lignes 980-1060)

- [ ] Logo CIBLE visible en haut à gauche
- [ ] Nom du tech + agent_code visibles
- [ ] Carte KPI `À faire` affiche `totalActive` — clic = filtre `all`
- [ ] Carte KPI `Aujourd'hui` affiche `activeToday` — clic = filtre `today`
- [ ] Carte KPI `Photos` affiche `pigesSentToday` — clic = lien vers `tech.space.piges`
- [ ] Carte KPI `Zones` affiche `zonesTodayCount` — clic = scroll smooth vers première section commune
- [ ] Barre de progression à paliers (10/25/50/75/100) — palier passé en couleur accent
- [ ] Pills `📍 Tes zones du jour` affichent jusqu'à 4 noms de commune + `+N` si plus

## 2. Banner / live (lignes 1062-1070)

- [ ] Bandeau "🆕 On t'a donné un nouveau panneau" apparaît quand le heartbeat détecte un `id` > `lastKnownTaskId` côté JS
- [ ] Clic sur le bandeau = `window.location.reload()`

## 3. Controls bar (lignes 1078-1098)

- [ ] Select2 recherche affiche placeholder "🔍 Cherche un panneau, une rue, une ville…"
- [ ] Bouton `🗺 Carte` → ouvre `tech.space.map` dans la même fenêtre
- [ ] Bouton `📍 Près de moi` → demande geoloc, trie cards par distance haversine
- [ ] Bouton `🚀 Mon chemin` → POST `tech.space.optimize` puis réordonne section dédiée
- [ ] Bouton `🖨 Papier` → ouvre `tech.space.route-sheet` en nouvel onglet
- [ ] Badge `📤 N` apparaît quand IndexedDB queue contient des photos en attente — clic = `flushUploadQueue()`

## 4. Hero "Prochaine pose" (lignes ~1150-1206 + JS 2738-2807)

- [ ] La pose la plus prioritaire (retard > today > suivant) affichée en hero
- [ ] Photo de référence panneau ou icône fallback 🪧
- [ ] Référence + nom + commune + scheduled_at
- [ ] Bouton "🧭 Y aller" → Google Maps (lat/lng ou search adresse)
- [ ] Bouton caméra "📷 Prendre la photo" → ouvre caméra arrière (input file capture=environment)
- [ ] Capture photo hero → réutilise le pipeline upload des cards normales (compression + XHR + retry)

## 5. Filter chips (lignes 1208-1232)

- [ ] Chip `⏰ En retard` → masque les cards non-en-retard
- [ ] Chip `📅 Aujourd'hui` → masque les cards non-aujourd'hui
- [ ] Chip `⚠️ Avec souci` → masque les cards sans `lastProblemReport`
- [ ] Chip `🚫 Photo à refaire` → masque les cards sans `latestRejectedPige`
- [ ] Chip `🚗 En route` → masque les cards de statut ≠ en_route
- [ ] Chip `🔧 Sur place` → masque les cards de statut ≠ en_cours
- [ ] Filtres combinables (AND entre eux + entre kpi + chips status)
- [ ] Compteurs sur chaque chip mis à jour live (cards SSR matchant)
- [ ] Bouton `Tout voir` visible si au moins un filtre actif
- [ ] État stocké dans l'URL (`?late=1&today=1&...`)
- [ ] Sections commune vides masquées automatiquement
- [ ] Empty state "Aucun panneau ne correspond" si filtre ne laisse rien

## 6. Liste cards groupée par commune (lignes 1240-1393)

- [ ] Sections triées : communes contenant du retard en premier, puis plus grosses
- [ ] En-tête de section : nom commune + compteur `done/total faites` + barre progression `done/total`
- [ ] Cards triées : retard en premier, puis échéance croissante
- [ ] Bandeau rouge "🚫 Photo refusée" si `latestRejectedPige` + raison visible
- [ ] Bandeau orange "⚠ Tu as déjà dit : ..." si `lastProblemReport` + temps écoulé
- [ ] Photo thumbnail panneau ou 🪧 fallback
- [ ] Référence + nom + commune + campagne (truncate 28) + scheduled_at format `d/m à H:hi`
- [ ] Mention `⏰ En retard` si retard
- [ ] Status dot coloré (planifiee/en_route/en_cours via `PoseTaskStatus::color()`)
- [ ] Tap n'importe où sur la zone main = caméra ouverte (label englobant input file)
- [ ] Tap "🧭 Y aller" → Google Maps + bump status `planifiee → en_route` via POST `/poses/{id}/status` (puis lien suit son cours)
- [ ] Tap "📍 J'y suis" → bump `en_route → en_cours` via POST status
- [ ] Bouton J'y suis désactivé si statut déjà `en_cours` (texte devient "✓ J'y suis")
- [ ] Tap "⚠️ Souci" → ouvre modale signalement

## 7. Upload photo terrain (lignes 1913-2106)

- [ ] Sélection photo via input file (capture environment)
- [ ] Compression côté JS (canvas 2400px max + JPEG q=0.85)
- [ ] HEIC → JPEG auto si navigateur sait décoder, sinon fallback original
- [ ] Tentative geoloc avant upload (best-effort, non bloquant)
- [ ] POST `/tech/{token}/poses/{taskId}/photo` avec FormData (photo + gps_lat + gps_lng + client_uuid)
- [ ] Si pose `lastProblemReport` ouvert → modale "justifier la pige malgré signalement" avant upload
- [ ] Toast feedback `success` si HTTP 200
- [ ] Overlay plein écran "Photo envoyée !" avec vibration (40ms)
- [ ] Card status passe à `realisee` automatiquement après upload (couleur dot + label)
- [ ] Si offline → `window.queueOfflinePhoto()` → IndexedDB queue → badge `📤 N`
- [ ] Au prochain `online` event → `flushUploadQueue()` → toast résultat

## 8. Modale signalement (lignes 1410-1431 + JS 2163-2278)

- [ ] Tap "⚠️ Souci" sur une card → modale apparaît
- [ ] 9 motifs (DelayReason cases) en grille (icône + label)
- [ ] Sélection d'un motif → bouton "Envoyer au bureau" activé
- [ ] Tap envoyer → POST `/tech/{token}/poses/{taskId}/report` avec `motif`
- [ ] Toast `success` "Merci, signalé au bureau"
- [ ] Bandeau orange "⚠ Tu as déjà dit : X" affiché sur la card sans reload
- [ ] Tap "Annuler" → ferme modale sans envoyer

## 9. Heartbeat polling (lignes 1625-1700)

- [ ] Fetch `tech.space.heartbeat` toutes les 20s
- [ ] Met à jour les 4 KPI cards via `data-kpi-value` (anim flash)
- [ ] Met à jour le sub-label "X faites ✓" de la card Aujourd'hui
- [ ] Met à jour le badge de la chip "Mes piges" (rejected si > 0, sinon total)
- [ ] Détecte `lastTaskId > lastKnownTaskId` → affiche bandeau "nouvelle pose"
- [ ] Pulse le dot "live indicator"
- [ ] Polling actif uniquement quand `document.visibilityState === 'visible'`

## 10. Mode tournée TSP (lignes 2850-2986)

- [ ] Bouton "🚀 Mon chemin" → demande geoloc
- [ ] POST `tech.space.optimize` avec `lat` + `lng`
- [ ] Renvoie ordre des cards (greedy nearest-neighbor)
- [ ] Crée section dédiée `ts-tour-section` en haut + reparente les cards dans l'ordre
- [ ] Affiche `pose-tour-leg` (distance + temps estimé entre 2 cards)
- [ ] Bouton "Annuler" → restore le DOM original via `originalParentByCard` map
- [ ] Label bouton bascule `Mon chemin` / `Annuler`

## 11. Recherche Select2 (lignes 2492-2593 + 2107-2162)

- [ ] Tap dans le champ → ouvre dropdown Select2
- [ ] Saisie ≥ 1 char → AJAX `tech.space.search?q=...`
- [ ] Pagination Select2 (per_page=20)
- [ ] Sélection d'un résultat → focus modal si pas en SSR (lignes 2594-2635)
- [ ] Sinon scroll smooth vers la card

## 12. Distance haversine (lignes 2636-2737)

- [ ] Tap "📍 Près de moi" → demande geoloc
- [ ] Tri cards visibles par distance croissante
- [ ] Affiche pill `.pose-distance` "X km" sur chaque card
- [ ] Bouton bascule label "Près de moi" / "Désactiver tri distance"

## 13. PWA + Service Worker (lignes 2824-2849)

- [ ] Manifeste `tech.webmanifest` chargé
- [ ] Tag `<meta theme-color>` orange #e8a020
- [ ] Service Worker enregistré au DOMContentLoaded
- [ ] `addToHomeScreen` event capturé (bouton "Installer" si supporté)

## 14. Background Sync queue offline (lignes 2987-3120)

- [ ] IndexedDB DB `panora-tech-uploads` créée
- [ ] Object store `queue` avec keyPath `id` autoIncrement
- [ ] Badge `📤 N` se met à jour à `refreshSyncBadge()`
- [ ] `window.queueOfflinePhoto(taskId, file, gps, reason)` ajoute à la queue
- [ ] Toast "Photo gardée — on l'enverra dès que tu as du réseau"
- [ ] `flushUploadQueue()` itère, repost vers `/poses/{id}/photo`, supprime les OK, garde les KO
- [ ] Toast récap `✓ N envoyées` / `N pas encore envoyées`
- [ ] `window.addEventListener('online', flushUploadQueue)` actif

## 15. Page `/tech/{token}/piges` (vue séparée)

- [ ] Affiche liste piges du tech (filtre robuste user_id OR via pose_task)
- [ ] Par défaut : actives uniquement (pas archivées)
- [ ] Status badges (en_attente / verifie / rejete)
- [ ] Rejet : raison visible directement
- [ ] Bouton "📷 Refaire" sur les rejetées renvoie sur la card concernée

## 16. Page `/pige/{token}` (legacy)

- [ ] Token 32 chars valide → `PoseTaskPublicController::show` (page intervention 1 panneau)
- [ ] Token 48 chars valide → `PublicPigeController::show` (page campagne multi-panneaux)
- [ ] Token autre → 404
- [ ] Aucun redirect côté SM1 (le `TechUrlResolverService` est créé mais NON branché)

## 17. Sécurité / throttle

- [ ] 60 req/min sur GET `/tech/{token}/poses` (test 100 req rapide → 429 attendu)
- [ ] 30 req/min sur POST upload photo
- [ ] 10 req/min sur POST report
- [ ] `is_active=false` → 404 avec "Lien invalide, expiré, ou compte désactivé"

---

## Engagement de non-régression

Cette checklist (≈ 90 items binaires) sert de **référentiel** entre :
- Phase 1 (préparation, aucune modification visible) — passe à 100% par construction
- Phase 2 (découpage Blade en 11 partials, rendu identique) — doit passer à 100%
- Phase 3 (extraction JS en 12 fichiers modulaires, comportement identique) — doit passer à 100%

Toute déviation est une régression à investiguer/corriger avant de continuer.
