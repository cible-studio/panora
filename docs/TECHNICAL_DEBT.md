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

## 🟡 [Priorité moyenne] Rôle `comptable` jamais commité

**Contexte** : un memory de session mentionne « Rôle COMPTABLE :
nouveau rôle avec read-all invoice scope, can mark paid/litige but
NOT create/update/delete/cancel. Added to UserRole enum, … ».

**État réel observé** (audit 2026-06-17 Sous-mission 0) :
L'enum `users.role` en DB locale contient `'admin','commercial','mediaplanner','technique'` —
**pas de `'comptable'`**. La migration `ALTER TABLE users MODIFY COLUMN role ENUM(...)`
n'a pas été appliquée — soit jamais commitée, soit ratée en prod.

**Conséquence** : si un dev tente de créer un user role=comptable
aujourd'hui, la BDD rejette la valeur. Les Policies qui mentionnent
`comptable` (s'il y en a) silencieusement no-op.

**Action prévue** : mission séparée pour soit (a) finir d'ajouter le
rôle proprement avec migration + Policies à jour, soit (b) retirer
les références orphelines au rôle comptable du code.

---

## 🟢 [Priorité basse] Secteur des clients d'agences externes

**Contexte** : la mission « Module 1 Performance Commerciale » (à venir)
prévoit des stats CA × secteur via `clients.sector_id`. Mais les
panneaux d'agences externes (`external_panels.agency_name`) n'ont pas
de notion de secteur client équivalente.

**Conséquence** : les rapports « CA par secteur » ne couvriront que
les clients internes CIBLE. La part externe sera invisible — ou regroupée
sous un fourre-tout « Régie externe ».

**Action prévue** : mission séparée pour étendre le mapping secteur
aux clients externes (table `external_clients` ou champ sur
`external_panels.agency`), à scoper avec le métier.

---

## 🟢 [Priorité basse] Table `delay_reasons` éditable

**Contexte** : la mission M3 SLA enrichi (2026-06-17) a centralisé les
9 motifs dans l'enum PHP `App\Enums\DelayReason`. Ce choix est volontaire :
pas de migration SQL, déploiement sans risque, type-safe en PHP 8.1.

**Limite** : ajouter / renommer / désactiver un motif nécessite une
modification du code + déploiement. Pas administrable depuis l'UI.

**Action prévue (si besoin métier émerge)** : mission séparée pour créer
table `delay_reasons(id, slug, label, icon, color, sort_order, is_active,
panne_type)` éditable par admin via UI. L'enum DelayReason deviendrait
alors un fallback statique pour les valeurs initiales, le hub réel
serait la BDD.

---

## Historique

| Date       | Mission                                      | Dette ajoutée |
|------------|----------------------------------------------|---------------|
| 2026-06-17 | Rapports pilotés par filtres en AJAX         | Migrations non sqlite-portable + audit sous-rapports |
| 2026-06-17 | M3 SLA enrichi (Module 3)                    | Rôle comptable + secteur externes + delay_reasons table |
