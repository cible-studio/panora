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
