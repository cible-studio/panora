# MISSION CLAUDE CODE — SM2b : Dashboard Admin Live

> 📍 RÉFÉRENCE OBLIGATOIRE : lis `SM2_DOSSIER_SPECIFICATION.md` AVANT
> de toucher au code. C'est ta bible visuelle et technique.

> 🎯 OBJECTIF : créer le dashboard admin temps réel (5 écrans A1-A5)
> qui permet à l'admin de voir la progression des techs au fil de
> leur journée, avec un polling 20s.

> ⚠️ PRÉCONDITION : SM2a doit être terminée, mergée, et stable depuis
> au moins 24h en conditions réelles.

> ⚠️ APPLIQUE LA RÈGLE N°1 DU CLAUDE.md (harmonisation globale).

---

## 📋 Préconditions strictes

- ✅ SM2a mergée sur `develop`
- ✅ SM2a observée stable 24h+ en conditions réelles (pas de
      remontée tech / patronne)
- ✅ Branche `feature/admin-dashboard-sm2b` créée depuis develop
- ✅ Suite phpunit baseline notée

---

## 🎯 Périmètre exact

### Inclus
- 5 nouveaux écrans admin selon `SM2_DOSSIER_SPECIFICATION.md` §4
- 5 nouvelles routes API selon spec §2.3
- Polling JS 20s sur le dashboard A1
- Modèles existants (User, PoseTask, Pige, ProblemReport) inchangés
  ou très peu modifiés
