# MISSION CLAUDE CODE — Refonte Espace Technicien · Sous-mission 1
# Refactor pur : préparation + découpage Blade + extraction JS

> 📍 Ce brief s'applique en complément du `CLAUDE.md` à la racine du
> projet (RÈGLE N°1 harmonisation globale). Tu DOIS suivre la
> procédure de listing AVANT de modifier quoi que ce soit.

> 🎯 PRINCIPE ABSOLU : cette sous-mission ne change AUCUN comportement
> visible côté tech. C'est un refactor pur. Le rendu visuel doit
> rester pixel à pixel identique. Si un seul détail change, c'est
> une régression.

> 📚 RÉFÉRENCES :
> - Audit complet : voir le document partagé par l'utilisateur
>   (AUDIT_ESPACE_TECHNICIEN.md — 704 lignes)
> - Plan de refonte complet : REFONTE_ESPACE_TECHNICIEN.md
> - Cette sous-mission couvre **Phases 1, 2 et 3** du plan global.

---

## 🎯 Décisions structurantes (NE PAS re-questionner)

Ces 3 décisions ont déjà été tranchées avec l'utilisateur :

1. **URL unifiée** vers `/tech/{token}/poses`. La route `/pige/{token}`
   et `/pose/{token}` deviennent des redirects 301 vers la nouvelle
   URL canonique.

2. **Stack frontend** : Blade découpé en partials + JS extrait en
   fichiers séparés. **Pas** de Livewire, **pas** de Vue, **pas** de
   bundler Vite obligatoire pour cette sous-mission.

3. **Adaptation au volume** : la révélation progressive (focus card
   par défaut + drawer Outils) sera implémentée en **Sous-mission 2**.
   Pour cette SM1, le rendu reste strictement identique à
   aujourd'hui.

---

## 📋 Objectif clair des 3 phases

### Phase 1 — Préparation (4-6h)
Mettre en place la structure cible **sans toucher au code existant**.

### Phase 2 — Découpage Blade (8-10h)
Passer `tech-space.blade.php` (3125 lignes) à un squelette +
~11 partials, rendu visuel identique.

### Phase 3 — Extraction JS (6-8h)
Passer les ~1500 lignes JS inline à 12 fichiers modulaires,
comportement identique.

**Total estimé : 18-24h.** À découper en 3 batches avec validation
utilisateur entre chaque.

---

## 🔍 PHASE 1 — Préparation (NE MODIFIE RIEN VISIBLE)

### Étape 1.1 — Audit complémentaire du JS inline

L'audit fourni dit *"JavaScript inline ~1500 lignes"* mais ne détaille
pas la structure interne. Tu dois cartographier précisément :

1. **Compter exactement les lignes JS** dans `tech-space.blade.php`
   (entre `<script>` et `</script>`).
2. **Identifier les blocs logiques** : upload, heartbeat, geoloc,
   TSP, search Select2, drawer, modale signalement, retry queue,
   etc.
3. **Lister les dépendances inter-blocs** : qui appelle quoi ?
   (utile pour découper sans casser).
4. **Identifier les variables globales** (window.X, var partagé
   entre blocs) — ce sera le piège principal lors de l'extraction.

Tu rends un mini-rapport :

```
=== AUDIT JS INLINE tech-space.blade.php ===

Total lignes JS : X
Blocs identifiés :
  1. Bootstrap / DOM ready (lignes X-Y) : init, event listeners globaux
  2. Upload photo (lignes X-Y) : capture, compression, XHR, retry queue
  3. Heartbeat polling (lignes X-Y) : fetch 20s, update KPIs
  4. Géolocalisation (lignes X-Y) : navigator.geolocation, haversine
  5. TSP "Mon chemin" (lignes X-Y) : POST optimize, réordonnancement
  6. Search Select2 (lignes X-Y) : init Select2, AJAX paginé
  7. Filtres chips (lignes X-Y) : show/hide cards par critère
  8. Modale signalement (lignes X-Y) : grille motifs, submit
  9. Service Worker registration (lignes X-Y) : si présent
  10. ... (autres)

Variables globales identifiées :
  - window.X (utilisée par : ...)
  - var uploadQueue (utilisée par : ...)
  - ...

Dépendances inter-blocs :
  - Upload → Heartbeat (notifie KPI photos envoyées)
  - Filtres → Cards DOM (manipule classes CSS)
  - ...

Variables sensibles (CSRF, tech_public_token, URLs) :
  - {{ csrf_token() }} embarqué à la ligne X
  - {{ $tech->tech_public_token }} embarqué aux lignes X, Y, Z
  - Routes Laravel ({{ route(...) }}) embarquées aux lignes X, Y...
```

