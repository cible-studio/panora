# Snapshots ground truth — Avant SM2a

Ce dossier doit contenir 5 captures PNG de l'état actuel (post-SM1.5)
de l'espace technicien, prises AVANT le début du code SM2a.

## Captures à fournir (par l'utilisateur)

| # | Nom du fichier | Scénario | Comment reproduire |
|---|---|---|---|
| 1 | `tech-space-0-poses.png` | Tech sans aucune pose à faire | Token tech d'un user sans `PoseTask` actif |
| 2 | `tech-space-3-poses.png` | Tech avec 3 poses (peu) | Token tech avec exactement 3 `PoseTask` `planifiee` |
| 3 | `tech-space-50-poses.png` | Tech avec 50+ poses (cap SSR) | Token tech proche du cap 200 SSR |
| 4 | `piges-historique.png` | Tableau historique des piges du tech | URL `/tech/{token}/piges` |
| 5 | `pige-legacy.png` | Page legacy `/pige/{token}` (1 panneau) | URL d'un envoi WhatsApp historique |

## Format

- PNG (pas JPEG, on veut le pixel exact)
- Résolution mobile portrait 360×640 minimum (cible Android Go)
- Sans annotation, sans flou

## Pourquoi

La refonte SM2a transforme visuellement les 9 écrans tech. Pour
détecter une régression visuelle (un détail oublié), on compare
pixel-à-pixel avec ces captures.

Ce dossier reste vide tant que l'utilisateur n'a pas fourni les
captures. Phase α NE PEUT PAS être considérée 100% verte sans ça.
