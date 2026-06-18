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

## 🟢 [Priorité basse] Décision C révisée — pas de table `sectors`

**Contexte** : la mission Sous-mission 0 avait validé la création d'une
table `sectors` avec UI admin. **Cette décision a été révisée** lors
de la mise en œuvre M1 (2026-06-17) : la const `Client::SECTORS` existe
déjà avec 21 valeurs FR et est utilisée partout (validation + forms +
sélecteurs filtres). Créer la table aurait dupliqué la source de vérité.

**État actuel** : la liste des secteurs vit dans `app/Models/Client.php:68`.
Pour ajouter/renommer un secteur → modifier la const + déploiement.

**Action si besoin émerge** : mission séparée pour migrer vers table
éditable, avec UI admin /admin/sectors. Pattern identique à la dette
« Table delay_reasons éditable » de M3.

---

## 🟡 [Priorité moyenne] Indice Herfindahl — validation métier requise

**Contexte** : M1 affiche un « score de diversification » calculé par
indice Herfindahl-Hirschman inversé (= `1 - Σ((ca_client_i / ca_total)²)`).
1.0 = portefeuille parfaitement équilibré, 0.0 = mono-client.

**Risque** : le terme « Herfindahl » est peu connu hors finance. Un
commercial qui voit son score 0.42 sans contexte peut ne pas
comprendre. Le drill-down M1 affiche en parallèle « % du CA chez le
top-1 client » qui est plus immédiat.

**Action prévue** : valider métier avec direction CIBLE le 2026-06-XX.
Si jugé non parlant → retirer l'indice Herfindahl, ne garder que la
part top-1 client (déjà calculée). Affichage côte-à-côte aujourd'hui
volontaire pour comparer.

---

## 🟢 [Priorité basse] Lien Rapports → Performance commerciale

**Contexte** : sur la page `/admin/rapports`, l'onglet « Clients »
affiche un classement par CA. Cliquer un client devrait pouvoir dériver
vers le drill du commercial qui possède ce client (utile UX pour
naviguer rapidement entre stats globales et perf individuelle).

**Action prévue (mission séparée)** : enrichir le drilldown client
modal pour montrer le commercial assigné, avec un bouton « → Voir la
fiche performance du commercial ».

---

## 🟢 [Priorité basse] Histogramme remplace boxplot Chart.js (M2)

**Contexte** : M2 Performance Technicien voulait à l'origine un boxplot
de la distribution de réactivité. Chart.js n'a pas de type boxplot natif
(lib `chartjs-chart-boxplot` ~30KB nécessaire).

**Choix retenu** : histogramme à 6 buckets (<1h / 1-4h / 4-24h / 1-3j /
3-7j / >7j) avec couleurs vert→rouge. Plus lisible pour un patron non-
statisticien, perte d'information marginale (moyenne, médiane, quartiles
non affichés mais déductibles visuellement).

**Action si besoin émerge** : ajouter chartjs-chart-boxplot et basculer
le drill tech. Trivial techniquement (~30 min).

---

## 🟡 [Priorité moyenne] M2 — drill équipe ignore les anciens membres