### Étape 1.2 — Structure de dossiers cibles

Crée la structure suivante **vide** (juste les dossiers, pas de
fichier — sauf un `.gitkeep` par dossier) :

```
resources/views/public/tech/
├── partials/
└── (les .blade.php seront créés en Phase 2)

resources/js/tech/
├── core/
├── features/
└── (les .js seront créés en Phase 3)
```

### Étape 1.3 — Redirect 301 `/pige/{token}` → `/tech/{token}/poses`

Le mapping est subtil : `/pige/{token}` peut résoudre vers une
`PoseTask` (32 chars), une `Campaign` (48 chars), ou rien.

Crée un service `app/Services/TechUrlResolverService.php` :

```php
class TechUrlResolverService
{
    /**
     * Résout un token /pige/{token} vers l'URL canonique /tech/{user_token}/poses
     *
     * Logique :
     *  - Si token 32 chars → cherche PoseTask::where('public_token', $token)
     *    → si trouvé ET task a un assigned_user_id avec tech_public_token
     *       → redirect /tech/{user_token}/poses?focus=task_{id}
     *    → sinon : garde l'URL /pige/{token} (vue legacy temporairement)
     *  - Si token 48 chars → cherche Campaign::where('pige_token', $token)
     *    → garde l'URL legacy (mode campagne, plus complexe à unifier)
     *  - Sinon : 404
     */
    public function resolve(string $token): ?string
    {
        // ... implémentation
    }
}
```

**Modifier `PublicPigeController::show($token)`** pour qu'il appelle
le resolver d'abord :

```php
public function show(string $token, TechUrlResolverService $resolver)
{
    $canonicalUrl = $resolver->resolve($token);
    
    if ($canonicalUrl) {
        // 301 vers la nouvelle URL canonique
        return redirect($canonicalUrl, 301);
    }
    
    // Fallback : comportement actuel inchangé
    return $this->showLegacy($token);
}
```

**Important** : tant que la Phase 2 n'est pas terminée, on garde le
comportement legacy actif. Le redirect 301 ne s'active QUE quand le
nouveau dashboard tech est prêt à recevoir le paramètre `?focus=task_X`.

Donc pour Phase 1 : **on prépare le code du resolver mais on ne le
branche pas encore en redirect**. Il reste en mode "shadow" (testé
mais inactif).

### Étape 1.4 — Snapshot ground truth

Capture l'état actuel pour pouvoir comparer après chaque phase :

