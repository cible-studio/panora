# Rapport final — Bloc 4 « CA réel sur Rapports » (Famille B)

**Date** : 2026-06-18
**Branche** : `develop` (Commits 11→16) + à merger vers `main`
**Mission** : intégrer le CA réel (HT facturé + TTC encaissé) sur la page
Rapports, en remplacement partiel du CA contractuel.

---

## ✅ HARMONISATION TERMINÉE — « CA réel sur Rapports »

### Centralisé dans

- [`app/Services/CaRealService.php`](../../app/Services/CaRealService.php)
  — single source of truth pour le CA réel.
- [`app/Services/FinancialDashboardService.php`](../../app/Services/FinancialDashboardService.php)
  — enrichi d'une clé `facture_periode_ht` (additif) pour permettre la
  cohérence stricte au franc près.

### Endroits adaptés

#### Service & tests
- ✅ `app/Services/CaRealService.php` (NOUVEAU)
- ✅ `app/Services/FinancialDashboardService.php` (clé `facture_periode_ht` add-on)
- ✅ `tests/Unit/CaRealServiceTest.php` (9 cas, 100 % passent)
- ✅ `tests/Feature/CaRealServiceConsistencyTest.php` (4 cas, skip sqlite,
   passe en MySQL — Garde-fou 3)

#### Contrôleur
- ✅ `app/Http/Controllers/Admin/RapportController.php`
  - `buildReportData()` injecte `$caReel` + `$caReelIgnoredFilters`
  - séries mensuelles `$caMensuelHt` + `$caMensuelTtc`
  - `exportPdf()` injecte `$caReel` dans la synthèse exécutive

#### Vues
- ✅ `resources/views/admin/rapports/index.blade.php`
  - `window.__RPT__` expose `caMensuelHt` + `caMensuelTtc`
  - JS `RPT.renderCaReal()` (Chart.js 2 lignes)
  - `chart-ca-real` ajouté à `refreshAllCharts` (live filter)
- ✅ `resources/views/admin/rapports/partials/_kpis.blade.php`
  - 1 KPI "CA période" → 2 KPIs "📤 CA HT facturé" + "💰 Encaissé TTC"
  - Bandeau bleu pâle "ℹ️ Filtres ignorés" conditionnel (Garde-fou 1)
  - Matrice RBAC migrée de `tab` → `id` pour discrimination fine
- ✅ `resources/views/admin/rapports/partials/_tab_ca.blade.php`
  - Nouveau bloc graphique CA réel 2 lignes en tête
  - KPIs renommés "CA contractuel période" etc. (Garde-fou 2)
  - Tables "Top clients", "Communes les plus rentables", "CA par ville"
    relibellées avec "(CA contractuel)" — ambiguïté supprimée
- ✅ `resources/views/admin/rapports/partials/_tab_clients.blade.php`
  - "Top CA" → "Top CA contractuel"
  - "Répartition du CA par client" → "...contractuel..."
  - Colonne "CA Total" → "CA contractuel"
- ✅ `resources/views/admin/rapports/partials/_tab_zones.blade.php`
  - Bouton "CA annuel" → "CA contractuel annuel"
  - Colonne "CA {année}" → "CA contractuel {année}"
- ✅ `resources/views/admin/rapports/synthese-pdf.blade.php`
  - "CA total période" → "CA contractuel période"
  - Nouveau bloc "💰 CA réel sur la période" (3 KPIs + note méthodo)

#### Documentation
- ✅ `docs/snapshots/ca_real_snapshot.md` (script tinker + grille à remplir)
- ✅ `docs/TECHNICAL_DEBT.md` (dette "CA réel non filtrable géographiquement")
- ✅ `docs/snapshots/ca_real_rapport_final.md` (CE FICHIER)

---

## 🛡️ Garde-fous patronne — état

### G1 — Bandeau d'info sur filtres incompatibles
✅ Présent dans `_kpis.blade.php`. Apparaît UNIQUEMENT si :
- `$caReelIgnoredFilters` non vide
- Au moins un KPI CA réel visible pour le rôle courant (admin / commercial)

Style : bordure gauche bleue 4px, fond `rgba(59,130,246,.08)`, contenu en
2 lignes (libellés des filtres ignorés + lien vers Finance).

### G2 — Libellés ultra-clairs
✅ Distinction visuelle imposée sur 100 % des surfaces touchées :
- **Blocs CA RÉEL** : émojis 📤 / 💰 + sous-titres "indép. filtres commune/zone"
- **Blocs CA CONTRACTUEL** : mot "contractuel" présent dans chaque libellé,
  tooltip de détail au survol

