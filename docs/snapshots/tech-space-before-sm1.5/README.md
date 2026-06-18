# Snapshot ground truth — pré-SM1.5

**Date** : 2026-06-18
**État source** : commit `ae2ee0e` de `develop` (= SM1 mergée).
**Branche SM1.5** : `feature/tech-refonte-sm1.5` créée depuis cet état.

## Captures à déposer (action utilisateur)

Reprendre les 5 mêmes scénarios qu'avant SM1 (mais sur l'état actuel
post-merge SM1 — qui doit déjà être pixel-identique à pré-SM1 selon
la validation utilisateur SM1) :

- `tech-space-0-poses.png`
- `tech-space-3-poses.png`
- `tech-space-50-poses.png`
- `tech-piges-historique.png`
- `pige-legacy.png`

Ces 5 captures servent de référence pour la comparaison pixel-à-pixel
après CHAQUE lot de SM1.5. À la fin (Phase C), 0 différence doit
apparaître par rapport à ces captures.

## Inventaire des variables globales custom à l'entrée SM1.5

Source : grep `window.X = ...` dans `resources/views/public/tech-space.blade.php`
post-merge SM1.

| Variable globale | Définie ligne | Consommée ligne(s) | Cible SM1.5 |
|---|---|---|---|
| `window.queueOfflinePhoto` | 1617 (bloc 30) | 691 + 694 (bloc 11) | Reste exposé temporairement (rétro-compat upload → bg-sync) |
| `window.flushUploadQueue` | 1691 (bloc 30) | (interne offline.js déjà) | Peut rester (consommé par core/offline.js déjà migré) |
| `window.jQuery` | (chargé via CDN) | 919 (bloc 2 + search) | RESTE (Select2 v4 requirement) |
| `window.TECH_CONFIG` | publié par _js_config.blade.php | tous les modules | RESTE (single source of truth Blade → JS) |

**Cible SM1.5** : à la fin, seuls subsistent `window.jQuery` (requirement
Select2) et `window.TECH_CONFIG` (single source of truth). Les 2 autres
(`queueOfflinePhoto`, `flushUploadQueue`) peuvent rester documentées
comme "exposées intentionnellement" pour la compat avec d'éventuels
scripts tiers, à valider en clôture SM1.5.

## Référence : la checklist 90 items

Voir `checklist-reference.md` (copie de docs/snapshots/tech-space-checklist.md
au moment de la création de cette branche). À cocher après chaque lot
pour confirmer la non-régression.
