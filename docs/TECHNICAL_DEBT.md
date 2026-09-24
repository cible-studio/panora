# TECHNICAL DEBT — Panora

Dette technique connue à suivre et à mettre à jour à chaque mission qui
en crée ou clôt une.

---

## Ouvertes

### Migrations non SQLite-portables

Certaines migrations utilisent la syntaxe MySQL `MODIFY COLUMN` (ex: enums,
constraints) qui ne passe pas sous SQLite. Les tests d'intégration qui
tournent sur SQLite en mémoire les évitent. À terme, refondre pour rester
compatible pour faciliter les tests locaux.

### Sous-rapports à auditer

Certains PDF de rapports (occupation détaillée, historique factures) n'ont
pas été audités depuis la refonte v2. À revoir quand la patronne signalera
un besoin.

### WhatsApp prod

Compte Twilio en Trial → prod en pause. Passage prod à faire après upgrade
du compte + validation du sender et du content template.

### Logo illisible dans `pdf/partials/branding-header` (repéré 2026-09-02)

`branding-header.blade.php` affiche `$logoCibleLight` (logol.png — texte
**noir**) alors que les vues qui l'incluent ont toutes un `.header` en
`#0a0c10` (quasi noir) : le logo y est donc très peu lisible. Le trait
`PdfAssets::getCibleLogoDark()` (logob.png, texte blanc) existe justement
pour ce cas.

Vues concernées : `network-report`, `piges-report`, `panel-list`,
`panel-sheet`, `selection-images`, `selection-liste`.

Non corrigé volontairement : un seul partial pilote 6 PDF, le changement
doit être validé visuellement sur chacun. `taxes-report` est sorti du lot
le 2026-09-02 (refonte style taxes) — il n'utilise plus ce partial et
affiche le logo sur fond clair.

---

### `calculODPCommune()` — cluster de code mort sur l'ancienne règle

`TaxCalculationService::calculODPCommune()` (~ligne 384) et ses
satellites (`panneauxPourCalcul()`, `moisExistencePanneau()`) appliquent
encore la règle **mensuelle** d'avant TX-9
(`tarif_mensuel × surface × mois_existence`), sans forfait trimestriel et
sans la fusion des faces TX-10.

Aucun appelant en production (vérifié par grep : seul `generateLines()`
est consommé par `TaxController` et `TaxesDetailsExport`). Conservé au
titre de la règle N°3 (pas de suppression hors périmètre), mais **à ne
jamais rebrancher en l'état**.

---

### Mâts double-face non détectés quand les références n'ont pas de suffixe A/B (repéré 2026-09-24)

`TaxCalculationService::referencePhysique()` regroupe les faces d'un même
mât en repérant le suffixe `A`/`B` collé au numéro (`ADJ-004A` + `ADJ-004B`
→ `ADJ-004`). Il couvre 59 paires du parc.