### G3 — Test de cohérence Finance ↔ Rapports
✅ Implémenté dans `tests/Feature/CaRealServiceConsistencyTest.php` :
- `test_ttc_encaisse_est_identique_a_finance_encaisse`
- `test_ht_facture_est_identique_a_finance_facture_periode_ht`
- `test_filtres_incompatibles_sont_rapportes_dans_ignored_filters`
- `test_pas_de_filtres_incompatibles_donne_array_vide`

Skip propre sur sqlite (cohérent avec `RapportsFilterTest`). Run réel
nécessaire sur CI MySQL avant merge prod.

---

## 📋 À vérifier manuellement par l'utilisateur

Liste impérative AVANT de considérer la mission close :

- [ ] **Snapshot ground truth** : remplir `docs/snapshots/ca_real_snapshot.md`
  sur prod (`php artisan tinker` + script fourni). Cocher les 5 cases de
  la grille de vérification.
- [ ] **Bandeau filtres incompatibles** : aller sur `/admin/rapports` →
  activer un filtre Commune → vérifier que le bandeau bleu pâle "ℹ️
  Filtres ignorés" apparaît au-dessus des KPIs CA réel.
- [ ] **2 KPIs CA réel** : vérifier que "📤 CA HT facturé" et "💰 Encaissé
  TTC" s'affichent en tête des Rapports, et que leurs valeurs MATCHENT
  celles affichées sur la page Finance pour la même période.
- [ ] **Graphique CA mensuel 2 lignes** : onglet CA → vérifier que le bloc
  "📊 CA réel mensuel — HT facturé / TTC encaissé" s'affiche AU-DESSUS du
  bloc historique CA contractuel.
- [ ] **Tableaux secondaires** : tous portent désormais "CA contractuel"
  dans leur libellé (Top clients, Top communes, CA par ville).
- [ ] **Export PDF synthèse** : télécharger `/admin/rapports/export/pdf` →
  vérifier la présence du bloc "💰 CA réel sur la période" + note méthodo.
- [ ] **Live filter AJAX** : changer une commune sur la page → vérifier
  que les KPIs CA réel ne bougent PAS (Garde-fou Q2), le bandeau d'info
  apparaît/disparaît, et le graphique 2 lignes se rafraîchit.
- [ ] **Cache view** : sur la prod, après le pull `git pull origin main`,
  exécuter `php artisan view:clear` (les vues compilées sont obsolètes).

---

## 🧪 Tests automatisés

| Suite | Résultat |
|---|---|
| `tests/Unit/CaRealServiceTest.php` | ✅ 9 tests / 9 OK (sans base) |
| `tests/Feature/CaRealServiceConsistencyTest.php` | ⏸ skip sqlite, prêt MySQL CI |
| `tests/Feature/RapportsFilterTest.php` (non-régression) | ⏸ skip sqlite |
| `tests/Unit/ReminderServiceTest.php` (non-régression) | ✅ tous OK |

Aucune régression détectée dans les suites locales lancées.

---

## 🔐 Garde-fou Q5 — RGPD / scope des changements

Le périmètre Bloc 4 est strictement additif :
- Aucune migration de base
- Aucune modification de schéma `invoices` / `invoice_payments`
- Aucune modification des méthodes Finance existantes (clé `facture_periode_ht`
  ajoutée en tail, `facture_periode` TTC inchangée)
- Aucun export modifié à part `synthese-pdf` (enrichi seul, cf. Q5)

→ Compatible avec une mise en prod ciblée sans data migration.

---

## 📦 Commits Bloc 4

| # | Hash | Sujet |
|---|---|---|
| 11 | `4210f90` | feat(rapports): CaRealService — CA HT facturé + Encaissé TTC |
| 12 | `e14f3ff` | feat(rapports): KPIs CA réel + bandeau filtres + libellés clairs |
| 13 | `bdd2829` | feat(rapports): graphique CA mensuel 2 lignes + libellés contractuel |
| 14 | `b47b905` | feat(rapports/pdf): bloc CA réel dans la synthèse exécutive |
| 15 | `a4fbbc7` | docs(rapports): snapshot ground truth CA réel — script + grille |
| 16 | `_______` | docs(rapports): TECHNICAL_DEBT + rapport final harmonisation |

---

## 🎯 STOP — En attente de validation manuelle utilisateur

Conformément au point 5 du brief, je m'arrête ici sur develop. Le merge
vers main + le déploiement en prod ne sont PAS lancés tant que la
checklist "À vérifier manuellement" ci-dessus n'a pas été cochée et que
le snapshot ground truth (`docs/snapshots/ca_real_snapshot.md`) n'a pas
été rempli.

Une fois validé, le merge final se fera en 3 commandes :

```bash
git checkout main
git merge --no-ff develop -m "merge: Bloc 4 — CA réel sur Rapports (Famille B)"
git push origin main
```

Puis sur la prod :
```bash
git pull origin main
php artisan view:clear
php artisan cache:clear
```
