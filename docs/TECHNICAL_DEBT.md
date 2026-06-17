# Dette technique Panora

Ce fichier centralise les dettes techniques connues, leur impact, et
leur niveau de priorité. À mettre à jour à chaque mission qui en crée
une, ou qui en clôt une.

---

## 🟡 [Priorité moyenne] Migrations Panora non sqlite-portable

**Conséquence** : les Feature tests de la mission « Rapports pilotés par
filtres en AJAX » (juin 2026, [tests/Feature/RapportsFilterTest.php][rft])
**skippent gracieusement quand `DB_CONNECTION=sqlite`** (cas par défaut
de `phpunit.xml`), donc ne sont **pas exécutés en CI standard**.

[rft]: ../tests/Feature/RapportsFilterTest.php

**Cause racine** : environ 20 migrations Panora utilisent des statements
MySQL-specific (`ALTER TABLE … MODIFY COLUMN`, `SHOW INDEX FROM`, ENUM
syntax) que sqlite rejette.

Liste indicative (grep `MODIFY|SHOW INDEX` dans `database/migrations/`) :

```
2026_03_25_110718_add_terminated_status_to_reservations.php
2026_04_29_095149_add_planifie_to_campaigns_status.php
2026_05_04_120000_add_external_panel_to_reservation_panels.php
2026_05_04_150000_extend_availability_status_external_panels.php
2026_05_10_230000_add_pause_to_campaigns_status.php
… (et autres ENUM changes)
```

**Migrations rendues sqlite-portable lors de la mission Rapports** :
- `2026_03_13_135616_create_permission_tables.php` (SET NAMES utf8 guardé)
- `2026_03_25_110557_add_optimization_indexes.php` (SHOW INDEX guardé,
  fallback Schema::hasIndex pour sqlite)

**Action prévue** : mission séparée « Sqlite-portable migrations » pour
patcher les ~20 migrations restantes — pattern : wrap chaque statement
MySQL-only dans `if (DB::getDriverName() === 'mysql')` + fournir un
chemin de remplacement portable (recréation de la table pour les ENUM
changes sqlite).

**En attendant** : pour exécuter les Feature tests Rapports localement,
configurer un MySQL test DB :

```bash
mysql -u root -e "CREATE DATABASE panora_test CHARACTER SET utf8mb4;"
# phpunit.xml — passer DB_CONNECTION=mysql, DB_DATABASE=panora_test
DB_CONNECTION=mysql DB_DATABASE=panora_test php artisan test tests/Feature/RapportsFilterTest.php
```

---

## 🟢 [Priorité basse] Audit downstream des 3 sous-rapports

**Contexte** : la mission « Rapports pilotés par filtres en AJAX »
(juin 2026) a corrigé le **Bug E** (la page principale propage les
filtres dans l'URL des 3 cartes top — Rapport campagnes / annulations
/ taxes).

**Dette résiduelle** : les controllers des 3 sous-rapports (`rapports.
campagnes`, `rapports.annulations`, `rapports.taxes`) **reçoivent**
les filtres dans la query string mais n'ont **pas été audités** pour
vérifier qu'ils les appliquent à 100 %.

**Action prévue** : mission séparée « Audit & harmonisation filtres
sur rapports.campagnes / rapports.annulations / rapports.taxes »,
même méthode (audit → plan → validation → code).

**Risque actuel** : si un user filtre Cocody en page principale et
clique « Rapport campagnes », il pourrait voir des chiffres globaux
au lieu de Cocody → décalage entre les 2 pages. À tester manuellement
en attendant la mission.

---

## Historique

| Date       | Mission                                      | Dette ajoutée |
|------------|----------------------------------------------|---------------|
| 2026-06-17 | Rapports pilotés par filtres en AJAX         | Les 2 entrées ci-dessus |