1. **Captures d'écran** (DevTools desktop + responsive mobile 360×640)
   de 5 pages :
   - `/tech/{token}/poses` avec 0 pose
   - `/tech/{token}/poses` avec 3 poses
   - `/tech/{token}/poses` avec 50+ poses (utilise un fixture si
     ta DB locale n'en a pas)
   - `/tech/{token}/piges` (historique)
   - `/pige/{token}` (page legacy 1 panneau)

2. **Sauvegarder** dans `docs/snapshots/tech-space-before-refonte/`
   (créer le dossier) au format PNG.

3. **Inventaire fonctionnel** dans un fichier
   `docs/snapshots/tech-space-checklist.md` :
   - Liste exhaustive des interactions possibles
   - Format : "Action → résultat attendu"
   - Exemple :
     ```
     - Tap "Y aller" → ouvre Google Maps avec coords du panneau
     - Tap "Photo" → ouvre caméra arrière en mode capture
     - Tap "Souci" → ouvre modale signalement avec 9 motifs
     - Filter "En retard" → masque les cards non-en-retard
     - ... (au moins 25-30 actions listées)
     ```

Cette checklist sert de **référentiel** : à la fin de chaque phase,
tu vérifies que chaque action fonctionne toujours.

### Étape 1.5 — Tests Feature existants

Lance les tests Feature concernant l'espace tech :

```bash
php artisan test --filter "TechSpace|PoseTaskPublic|PublicPige"
```

Note quels tests passent, quels skip, quels échouent. **Aucune
régression ne sera tolérée** après les 3 phases.

### 📋 Mini-rapport Phase 1 attendu

```
=== PHASE 1 — PRÉPARATION TERMINÉE ===

🆕 Structure dossiers créée :
   resources/views/public/tech/partials/
   resources/js/tech/core/
   resources/js/tech/features/

🆕 Service créé (shadow, non branché) :
   app/Services/TechUrlResolverService.php

📸 Snapshot ground truth :
   docs/snapshots/tech-space-before-refonte/ (5 captures PNG)
   docs/snapshots/tech-space-checklist.md (X actions listées)

📋 Audit JS inline complet (cf. format Étape 1.1)

🧪 Tests Feature pré-existants :
   - X tests passent
   - Y tests skippent (sqlite)
   - 0 régression introduite

Aucune modification visible côté tech. Le rendu est strictement
identique à avant.

STOP — j'attends ta validation avant de lancer la Phase 2.
```

---

## 🔍 PHASE 2 — Découpage Blade (~3125 → squelette + 11 partials)

### Principe d'extraction mécanique

Tu fais une **extraction couper-coller** sans modifier le rendu :

1. Identifier dans `tech-space.blade.php` les blocs HTML logiques
   (séparés par commentaires ou par structure naturelle).
2. Couper le bloc, le coller dans un nouveau partial.
3. Remplacer dans `tech-space.blade.php` par `@include('public.tech.partials._xxx')`.
4. Vérifier que le rendu est strictement identique.

### Partials à créer (dans l'ordre suggéré)

1. **`_topbar.blade.php`** — Header sticky + progress + KPIs
2. **`_focus_card.blade.php`** — "Prochaine pose" mise en avant
3. **`_pose_card.blade.php`** — Une card de pose (réutilisable dans la liste)
4. **`_pose_list.blade.php`** — Liste groupée par commune (utilise `_pose_card`)
5. **`_filters_chips.blade.php`** — Chips de filtre (late/today/problem/etc.)
6. **`_controls_bar.blade.php`** — Barre d'outils (recherche, carte, etc.)
7. **`_modal_report.blade.php`** — Modale signalement (9 motifs)
8. **`_banner_new_task.blade.php`** — Bandeau "nouvelle pose assignée"
9. **`_banner_rejected_photo.blade.php`** — Bandeau "photo refusée"
10. **`_pwa_install.blade.php`** — Splash / installation PWA
11. **`_styles.blade.php`** — Bloc `<style>` inline (avant extraction CSS phase optionnelle)

### Vue principale après découpage

`tech-space.blade.php` devient un squelette d'environ 150 lignes :

```blade
@extends('layouts.tech-public')

@section('head')
    @include('public.tech.partials._styles')
    {{-- meta + manifest --}}
@endsection

@section('content')
    @include('public.tech.partials._topbar')
    
    @if($nextTask)
        @include('public.tech.partials._focus_card', ['task' => $nextTask])
    @endif
    
    @include('public.tech.partials._banner_new_task')
    @include('public.tech.partials._filters_chips')
    @include('public.tech.partials._controls_bar')
    
    @include('public.tech.partials._pose_list', ['groupedTasks' => $groupedTasks])
    
    @include('public.tech.partials._modal_report')
    @include('public.tech.partials._pwa_install')
@endsection

@push('scripts')
    {{-- Le JS reste inline pour cette phase. La Phase 3 l'extraira. --}}
    <script>
        // ... ~1500 lignes existantes, intouchées
    </script>
@endpush
```

### Vérifications obligatoires

Après chaque partial extrait :

1. **Comparer visuellement** avec la capture snapshot de la Phase 1.
   Tolérance : 0 pixel de différence (sauf si une différence est
   explicitement justifiée par toi).
2. **Tester les 5 scénarios** de la checklist Phase 1.4.
3. **Vérifier le HTML rendu** (View Source) : structure identique
   (mêmes IDs, mêmes classes, mêmes attributs data-*).

### Précautions sur les variables Blade

Le piège classique du découpage : les partials n'ont pas accès aux
mêmes variables. Solutions :

- Passer les variables explicitement via `@include('xxx', ['var' => $var])`
- OU utiliser `@includeWhen`, `@includeFirst` si besoin
- NE PAS utiliser `$loop` à travers les partials (sa scope est local
  à chaque `@foreach`)

### 📋 Mini-rapport Phase 2 attendu

```
=== PHASE 2 — DÉCOUPAGE BLADE TERMINÉ ===

📄 Vue principale tech-space.blade.php :
   Avant : 3125 lignes
   Après : XXX lignes (squelette + @include)

🆕 11 partials créés :
   ✏️ _topbar.blade.php (X lignes)
   ✏️ _focus_card.blade.php (X lignes)
   ✏️ _pose_card.blade.php (X lignes)
   ... (etc.)

📸 Comparaison snapshot :
   5/5 captures identiques au pixel près ✓
   Différences identifiées : 0

🧪 Tests Feature : aucune régression ✓
🧪 Checklist 25 actions : toutes OK ✓

Variables Blade exposées explicitement à chaque partial.
Aucune utilisation de $loop à travers les partials.

STOP — j'attends ta validation avant de lancer la Phase 3.
```

---

## 🔍 PHASE 3 — Extraction JS en modules

### Principe

Passer du JS inline (~1500 lignes dans `<script>`) à des fichiers
séparés modulaires. **Pas de bundler Vite** (la décision 2 l'exclut
pour cette sous-mission). Les fichiers sont servis directement via
`<script src="...">`.

### Architecture cible

```
resources/js/tech/
├── tech-app.js                  Point d'entrée, bootstrap, registre des modules
├── core/
│   ├── api.js                   fetch helpers + CSRF + base URL
│   ├── state.js                 État global (objet simple, pas Proxy pour SM1)
│   ├── offline.js               Détection online/offline + flush retry queue
│   └── sw-register.js           Registration du Service Worker existant
└── features/
    ├── upload.js                Capture + compression + XHR + retry
    ├── geolocate.js             "Près de moi" + TSP "Mon chemin"
    ├── heartbeat.js             Polling 20s
    ├── search.js                Select2 + AJAX paginé
    ├── filters.js               Filtres chips show/hide
    ├── report.js                Modale signalement
    └── pwa-install.js           Bouton "Installer"
```

### Stratégie de migration

Pour chaque bloc identifié en Phase 1.1, tu suis ce pattern :

1. **Copier** le code du bloc dans le nouveau module (`features/xxx.js`)
2. **Exporter** une fonction `init()` qui setup les event listeners
3. **Importer** dans `tech-app.js` et appeler `init()` au DOM ready
4. **Supprimer** le bloc original du inline JS de la vue Blade

Exemple `features/upload.js` :

```javascript
import { apiPost } from '../core/api.js';
import { state } from '../core/state.js';

let queue = JSON.parse(localStorage.getItem('ts-upload-queue') || '[]');

export function init() {
    const photoInputs = document.querySelectorAll('input[type="file"][data-tech-photo]');
    photoInputs.forEach(input => input.addEventListener('change', handleCapture));
    
    window.addEventListener('online', flushQueue);
    
    // ... reste de la logique upload
}

async function handleCapture(e) {
    // ... compression + XHR
}

function flushQueue() {
    // ... retry des uploads en attente
}
```

`tech-app.js` :

```javascript
import { init as initUpload } from './features/upload.js';
import { init as initHeartbeat } from './features/heartbeat.js';
import { init as initGeolocate } from './features/geolocate.js';
import { init as initSearch } from './features/search.js';
import { init as initFilters } from './features/filters.js';
import { init as initReport } from './features/report.js';
import { init as initPwaInstall } from './features/pwa-install.js';
import { initOffline } from './core/offline.js';
import { initSwRegister } from './core/sw-register.js';

document.addEventListener('DOMContentLoaded', () => {
    initOffline();
    initSwRegister();
    
    initUpload();
    initHeartbeat();
    initGeolocate();
    initSearch();
    initFilters();
    initReport();
    initPwaInstall();
});
```

### Chargement dans la vue Blade

Comme on n'utilise pas Vite, on charge avec une balise classique
+ `type="module"` :

```blade
@push('scripts')
    <script type="module" src="{{ asset('js/tech/tech-app.js?v=' . config('app.version')) }}"></script>
@endpush
```

Le `?v=` invalide le cache navigateur à chaque déploiement.

### Gestion des variables Blade dans le JS

Le JS inline avait accès direct à Blade (`{{ csrf_token() }}`,
`{{ route('xxx') }}`). Quand on extrait, on perd cet accès. Solution :
publier un objet de config sur `window` AVANT le chargement des modules :

Dans `_topbar.blade.php` (ou un nouveau `_js_config.blade.php`) :

```blade
<script>
    window.TECH_CONFIG = {
        csrfToken: @json(csrf_token()),
        techToken: @json($tech->tech_public_token),
        routes: {
            uploadPhoto: @json(route('tech.space.photo', ['token' => $tech->tech_public_token, 'task' => '__TASK__'])),
            heartbeat: @json(route('tech.space.heartbeat', ['token' => $tech->tech_public_token])),
            search: @json(route('tech.space.search', ['token' => $tech->tech_public_token])),
            // ... etc.
        },
        bootstrap: {
            ssrCap: {{ config('tech_space.ssr_cap') }},
            heartbeatInterval: 20000,
        }
    };
</script>
```

Les modules JS lisent `window.TECH_CONFIG.csrfToken` au lieu de
`{{ csrf_token() }}`.

### Routes dynamiques (avec ID variable)

Pour les routes qui ont un ID variable (ex. `upload/{task_id}`), on
publie un template avec un placeholder `__TASK__` que le JS remplace
au moment de l'appel :

```javascript
// Dans upload.js
const url = window.TECH_CONFIG.routes.uploadPhoto.replace('__TASK__', taskId);
```

### Vérifications obligatoires

Après l'extraction de chaque module :

1. Ouvrir la console DevTools → aucune erreur JS
2. Tester l'action correspondante (upload photo, heartbeat, etc.)
3. Vérifier que ça marche **online ET offline** (toggle Network)
4. Snapshot ground truth : checklist Phase 1.4 toujours 100% verte

### 📋 Mini-rapport Phase 3 attendu

```
=== PHASE 3 — EXTRACTION JS TERMINÉE ===

📦 11 fichiers JS créés :
   🆕 tech-app.js (entry, X lignes)
   🆕 core/api.js (X lignes)
   🆕 core/state.js (X lignes)
   🆕 core/offline.js (X lignes)
   🆕 core/sw-register.js (X lignes)
   🆕 features/upload.js (X lignes)
   🆕 features/heartbeat.js (X lignes)
   🆕 features/geolocate.js (X lignes)
   🆕 features/search.js (X lignes)
   🆕 features/filters.js (X lignes)
   🆕 features/report.js (X lignes)
   🆕 features/pwa-install.js (X lignes)

📄 Vue tech-space.blade.php :
   Avant Phase 3 : XXX lignes (avec JS inline ~1500)
   Après Phase 3 : ~150 lignes (squelette pur, JS externalisé)

🔧 Window.TECH_CONFIG publié avec :
   - csrfToken
   - techToken
   - X routes
   - Config bootstrap

📸 Comparaison snapshot : 5/5 captures identiques ✓
🧪 Tests Feature : 0 régression ✓
🧪 Checklist 25 actions : 25/25 OK ✓
🌐 Test offline : retry queue fonctionne ✓
📱 Test sur Chrome DevTools Network throttling 2G : OK ✓

Bundle JS total (non minifié) : XXX KB
Bundle JS total (gzip estimé) : XXX KB

STOP — j'attends ta validation finale avant le rapport de clôture
SM1.
```

---

## 📌 Règles non négociables

1. **Zéro régression visuelle.** Snapshot ground truth obligatoire
   entre chaque phase.

2. **Zéro régression fonctionnelle.** Checklist 25 actions
   validée 100% à chaque phase.

3. **Aucune nouvelle dépendance JS** (pas de npm install, pas de
   bundler Vite obligatoire pour cette SM).

4. **Mobile-first vrai.** Test sur Chrome DevTools (responsive
   360×640, throttling 2G) à chaque phase.

5. **STOP entre chaque phase.** Tu me livres un mini-rapport, je
   valide, tu enchaînes.

6. **Pas de big-bang.** Pas de "je fais les 3 phases d'un coup
   pour aller plus vite". Le découpage en phases est ta sécurité.

7. **Branche dédiée.** Travaille sur `feature/tech-refonte-sm1` ou
   équivalent. Pas de push direct sur `main` ou `develop`.

---

## 🎯 Commande de lancement

Procède dans cet ordre strict :

1. Phase 1 → mini-rapport → ma validation
2. Phase 2 → mini-rapport → ma validation
3. Phase 3 → mini-rapport → ma validation
4. Rapport de clôture SM1 selon format CLAUDE.md (✅ HARMONISATION
   TERMINÉE) avec checklist tests utilisateurs (1-2 techs CIBLE qui
   utilisent leur lien comme d'habitude pendant 1-2 jours, retour
   "tu remarques quelque chose de différent ?" — la réponse attendue
   est "non, rien changé" = succès).

**Tu peux lancer la Phase 1.**
