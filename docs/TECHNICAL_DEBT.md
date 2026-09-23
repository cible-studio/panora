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

### TX-11 — `computeAnnualTotalDue()` triple l'ODP annuelle (prouvé 2026-09-23)

`TaxController::computeAnnualTotalDue()` (ligne ~441) additionne les 12
totaux mensuels renvoyés par `generateLines(PERIOD_MONTHLY, …)` :

```php
for ($mois = 1; $mois <= 12; $mois++) {
    $totals = $calc->summarize($calc->generateLines(PERIOD_MONTHLY, $mois, $year, …));
    $total += $totals['odp_total'] + $totals['tm_total'];
}
```

L'ODP se compte en **trimestres** et la règle « 1 jour dans le trimestre =
trimestre entier » fait que **chaque mois** renvoie un trimestre complet.
Les 12 mois cumulent donc 12 trimestres au lieu de 4 → **×3 exactement**.

Mesuré sur le parc réel (2026, ODP seule, ratio identique sur les 30
communes) — chiffres réactualisés après TX-12 :

| Méthode                              | ODP annuelle 2026 |
|--------------------------------------|-------------------|
| Somme des 12 mois (code actuel)      | 105 204 000 FCFA  |
| `generateLines('annuel', …)`         |  35 068 000 FCFA  |

⚠ La part **TM** de la somme, elle, est correcte (la TM est réellement
mensuelle). Le correctif ne peut donc pas être un `/3` global : il faut
calculer l'ODP en **un seul appel annuel** et garder la somme des 12 mois
pour la TM.

Même symptôme sur la matrice mensuelle de `showCommune()` (ligne ~498) :
chaque mois d'un trimestre affiche un trimestre entier, donc le cumul
annuel de la colonne est lui aussi ×3.

**Non corrigé volontairement** : la règle N°5 du `CLAUDE.md` impose une
validation écrite de la patronne avant toute modification d'un calcul
fiscal. Le chiffre affiché sur la fiche commune change de 315 M à 105 M —
décision métier, pas décision technique.

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

### TX-13 — La FACTURATION applique encore le ×3 sur l'ODP (constaté 2026-09-23)

TX-12 a retiré le ×3 du module Taxes (`TaxCalculationService`, dashboard,
détail, PDF mairie, Excel) sur règle validée par écrit : **ODP = tarif
mensuel × m² × nb trimestres**.

Mais `App\Services\InvoiceCalculator::calculateLine()` (ligne ~147)
applique toujours l'ancienne règle TX-9 :

```php
$odpAuto = ($odpRate * 3) * $m2 * $qte * $trimestresODP;   // forfait trimestriel ×3
```

Conséquence : **CIBLE refacture au client 3× l'ODP qu'elle doit
réellement à la commune.** Sur un panneau 50 m² à 3 000 F dans une
commune, sur un trimestre :

| | Montant |
|---|---|
| Ce que le module Taxes dit devoir à la mairie | 150 000 FCFA |
| Ce que la facture client porte | 450 000 FCFA |

Périmètre concerné (tous consomment `InvoiceCalculator`) :
`InvoiceController`, `QuoteController`, `InvoiceFromCampaignBuilder`,
`QuoteBuilder`, `admin/invoices/partials/_form-fne.blade.php` (JS de
prévisualisation temps réel), `resources/views/pdf/quote.blade.php`.

Bonne nouvelle : les montants sont **figés en base**
(`invoice_lines.odp_ligne`, `odp_rate_applique`, `odp_amount_override`),
donc aligner le calcul ne modifierait **pas** les factures déjà émises —
seulement les nouvelles et les brouillons recalculés.

**Non corrigé volontairement** : règle N°5 du `CLAUDE.md`. La règle a été
validée pour le module Taxes (ce que CIBLE doit à la mairie) ; l'étendre
à ce que CIBLE facture au client est une **décision commerciale** qui
divise par 3 une ligne de revenu. Doit être validée explicitement avant
toute modification.
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