**Contexte** : la mission M2 (2026-06-17) calcule les KPIs équipe en
itérant sur `$team->members` (= membres ACTUELS de l'équipe). Un tech
qui était dans l'équipe il y a 3 mois puis qui a été détaché n'apparaît
pas dans le drill équipe — ses poses historiques ne comptent pas.

**Conséquence** : si un MP fait des comparaisons N vs N-1 au niveau
équipe, la composition équipe d'aujourd'hui sert de référence. Si on
ré-organise les équipes en cours d'année, les stats équipe deviennent
moins comparables d'une année sur l'autre.

**Mitigation actuelle** : sous-titre explicatif ℹ sur le drill équipe
(Garde-fou A) qui documente la limite.

**Action si besoin émerge** : table d'historisation `pose_team_memberships
(user_id, team_id, joined_at, left_at)` qui permettrait de recalculer
la composition équipe à un instant T. Pas urgent — quasi personne ne
fait ce type d'analyse rétrospective.

---

## 🟡 [Priorité moyenne] CA réel non filtrable géographiquement

**Contexte** : Bloc 4 (CA réel sur Rapports, 2026-06-18) a livré 2 nouveaux
KPIs `CA HT facturé` + `Encaissé TTC` via [CaRealService][crs] qui ignorent
volontairement les filtres `commune_id` / `zone` / `category_id` (cf.
arbitrage Q2 patronne).

[crs]: ../app/Services/CaRealService.php

**Conséquence** : impossible aujourd'hui d'obtenir le CA réel ventilé par
commune sans repasser par la page Finance (qui scope au client, pas au
panneau). Le bandeau d'info sur Rapports renvoie l'utilisateur vers Finance.

**Cause racine** : la chaîne `invoices → invoice_lines → campaigns → panels
→ communes` n'est pas exploitée — on garde un join SQL léger et un service
fin (cf. choix volontaire Option A de l'arbitrage Q2).

**Action prévue (si besoin métier confirmé)** : mission séparée "CA réel
géographique" — ajouter une méthode `CaRealService::ventilationParCommune()`
qui :
1. Liste les invoices émises sur la période (filtres compat).
2. Pour chaque invoice, récupère la commune via `invoice_lines.campaign_id`
   → `campaigns.id` → `campaign_panels` → `panels.commune_id`.
3. Ventile `invoice.net_ht` au prorata du nombre de panneaux par commune
   (modèle analogue à `FinancialDashboardService::encaissementsByCommune`).
4. Documenter que la ventilation est une **approximation** (les invoices
   ne sont pas toujours adossées 1:1 à une campagne — cas des factures
   multi-campagnes ou des avoirs sans campaign_id).

**Note** : la spec actuelle Garde-fou 1 (bandeau d'info) doit rester
même si on ajoute cette ventilation — la ventilation reste une approximation
opérationnelle, pas un fait comptable.

---

## 🟡 [Priorité moyenne] Refonte Espace Technicien — SM1.5 à programmer

État actuel après SM1 (refactor pur, juin 2026 — branche
`feature/tech-refonte-sm1`) :

- **Phase 1** ✅ Préparation terminée (audit JS, structure dossiers,
  TechUrlResolverService shadow, checklist 90 items)
- **Phase 2** ✅ Découpage Blade en 11 partials terminé
  (tech-space.blade.php 3125 → 1784 lignes)
- **Phase 3** ⚠️ Extraction JS partielle (~50 %)
  - tech-space.blade.php : 1784 → 1702 lignes
  - 4 features autonomes migrées et actives
  - 6 features encore dans le `<script>` inline

### Modules JS effectivement actifs en SM1 (6)

`public/js/tech/` :
- `tech-app.js` — entry, bootstrap, registre des modules
- `core/api.js` — helpers fetch + CSRF + urlForTask (utilitaire)
- `core/state.js` — objet d'état partagé (utilitaire)
- `core/offline.js` — online/offline events + flush queue
- `core/sw-register.js` — registration Service Worker
- `features/heartbeat.js` — polling 20s + bump KPIs + détection nouvelle pose
- `features/pwa-install.js` — capture beforeinstallprompt (nouveau)

Total : ~280 lignes JS modulaires.
window.TECH_CONFIG publié via partial `_js_config.blade.php` (csrf,
token, 9 routes nommées, motifLabels, bootstrap).

### Encore en inline (à migrer en SM1.5) — par ordre de priorité

| Module cible | Volume estimé | Sections inline source |
|---|---|---|
| `features/upload.js` | ~400 l | compression image, géoloc EXIF, modale justifier pige, upload XHR, aperçu, queue offline IndexedDB |
| `features/filters.js` | ~200 l | filterState + URL sync + chips + KPI grid + TOC zones |
| `features/geolocate.js` | ~180 l | distance haversine "Près de moi" + TSP "Mon chemin" |
| `features/search.js` | ~150 l | Select2 AJAX paginé + openFocusModal |
| `features/status-changes.js` | ~150 l | bumps "Y aller" (en_route 25%) + "J'y suis" (en_cours 60%) + changement statut générique |
| `features/report.js` | ~80 l | modale signalement 9 motifs DelayReason + POST report + bandeau "déjà signalé" |

Volume total restant : ~1160 lignes JS à migrer.
Estimation effort : **8-12h focus**.
Priorité : **moyenne** — pas de blocage utilisateur, mais bloque la
Sous-mission 2 (SM2) qui touchera directement l'UI Focus mode.

### Conditions de réussite SM1.5

- Suppression effective des 2 blocs `<script>` inline historiques
- Console DevTools propre sur 5 scénarios (0, 3, 50+ poses ; piges ;
  pige legacy)
- Test offline + retry queue IndexedDB intact
- Snapshot ground truth pixel-à-pixel préservé
- Checklist 90 items (cf. `docs/snapshots/tech-space-checklist.md`)
  toujours à 100 %

### Pourquoi ce report

Le brief SM1 estimait Phase 3 à 6-8h. À l'exécution, le volume effectif
de code interdépendant (1613 lignes JS inline avec 30 blocs logiques et
variables partagées entre features non encore migrées) rendait risquée
une livraison Lot G complète sans tests utilisateur intermédiaires.
Choix tactique validé avec l'utilisateur : sécurité opérationnelle
(0 régression visible côté tech) > respect du volume annoncé.

---

## Historique

| Date       | Mission                                      | Dette ajoutée |
|------------|----------------------------------------------|---------------|
| 2026-06-17 | Rapports pilotés par filtres en AJAX         | Migrations non sqlite-portable + audit sous-rapports |
| 2026-06-17 | M3 SLA enrichi (Module 3)                    | Rôle comptable + secteur externes + delay_reasons table |
| 2026-06-17 | M1 Performance Commerciale (Module 1)        | Décision C révisée (pas de table sectors) + Herfindahl à valider + lien Rapports↔Perf |
| 2026-06-17 | M2 Performance Tech / Équipe (Module 2)      | Boxplot → histogramme + drill équipe ignore anciens membres |
| 2026-06-18 | Bloc 4 — CA réel sur Rapports (Famille B)    | CA réel non filtrable géographiquement (Option A Q2) — fallback Finance documenté |
| 2026-06-18 | Refonte Espace Technicien SM1 (partielle)    | Phase 3 livrée à ~50 % — SM1.5 (8-12h) à programmer pour finaliser l'extraction JS de 6 modules restant en inline |
