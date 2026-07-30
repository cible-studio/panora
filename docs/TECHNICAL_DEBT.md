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

---

## Résolues

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
