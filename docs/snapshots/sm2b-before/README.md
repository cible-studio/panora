# Phase α SM2b — Préparation

## Branche
`feature/admin-dashboard-sm2b` créée depuis develop (HEAD = 93a6ac5
post-merge SM2a).

## Inventaire

- [routes-admin-baseline.txt](routes-admin-baseline.txt) — 24 routes
  admin matchant `dashboard|pige|map|team`. Aucune route `/live` n'existe
  encore — SM2b en ajoutera 5 (cf. spec §2.3).

- **Dashboard admin actuel** : `app/Http/Controllers/Admin/DashboardController.php`
  méthode `index()` (rapports/KPIs métier statique). Le nouveau "live"
  dashboard cohabitera dans le même contrôleur (méthode `live()`) ou
  via un nouveau `AdminLiveDashboardController` — décision Lot 1.2.

## Adaptations du brief vs. réalité du code

Le brief SM2b a été rédigé en aveugle. Adaptations nécessaires :

| Brief mention | Réalité Panora | Décision SM2b |
|---|---|---|
| `User.last_seen_at` | Colonne manquante (il existe `reservations_last_seen_at` pour autre usage) | **Lot 1.1** : migration `add_last_seen_at_to_users` + update `TechSpaceController::heartbeat()` pour stamper |
| `ProblemReport` model | N'existe pas comme modèle dédié — c'est `PoseTaskAction` avec `action='problem_reported'`, `resolved_at` discrimine ouvert/résolu | **Tous lots** : utiliser `PoseTaskAction` partout, ne PAS créer de nouveau modèle |
| `Pige.rejection_comment` | Colonne s'appelle `rejection_reason` | Utiliser `rejection_reason` |
| `Pige.rejected_at`, `rejected_by_id`, `validated_at` | N'existent pas. À la place : `verified_at` + `verified_by` + `status` ('en_attente'/'verifie'/'rejete') | Adapter `validate`/`reject` controllers en conséquence — pas de nouvelles colonnes |
| `Pige.rejection_reason` enum 'blurry'/'wrong_panel'/'gps_too_far'/'other' | C'est un texte libre aujourd'hui | **Phase 5** : texte libre suffit. Si la patronne demande un enum plus tard, on bumpera. |

## Baseline tests

Pas de `php artisan test` exécuté en Phase α (suite peut prendre 5+ min
sur Windows + erreurs sqlite préexistantes sur M2/CaReal/RapportsFilter).
Inventaire des 21 fichiers tests préservé dans `../sm2-before/tests-inventory-baseline.txt`
(SM2a — toujours valide). Aucun test admin/dashboard live à régresser.

## Décisions structurelles

1. **Polling 20s** : aligne avec celui des techs (`tech-app.js` heartbeat
   déjà en place). Cohérence + facilite la corrélation côté admin avec
   les heartbeats reçus.
2. **Cache 5s** sur `AdminLiveDashboardService` via `Cache::remember`
   pour ne pas marteler la BDD si 5 admins ouvrent le dashboard en
   simultané.
3. **Pas de WebSocket** : on reste sur HTTP polling (déjà éprouvé).
4. **Pas de nouvelle migration sauf `last_seen_at`** sur users. Tout
   le reste utilise le schéma existant.
5. **Stack admin** : Blade + ES module (cohérent avec SM1.5/SM2a tech).
   Pas de Livewire/Vue/React.