- Nouveaux services pour agrégation live :
  - `AdminLiveDashboardService` (KPIs + techs actifs + events)
  - `TechTimelineService` (historique chronologique d'un tech)

### Exclus
- Pas de WebSocket/Pusher (juste polling)
- Pas de notifications push navigateur (out of scope)
- Pas de modification du schéma BDD (sauf ajout d'index si lent)

---

## 🔧 Procédure détaillée

### Phase α — Préparation (1h)

1. Lis `SM2_DOSSIER_SPECIFICATION.md` en entier
2. `git checkout -b feature/admin-dashboard-sm2b develop`
3. `php artisan test > .baseline-sm2b.txt`
4. Audit des routes admin existantes :
   ```bash
   php artisan route:list | grep ^.*admin > .routes-admin-baseline.txt
   ```
5. Repère l'emplacement du dashboard admin actuel (probablement
   `app/Http/Controllers/Admin/DashboardController.php` ou similaire)

### Phase 1 — Endpoints backend (3-4h)

C'est le fondement de tout le reste. À faire d'abord pour pouvoir
tester avec curl pendant le développement des vues.

**Lot 1.1 — `AdminLiveDashboardService`**

Crée `app/Services/AdminLiveDashboardService.php` avec une méthode
`buildLivePayload()` qui retourne le JSON décrit dans spec §2.1.

Logique :
- KPIs : agrégations SQL groupées sur la journée
- `techs_active` : `User::where('role', 'tech')->where('last_seen_at', '>', now()->subMinutes(10))`
- `live_events` : `Pige::where('created_at', '>', now()->subSeconds(60))`
  + `ProblemReport::where('created_at', '>', now()->subSeconds(60))`
  + autres transitions PoseTask sur dernière minute
- Cache léger 5s via `Cache::remember` (évite martelage BDD si 5
  admins ouvrent le dashboard simultanément)

**Lot 1.2 — Route et controller**

```php
// routes/web.php
Route::middleware(['auth', 'role:admin,mp,chef_equipe'])->prefix('admin')->group(function () {
    Route::get('/dashboard/live', [AdminDashboardController::class, 'live'])
         ->name('admin.dashboard.live');
});
```

Controller minimaliste : appelle le service, retourne `response()->json()`.

**Lot 1.3 — Tests Feature**

Crée `tests/Feature/Admin/LiveDashboardTest.php` avec au minimum :
- Test 1 : payload contient les 5 clés (`as_of`, `kpis`, `techs_active`,
  `live_events`)
- Test 2 : techs actifs = 0 si `last_seen_at` > 10 min
- Test 3 : live_events vides si aucun nouvel événement
- Test 4 : KPIs corrects sur un dataset connu
- Test 5 : middleware bloque les non-admin

**Lot 1.4 — `TechTimelineService`**

Crée `app/Services/TechTimelineService.php` avec méthode
`buildTimeline($tech, $date)` qui retourne une collection
chronologique d'événements :

```php
[
  [
    'at' => Carbon::parse('2026-06-17 14:32:34'),
    'type' => 'photo_sent',
    'label' => 'Photo envoyée',
    'subject' => 'Carrefour Niangon',
    'location' => 'Cocody',
    'meta' => ['gps_distance_m' => 23],
    'is_current' => true, // si c'est l'événement le plus récent
  ],
  // ... autres événements
]
```

Sources d'événements :
- Création de `Pige` → `photo_sent`
- Validation de `Pige` → `photo_validated`
- `Pige.rejection_comment` non null → `photo_rejected`
- Création de `ProblemReport` → `problem_reported`
- Changements `PoseTask.status` → `tech_en_route`, `tech_arrived`,
  `pose_completed`
- Login user (si journalisé) → `day_started`

Route + tests Feature équivalents au Lot 1.3.

**Lot 1.5 — Endpoints validation photo**

```php
Route::post('/admin/pige/{pige}/validate', [...])->name('admin.pige.validate');
Route::post('/admin/pige/{pige}/reject', [...])->name('admin.pige.reject');
```

Le `reject` enregistre :
- `pige.validated_at = null`
- `pige.rejection_comment = $request->input('comment')`
- `pige.rejection_reason = $request->input('reason')` (enum:
  'blurry', 'wrong_panel', 'gps_too_far', 'other')
- `pige.rejected_at = now()`
- `pige.rejected_by_id = auth()->id()`

Tests Feature obligatoires.

**Lot 1.6 — Endpoint carte live**

```php
Route::get('/admin/map/live', [...])->name('admin.map.live');
```

Retourne juste les positions GPS des techs actifs (économie : pas
besoin du payload complet du dashboard).

```json
{
  "as_of": "2026-06-17T14:32:42Z",
  "techs": [
    {
      "id": 42,
      "initials": "KT",
      "lat": 5.3456,
      "lng": -4.0234,
      "status": "in_progress",
      "current_pose_label": "Carrefour Niangon"
    }
  ]
}
```

→ STOP, mini-rapport intermédiaire 1 (backend prêt)

### Phase 2 — Dashboard A1 (4-5h)

**Lot 2.1 — Squelette de la vue**

Crée `resources/views/admin/dashboard/live.blade.php` :

```blade
@extends('layouts.admin')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/admin/live-dashboard.css?v=' . config('app.version')) }}">
@endpush

@section('content')
    @include('admin.dashboard.partials._live_header')
    @include('admin.dashboard.partials._live_kpis')
    @include('admin.dashboard.partials._live_event_banner')
    @include('admin.dashboard.partials._live_techs_list')
@endsection

@push('scripts')
    @include('admin.dashboard.partials._live_js_config')
    <script type="module" src="{{ asset('js/admin/live-dashboard.js?v=' . config('app.version')) }}"></script>
@endpush
```

**Lot 2.2 — Partials Blade A1**

Crée 4 partials selon spec A1 :
- `_live_header.blade.php` : badge "Mise à jour il y a Xs" avec pastille pulsante
- `_live_kpis.blade.php` : grid 4 colonnes (Progression / En cours / À valider / Signalements)
- `_live_event_banner.blade.php` : bandeau orange caché par défaut (`display: none`),
  rendu visible par JS quand un événement arrive
- `_live_techs_list.blade.php` : liste avec template d'une ligne tech

**Lot 2.3 — Module JS dashboard**

Crée `public/js/admin/live-dashboard.js` selon le pattern SM1 (ESM module) :

```javascript
import { TechPolling } from './core/polling.js';

const ENDPOINT = window.ADMIN_DASHBOARD_CONFIG.endpoint;
const POLL_INTERVAL = 20000;

let knownEventIds = new Set();

async function tick() {
  try {
    const payload = await fetch(ENDPOINT).then(r => r.json());
    updateAsOfBadge(payload.as_of);
    updateKpis(payload.kpis);
    updateTechsList(payload.techs_active);
    handleLiveEvents(payload.live_events);
  } catch (e) {
    console.warn('Live dashboard tick failed', e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  tick();
  setInterval(tick, POLL_INTERVAL);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      // Pause polling — économie batterie
      clearInterval(window.__dashboardTimer);
    } else {
      tick();
      window.__dashboardTimer = setInterval(tick, POLL_INTERVAL);
    }
  });
});

function handleLiveEvents(events) {
  const newEvents = events.filter(e => !knownEventIds.has(eventId(e)));
  newEvents.forEach(showBanner);
  events.forEach(e => knownEventIds.add(eventId(e)));
}
```

**Lot 2.4 — CSS dédié**

Crée `public/css/admin/live-dashboard.css` avec les classes
nécessaires selon spec §6.

**Lot 2.5 — Tests visuels manuels**

Garde-fous obligatoires :
- Ouvre `/admin/dashboard/live` dans Chrome
- Console : aucune erreur
- Network : poll 20s régulier sur `/admin/dashboard/live`
- Trigger manuel : depuis tinker, crée une `Pige`, attends 20s,
  vérifie que le bandeau orange apparaît

→ STOP, mini-rapport intermédiaire 2 (A1 terminé)

### Phase 3 — Fiche tech A2 (2-3h)

**Lot 3.1 — Vue et partials**

Route : `Route::get('/admin/tech/{user}/live', ...)` avec polling
adapté.

Partials :
- `_tech_header.blade.php` : avatar + nom + statut + badge Live
- `_tech_kpis.blade.php` : 4 KPIs personnels
- `_tech_current_card.blade.php` : card orange "EN CE MOMENT"
- `_tech_timeline.blade.php` : frise chronologique

**Lot 3.2 — Timeline rendering**

Le service `TechTimelineService` retourne la collection. La vue
Blade rend la timeline avec point coloré + détails par événement.
Animation simple CSS pour le point "courant" (anneau pulsant).

**Lot 3.3 — Actions rapides**

3 boutons en bas :
- "Appeler" : `<a href="tel:{{ $tech->phone }}">`
- "WhatsApp" : `<a href="https://wa.me/{{ $tech->phone }}">`
- "Localiser" : ouvre A3 centré sur ce tech

→ STOP, mini-rapport intermédiaire 3 (A2 terminé)

### Phase 4 — Carte live A3 (3-4h)

**Lot 4.1 — Vue avec Leaflet**

Route : `Route::get('/admin/map/live', ...)`

Vue : Leaflet 1.9 (déjà chargé pour le tech `/tech/{token}/map`).
Réutiliser CDN si possible.

Marqueurs personnalisés : icône avec initiales du tech + couleur
selon statut.

**Lot 4.2 — Polling positions GPS**

Module JS séparé `public/js/admin/live-map.js` qui poll
`/admin/map/live` toutes les 20s et met à jour les positions des
marqueurs (sans recharger la carte).

**Lot 4.3 — Cluster Leaflet**

Si plusieurs techs proches : utiliser Leaflet.markercluster (déjà
inclus si existant, sinon CDN).

**Lot 4.4 — Panneau latéral**

Liste compacte des techs visibles, mêmes infos que A1 mais en plus
court. Tap sur un tech → centre la carte sur sa position.

→ STOP, mini-rapport intermédiaire 4 (A3 terminé)

### Phase 5 — Validation photo A4 (2-3h)

**Lot 5.1 — Modale validation**

Ajout d'une modale dans A1 et A2 qui s'ouvre sur :
- Click bouton "Valider →" du bandeau live
- Click sur une photo en attente dans A2 timeline

Vue : `resources/views/admin/dashboard/partials/_modal_validate_photo.blade.php`

Contenu selon spec A4 :
- Photo en grand avec overlay GPS
- Comparaison avec photo de référence
- Bouton vert "Valider" + bouton rouge "Refuser"

**Lot 5.2 — Refus avec motif**

Quand l'admin tape "Refuser" :
- Modale secondaire avec choix rapide (4 motifs)
- Textarea pour commentaire détaillé
- Submit POST sur `admin.pige.reject`

**Lot 5.3 — Mise à jour instantanée**

Après validation/refus : la photo disparaît de la liste "À valider"
au prochain poll (20s max) sans rechargement.

→ STOP, mini-rapport intermédiaire 5 (A4 terminé)

### Phase 6 — Vue équipe A5 (1-2h)

**Lot 6.1 — Route et vue**

Route : `Route::get('/admin/team/{poseTeam}/live', ...)`

Vue : grid de cards de techs (3 colonnes desktop, 2 mobile).

**Lot 6.2 — Stats équipe**

Header avec stats globales équipe (somme des techs membres).

→ STOP, mini-rapport intermédiaire 6 (A5 terminé)

### Phase 7 — Notifications visuelles côté admin (1h)

Pour les événements importants (signalement, photo arrivée),
émettre une notification visuelle (sans son par défaut, option à
activer dans les préférences) :
- Badge rouge sur l'onglet navigateur (via `document.title` + `\u{1F534}`)
- Faviconchange si possible
- Pas de bibliothèque externe

### Phase 8 — Rapport final SM2b

Format `✅ HARMONISATION TERMINÉE` du CLAUDE.md.

---

## 🛡️ Garde-fous obligatoires

1. **node --check** + **curl smoke test** sur chaque nouvel endpoint
2. **Tests Feature** pour les 5 nouvelles routes (couverture > 80%)
3. **Test polling** : ouvrir DevTools Network, vérifier 1 requête /
   20s exactement, pas plus, pas moins
4. **Test visibilitychange** : changer d'onglet → polling pause,
   revenir → reprend
5. **Test concurrence** : 2 admins en simultané ne crashent pas
   (cache 5s aide)
6. **Test sans données** : si 0 tech actif, écran reste lisible

---

## ⛔ STOPs obligatoires

6 STOPs durant l'exécution (1 par phase complétée).

---

## 🎯 Critères de réussite SM2b

- [ ] 5 nouveaux endpoints fonctionnels et testés
- [ ] 5 écrans admin (A1-A5) livrés selon spec
- [ ] Polling 20s opérationnel, pause si onglet caché
- [ ] Validation photo opérationnelle (round-trip avec T9 côté tech)
- [ ] Cache 5s en place sur le dashboard pour éviter martelage BDD
- [ ] Suite phpunit baseline préservée + nouveaux tests passants
- [ ] Aucune dégradation perçue sur le dashboard admin existant
- [ ] Compatibilité multi-admins (2-3 admins en simultané OK)
- [ ] Rapport final au format CLAUDE.md ✅ HARMONISATION TERMINÉE

Tu peux lancer la Phase α maintenant.