Mais la déclaration réelle `docs/ODP 2024 SAN PEDRO.xlsx` montre un cas
que la règle rate : la ligne « San-Pedro Entrée de ville/**Sortie** de
ville », quantité **1**, correspond à **deux** panneaux dans Panora —
`SPBS-01` (Entrée de Ville) et `SPBS-02` (Sortie de Ville). CIBLE les
déclare comme un seul mât ; Panora facture deux ODP, soit 1 800 000 FCFA
de trop sur San Pedro pour une année.

À l'inverse, le même fichier déclare le rond-point de la Cité en **deux**
emplacements séparés (côté Hôpital, côté Mosquée). Le regroupement n'est
donc pas mécanique : c'est un jugement par site, que la convention de
nommage ne porte pas.

Pistes (aucune tranchée) :
- un champ `panneau_physique_id` (ou `mat_id`) sur `panels`, saisi par le
  MP, qui remplacerait la déduction par référence — le plus fiable ;
- réutiliser `nombre_faces`, aujourd'hui à 1 sur les 364 panneaux donc
  inexploitable en l'état ;
- un rapprochement par coordonnées GPS, fragile (dispersion connue).

⚠ Tant que ce n'est pas traité, **l'ODP de Panora est surévaluée** partout
où une paire de faces ne suit pas la convention `A`/`B`. Le nombre de cas
n'est pas connu : il faudrait confronter le parc aux déclarations réelles
commune par commune.

---
### Base de dev locale en MyISAM → les transactions ne protègent rien (constaté 2026-09-23)

Sur le WAMP de dev, **51 des 57 tables sont en MyISAM** (`panels`,
`campaigns`, `communes`, `invoices`, `audits`…), seules 6 sont en InnoDB.
MyISAM ne gère pas les transactions : `DB::beginTransaction()` /
`DB::rollBack()` s'exécutent **sans erreur et sans effet**.

Constaté en dur : un `update()` de 8 panneaux encadré d'un `beginTransaction`
+ `rollBack` a été **écrit en base définitivement**. Aucune exception, aucun
avertissement.

Conséquences :
- Tout `DB::transaction()` du code métier (`PaymentService`,
  `BillingAllocationService`, `ReservationService`…) n'a **aucune atomicité**
  en local : un échec au milieu laisse des écritures partielles.
- Un bug d'atomicité est **invisible en dev** et n'apparaîtra qu'en prod.
- Aucun script de vérification ne doit se reposer sur un rollback pour
  « annuler » une écriture de test sur cette base.

⚠ À vérifier : la base de **prod** (Docker MySQL) est a priori en InnoDB
puisque créée par les migrations Laravel, mais ce n'est **pas vérifié**.
Contrôle : `SHOW TABLE STATUS` et regarder la colonne `Engine`.

Correctif local possible (non appliqué, décision utilisateur) :
`ALTER TABLE <table> ENGINE=InnoDB;` sur les 51 tables.

---

## Résolues

### TX-14 (2026-09-24) — L'ODP se compte en MOIS (correction de TX-12)

**Pièce de référence : `docs/ODP 2024 SAN PEDRO.xlsx`**, la déclaration
réelle transmise par CIBLE, datée du 13/12/2024. Ses formules :

```
6 m²  : =SUM(6*12*6*3000)   = 1 296 000
12 m² : =SUM(12*12*4*3000)  = 1 728 000
50 m² : =SUM(50*12*1*3000)  = 1 800 000
TOTAL                       = 4 824 000
```

Les quatre facteurs sont les colonnes du tableau : **surface × NB MOIS ×
quantité × tarif mensuel**. La colonne s'appelle « NB MOIS » et vaut 12
pour l'année. **Aucune notion de trimestre n'apparaît dans le document.**

Ce que ça corrige : TX-12 (2026-09-23) avait retiré le ×3 *en gardant le
comptage trimestriel*, ce qui divisait l'ODP par 3. L'ancienne écriture de
TX-9 (`tarif × 3` × 4 trimestres) donnait en réalité le bon montant —
3 × 4 = 12 mois — mais affichait un tarif faux (9 000 au lieu de 3 000),
d'où la demande de retirer le ×3.

Le paiement trimestriel évoqué par la patronne est une **cadence de
règlement** (4 versements couvrant 3 mois chacun), pas un forfait.

Adapté : `TaxPeriodCalculator` (nouveaux `moisCalendairesTouches()` /
`moisODPDansPeriode()` ; les méthodes `trimestres*` restent en place mais
ne portent plus la règle), `TaxCalculationService`, la matrice mensuelle de
`showCommune()`, `InvoiceCalculator`, les légendes des 2 écrans, le PDF
mairie et l'en-tête de l'export Excel.

Vérifié : San Pedro année pleine = 6 840 000 FCFA, soit exactement la
formule du fichier appliquée au parc actuel. L'écart avec les 4 824 000 du
fichier 2024 s'explique intégralement par le parc (`SPBS-02` + un 7ᵉ
panneau 6 m²), pas par le calcul. Détail et dashboard concordent sur les
3 périodicités, et la somme des 12 mois retombe sur l'annuel.

⚠ Hypothèse restée à confirmer côté facturation : `InvoiceCalculator`
compte les **mois calendaires** touchés par la campagne, alors que la TM
garde ses mois « de date à date ». Les deux peuvent différer d'un mois sur
une campagne à cheval.


### TX-11 / TX-13 (2026-09-23) — ODP : cumul annuel et refacturation client

**TX-11** — `computeAnnualTotalDue()` et la matrice mensuelle de
`showCommune()` additionnaient 12 totaux mensuels d'une taxe trimestrielle
(chaque mois renvoyant le trimestre entier) → cumul annuel ×3.
Corrigé : l'ODP passe par un seul appel annuel, et la matrice la porte sur
le 1er mois de chaque trimestre (janvier, avril, juillet, octobre) — ce qui
est aussi le moment où elle est exigible. La TM continue d'être sommée mois
par mois, elle est réellement mensuelle.
Vérifié : fiche commune = 35 068 000 FCFA, identique à /admin/taxes.

**TX-13** — `InvoiceCalculator::calculateLine()` appliquait encore
`($odpRate * 3)`, donc CIBLE refacturait au client 3× l'ODP due à la mairie.
Règle validée par écrit le 2026-09-23 : « je facture au client ce que je paie
à la mairie ». Le ×3 est retiré ; les factures déjà émises ne bougent pas,
leurs montants étant figés dans `invoice_lines.odp_ligne`.
Vérifié : le ×3 n'était recopié nulle part ailleurs — ni dans `QuoteBuilder`
ni dans `InvoiceFromCampaignBuilder` (qui délèguent au calculateur), ni dans
le JS de prévisualisation du formulaire FNE (qui n'utilise pas les dates
campagne, donc la branche fallback sans ×3).

### TX-9 (2026-07-29) — Règles TM / ODP alignées sur la pratique terrain

**Contexte** : le MP calculait TM et ODP sur le terrain différemment de
ce que Panora affichait. Écart signalé par le user 2026-07-29.

**Décision métier validée** :

- **TM (Taxe Municipale)** : `mois anniversaire entamés STRICTEMENT`.
  Exemples : 01/03 → 05/03 = 1 mois. 16/03 → 16/04 = 1 mois (fin = anniv,
  pas strictement dépassé). 15/03 → 30/04 = 2 mois (fin > anniv 15/04).
  05/02 → 05/03 = 1 mois. 05/02 → 07/03 = 2 mois.

- **ODP (Occupation Domaine Public)** : `trimestres calendaires touchés`.
  1 seul jour dans un trimestre = trimestre entier compté. Tarif effectif
  = `tarif_mensuel × 3` (forfait trimestriel). Le tarif stocké dans
  `communes.odp_rate` reste en FCFA/m²/mois — on convertit à la volée.

**Implémentation** :

- Nouveau helper `App\Services\TaxPeriodCalculator` :
  - `moisAnniversaireEntames(start, end)` : algo anniversaire glissant
  - `trimestresCalendairesTouches(start, end)` : compte les T1/T2/T3/T4
  - `moisTMDansPeriode(campStart, campEnd, filterStart, filterEnd)` : idem
    restreint à une fenêtre filtre
  - `trimestresODPDansPeriode(panelCreated, panelDeleted, ps, pe)` : idem

- `TaxCalculationService::generateLines` refondu : utilise ces helpers
  et applique `rateApplied = unitRate × 3` pour l'ODP.

- `InvoiceCalculator::calculateLine` : mode auto si `campaign_start` +
  `campaign_end` sont fournis dans la ligne. Sinon fallback sur
  `duree_mois` (compatibilité totale avec factures FNE émises avant TX-9).

- Migration `2026_07_29_100000_add_campaign_dates_to_invoice_and_quote_lines` :
  ajoute `campaign_start` et `campaign_end` (nullable, date) sur
  `invoice_lines` et `quote_lines`. Aucune donnée existante impactée.

- `QuoteController::syncLines` : propage `quote.period_start` /
  `quote.period_end` vers chaque ligne créée.

- `InvoiceFromCampaignBuilder::createLineForPanel` : propage
  `campaign.start_date` / `campaign.end_date` vers chaque ligne créée.

- `QuoteBuilder::recalculateAndPersist` : passe les dates de la ligne
  (fallback sur quote.period si absentes) à `InvoiceCalculator`.

- Vue `admin/taxes/details.blade.php` : affichage adapté (`× Nm` pour TM,
  `× Nt` pour ODP, avec tarif effectif × 3 pour ODP).

**Impact zéro sur l'historique** :

- **Factures FNE déjà émises** : intactes. Le mode fallback préserve
  l'ancien comportement pour toute ligne sans dates campagne persistées.
- **Modèles Invoice et Quote** : aucune colonne modifiée, aucun total
  recalculé rétroactivement.
- **Nouvelles factures** : bénéficient automatiquement de la nouvelle
  règle dès que les dates campagne sont propagées.

**Tests** : 105/105 passent. Le helper a 19 tests unitaires couvrant tous
les cas validés par le user (courte campagne, campagne à cheval sur
2 mois, année complète, février court, panneau démonté au milieu d'un
trimestre, etc.).

---

## Historique

- **2026-07-29** : TX-9 clôturé — règles TM/ODP alignées sur pratique MP.
- **2026-06-26** : TX-7 clôturé — mois facturables par ligne (fin du `×3`
  systématique). Précurseur de TX-9.
- **2026-06-22** : TX-3 clôturé — les tarifs sont mensuels (correction du
  bug TX-1 qui divisait par 12).
