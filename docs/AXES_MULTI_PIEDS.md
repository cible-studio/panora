# Axes multi-pieds — état, décisions, plan

> Chantier ouvert le **2026-11-11**. Ce document est le point de vérité du
> sujet : ce qui a été mesuré sur la base de production, ce qui a été
> tranché, ce qui attend encore une décision.
>
> ✅ **Phase 1 validée par l'utilisateur et livrée le 2026-11-11** (branche
> `develop`). Ce qui est fait, ce qui reste et comment déployer : section 8.
> La phase 2 (le prix au pied) attend la décision ODP / TM.

---

## 1. Le problème

Un axe comme *Saint-Jean – Carrefour la Vie* est **une seule ligne
`panels`**, alors qu'il regroupe 11 supports distincts le long de la voie.
Conséquences :

- un client ne peut pas réserver une partie de l'axe — c'est tout ou rien ;
- la disponibilité ne montre pas les pieds encore libres ;
- une seule tâche de pose est créée pour 11 pieds à poser.

**Vocabulaire retenu :**

| Terme | Définition |
|---|---|
| **Axe** | Tronçon de voie regroupant plusieurs pieds. Aujourd'hui = une ligne `panels`. |
| **Pied** | Support individuel sur l'axe. **Unité de réservation, de disponibilité et de pige.** |
| **Face** | Surface d'affichage d'un pied. Un pied Lumipub en porte 3 ; un pied de `BK-002` en porte 2. |

---

## 2. Ce que la production dit vraiment *(mesuré le 2026-11-11)*

Relevé par un script de lecture seule sur la base de production. Le parc
compte **348 panneaux actifs** (34 supprimés) — le chiffre de 364 qui
circule dans les docs est antérieur.

**13 axes, 101 pieds, 291 faces.** La structure est déjà écrite dans le
nom du panneau, en texte libre, et se lit automatiquement pour 13 sur 14 :

| Référence | Axe | Commune | Pieds | Faces/pied | Tarif mensuel | Statut |
|---|---|---|---|---|---|---|
| `CDYLUP-001` | Hôtel Ivoire – St Jean | Cocody | 10 | 3 | 130 000 | occupé |
| `CDYLUP-002` | St Jean – Maison du Parti | Cocody | 6 | 3 | 130 000 | confirmé |
| `CDYLUP-003` | St Jean – Corniche | Cocody | 3 | 3 | 130 000 | libre |
| `CDYLUP-004` | St Jean – Carrefour Lavie | Cocody | **11** | 3 | 130 000 | libre |
| `CDYLUP-005` | RTI – Pont Lycée Technique | Cocody | 4 | 3 | 130 000 | libre |
| `CDYLUP-006` | RTI – CHU | Cocody | 10 | 3 | 130 000 | libre |
| `CDYLUP-007` | BMW – Station Oil Lybia | Cocody | 9 | 3 | 130 000 | occupé |
| `CDYLUP-008` | Mahikari – 22ᵉ Arr. Angré | Cocody | 9 | 3 | 130 000 | occupé |
| `CDYPUB-09` | 2 Plateaux – Carrefour Laspalmas | Cocody | 10 | 3 | **100 000** ⚠ | libre |
| `CDYLUP-10` | Opéra – Pharmacie St Gabriel | Cocody | 2 | 3 | 130 000 | libre |
| `TVILUP-001` | Biafra – Solibra | Treichville | 10 | 3 | 130 000 | occupé |
| `TVILUP-002` | Solibra – Drocolor | Treichville | 5 | 3 | 130 000 | occupé |
| `BK-002` | Carrefour Adiaké Assinie | Adiaké-Assinie | 12 | **2** | **100 000** ⚠ | maintenance |

Tous sont de catégorie **Lumipub** (3 m² par face) sauf `BK-002`, qui est
un **Classique de 6 m² en double face** : le besoin n'est donc pas
propre au Lumipub.

**`MRYLUP-001` « Marcory Résidentiel (1 Lumipub) » n'est pas un axe** : un
Lumipub isolé, 1 pied. Il est détecté par le script parce qu'il est de
catégorie Lumipub. **À ne pas découper.**

### Le prix n'a jamais été corrigé

```
PU_corrige_main=0   PU_laisse_au_tarif=12   PU_a_zero=2
```

Sur 14 lignes de réservation portant un axe, **aucune** n'a vu son prix
ajusté à la main. L'hypothèse « les commerciaux compensent » est fausse :
l'axe entier est systématiquement vendu au prix d'un pied.

En valeur de catalogue, sur les 13 axes : **1 630 000 FCFA/mois
aujourd'hui contre 12 470 000 au prix réel**.

**Mais presque rien n'a été facturé par Panora.** Une seule ligne de
facture existe sur un axe, et elle est en brouillon. Les grosses
réservations annuelles portent le préfixe `AUTO-` et affichent le *même*
total de 1 625 000 sur cinq panneaux différents : ce sont des reprises
d'historique importées, pas des devis commerciaux. **Le risque est donc
devant, pas derrière** — il n'y a pas de manque à gagner à recouvrer, il y
a un tarif catalogue à corriger avant les prochains devis.

### Les piges comptent déjà les pieds

C'est l'argument le plus solide du chantier : le terrain contourne déjà le
problème.

| Axe | Pieds | Piges sur une même campagne |
|---|---|---|
| `CDYLUP-007` | 9 | **9** |
| `CDYLUP-008` | 9 | **9** |
| `TVILUP-001` | 10 | **10** |
| `CDYLUP-10` | 2 | **2** (sur deux campagnes) |
| `CDYLUP-001` | 10 | 20 (deux passages) |
| `TVILUP-002` | 5 | 10 (deux passages) |

Les équipes photographient chaque pied séparément et accrochent les 9, 10
ou 20 piges à l'unique ligne de l'axe. Côté **poses**, rien ne suit : 3
tâches seulement pour un axe de 10 pieds.

Le découpage ne crée pas un besoin : **il régularise une pratique.**

---

## 3. Décisions prises

| # | Question | Décision | Date |
|---|---|---|---|
| 1 | Que valent les 130 000 FCFA ? | **Un pied** | 2026-11-11 |
| 2 | Un pied se réserve-t-il avec ses 3 faces ? | **Oui** — l'unité de réservation est le pied, faces comprises | 2026-11-11 |
| 3 | ODP par pied ? | **Ne pas y toucher.** L'ODP est gérée autrement ; décision reportée | 2026-11-11 |
| 4 | TM par pied ou par face ? | **Ne pas y toucher.** Décision reportée | 2026-11-11 |
| 5 | Corriger la facture brouillon `FAC-2026-019` ? | **Non**, les brouillons attendent | 2026-11-11 |
| 6 | Prix facturé d'un axe ? | **La somme des prix HT des pieds réservés** (prix négocié par la direction ; 0 = pied offert). Une ligne par axe, « … — 5 pieds » | 2026-11-12 |
| 7 | ODP et TM d'un axe ? | **Jamais calculées par Panora**, ni sur les factures, ni sur les devis, ni dans le module Taxes : 0 par défaut, le comptable saisit les montants. Remplace les décisions 3 et 4, et rend la « phase 2 » sans objet | 2026-11-12 |
| 8 | Deux clients sur un même axe ? | **Oui** : 5 pieds à l'un, 5 à l'autre, ou 2 pieds offerts à un gros client. Un axe ne se vend pas comme un panneau ordinaire | 2026-11-12 |

---

## 4. ⚠ Le point dur : facturer au pied touche l'ODP, qu'on le veuille ou non

`InvoiceCalculator::calculateLine()` applique **le même multiplicateur aux
trois calculs** ([InvoiceCalculator.php:80-88](../app/Services/InvoiceCalculator.php#L80-L88),
[162-163](../app/Services/InvoiceCalculator.php#L162-L163)) :

```
LOYER = pu_ht_mensuel × quantite × duree_mois
ODP   = odp_rate × surface × quantite × mois_ODP
TM    = tm_rate  × surface × quantite × mois_TM
```

Conséquence : dès qu'une réservation porte 6 pieds, la facture sort 6
lignes (ou une ligne à quantité 6) et **l'ODP comme la TM sont multipliées
par 6 d'elles-mêmes**. « Facturer au pied » et « laisser l'ODP tranquille »
ne peuvent pas être vrais en même temps.

Il existe bien `odp_amount_override` / `tm_amount_override` sur
`invoice_lines` (2026-08-03), mais ils sont prévus pour un **accord
ponctuel négocié avec une commune**. S'en servir pour figer une taxe
donnerait au comptable des montants forcés sans explication : **à écarter.**

D'où le plan en deux phases ci-dessous.

---

## 5. Plan proposé — à valider

### Modèle de données : un panneau par pied, pas de table `pieds`

Panora a déjà résolu « plusieurs supports au même endroit » en multipliant
les lignes `panels` (les double-faces suffixés `A`/`B`, et
`PanelReferenceGenerator` gère nativement une face `A/B/C/D`).

Et `panel_id` est partout : **8 tables** le portent (`reservation_panels`,
`campaign_panels`, `invoice_lines`, `quote_lines`, `piges`, `pose_tasks`,
`maintenances`, `panel_photos`), **669 occurrences dans 69 fichiers PHP** et
28 vues Blade. `AvailabilityService` fait 1209 lignes, se décrit comme
« source de vérité unique », et porte un verrou pessimiste
`Panel::lockForUpdate()` avec un index dédié.

Une table `pieds` séparée obligerait à **dupliquer tout cela** — une
seconde disponibilité, un second verrou anti-double-booking — sur la pièce
qui empêche de vendre deux fois le même emplacement. Un panneau par pied
en hérite gratuitement.

**Deux colonnes additives sur `panels`** (règle n°3) :

- `axe_id` — `BIGINT UNSIGNED NULL`, clé étrangère vers `panels.id`, indexée.
  `NULL` = panneau normal ou ligne-axe.
- `est_axe` — `BOOLEAN NOT NULL DEFAULT 0`. Marque la ligne parente.

**Référence des pieds : `<AXE>-P01` … `-P11`.** Elle se termine par des
chiffres, donc `TaxCalculationService::referencePhysique()` — qui ne retire
qu'un suffixe `A`/`B` collé à un chiffre — ne les regroupera pas et la
fusion des faces existante reste intacte.

**La ligne-axe reste en base** et garde tout son historique (les 20 piges
de `CDYLUP-001`, les poses, la réservation `AUTO-000002`, la facture
brouillon). Rien n'est réécrit. Elle cesse simplement d'être réservable.

### Phase 1 — la structure, fiscalement neutre

Ce qui change :

- création des 101 pieds, rattachés à leur axe ;
- `AvailabilityService` ignore les lignes `est_axe = 1` : elles ne sont ni
  disponibles ni bloquantes (sinon l'axe et ses pieds seraient comptés deux
  fois) ;
- écran de réservation : on choisit l'axe, puis on coche les pieds, avec
  leur statut sur la période et un compteur en direct. Aucun minimum ;
- `CampaignObserver` crée **une tâche de pose par pied** — automatique, il
  travaille déjà par `panel_id` ;
- **regroupement d'affichage** : une entrée par axe avec son nombre de
  pieds proposés, dans le PDF des disponibilités **et** dans le catalogue
  Brevo (deux rendus distincts — `DisponibilitesPdfBuilder` et
  `DiffusionDisponibilitesService::rendreCatalogue`) ;
- encadré « Comment ça marche » en tête du PDF.

Ce qui **ne change pas** :

- **la facture.** `InvoiceFromCampaignBuilder` regroupe les pieds sur leur
  axe et sort une ligne par axe, quantité 1, au prix du catalogue —
  exactement comme aujourd'hui ;
- **l'ODP, la TM, `TaxCalculationService`** : zéro modification, garantie
  par le point précédent ;
- **les panneaux simples** : aucun changement.

**Test de non-régression exigé** : construire la facture des 3 campagnes
vivantes avant et après la migration, et vérifier que chaque ligne est
identique au franc près.

### Phase 2 — le prix, après la confirmation ODP/TM

À n'ouvrir qu'une fois les décisions 3 et 4 rendues :

- `invoice_lines.quantite` = nombre de pieds réservés → loyer correct ;
- **l'ODP et la TM suivent automatiquement le même facteur** (section 4).
  C'est précisément ce qu'il faut avoir validé avant.

### Migration des données

- 13 axes à découper → 101 pieds ;
- **3 réservations** et **3 campagnes** vivantes à rattacher à tous les
  pieds de leur axe (volume faible : c'est le bon moment) ;
- **15 photos** au total sur les 14 axes : les pieds affichent la photo de
  leur axe, pas de duplication ;
- piges et poses historiques **restent sur la ligne-axe** — rien ne permet
  de savoir quelle pige correspond à quel pied ;
- 1 maintenance reste sur son axe ;
- `MRYLUP-001` : ne pas y toucher.

### Calendrier — le catalogue

Les 6 axes aujourd'hui `libre` totalisent **40 pieds**. Sans regroupement
d'affichage, `NB_PANNEAUX` passerait de **255** (chiffre réellement parti
aux clients le 6/11) à environ 290, et le PDF gagnerait une trentaine de
pages pour 15 photos disponibles.

**Le découpage ne part pas en production avant l'envoi automatique du
20/11.** Soit on gèle jusqu'après, soit le regroupement d'affichage est
livré dans le même lot que la migration — jamais après.

---

## 6. Ce qui attend encore

- [ ] **ODP** : un axe de 11 pieds, c'est 11 ODP ou 1 ? *(décision reportée
      le 11/11 ; l'utilisateur revient avec confirmation)*
- [ ] **TM** : par pied, ou par face (3 par pied) ? *(idem)*
- [ ] **`CDYPUB-09` à 100 000** quand tous les autres Lumipub sont à
      130 000, et sa référence ne suit pas la convention (`CDYPUB` au lieu
      de `CDYLUP`) : erreur de saisie, ou tarif négocié figé ?
- [ ] **`CDYLUP-10` réservé deux fois à PU = 0** (`RES-QNHQIVXJ` et
      `AUTO-000050`) : gratuité voulue, ou oubli ?
- [ ] **Validation du plan de la section 5** avant toute ligne de code.

---

## 7. À ne pas refaire

- **Ne pas se fier à `SeedParc.php`** pour l'inventaire : il annonçait 14
  pieds pour `CDYLUP-004`, la production en compte 11.
- **Ne pas se fier à `nombre_faces`** : le champ porte 1 sur 320 panneaux,
  4 sur 27 (les Chevalets, faces du chevalet) et 3 sur un seul
  (`CDYLUP-10`, faces d'un pied). Deux significations différentes dans la
  même colonne.
- **Ne pas créer de table `pieds`** sans avoir mesuré ce que ça coûte à
  `AvailabilityService` (section 5).
- **Ne pas livrer le découpage sans le regroupement d'affichage** : les
  clients verraient le parc gonfler sans explication.


---

## 8. Phase 1 — livrée le 2026-11-11

### Ce qui est en place

| Élément | Où |
|---|---|
| Colonnes `axe_id`, `est_axe`, `pied_numero`, `faces_par_pied` | `2026_11_11_100000_add_axe_to_panels_table.php` |
| **La règle, écrite une seule fois** | `app/Services/Axes/AxeService.php` |
| Relations, portées `vendables()` / `axes()` | `app/Models/Panel.php` |
| Un pied hérite du blocage de son axe — **8 méthodes** | `app/Services/AvailabilityService.php` |
| Facture : regroupement des pieds, bascule `FACTURER_AU_PIED` | `app/Services/InvoiceFromCampaignBuilder.php` |
| Un axe = UNE entrée dans le PDF **et** le catalogue Brevo | `DisponibilitesPdfBuilder::regrouperLesPieds()` |
| Encadré « Comment lire cette disponibilité » + « Pieds proposés » | `admin/reservations/pdf/disponibilites-images.blade.php` |
| Les axes ne sont plus proposés (4 écrans) | réservation ×2, campagne, devis |
| Badge « AXE · N pieds » / « pied N » en inventaire | `admin/panels/partials/table-rows.blade.php` |
| Badge « ⛓ pied 3/11 » + bouton « tout l'axe » au sélecteur | `admin/reservations/disponibilites.blade.php` |
| La règle appliquée au sélecteur, qui recalculait seul | `ReservationController::panneauxAjax` |
| Découpage des données, simulation par défaut | `php artisan axes:decouper` |
| Garde-fous | `tests/Unit/AxesMultiPiedsTest.php` — 28 tests |

Les 8 méthodes de décision adaptées : `getUnavailablePanelIds`,
`conflitsFermes`, `getOccupationDetails`, `quickCheck`,
`getPanelAvailabilityData`, `getInternalConflictMap`,
`getInternalPanelBookingMap` et `syncPanelStatuses` — ce dernier parce que
`panels.status` est le cache lu par tous les badges d'inventaire : un pied
affiché « libre » alors que son axe est vendu, c'est une vente en double
annoncée.

### Trois décisions techniques à connaître

1. **Les pieds naissent sans position GPS.** Recopier celle de l'axe ferait
   rendre « trop loin » au contrôle géo des piges, alors que les pieds sont
   répartis sur des centaines de mètres. Le verdict `no_panel_gps` est
   neutre et vrai, et la géolocalisation automatique
   (`gps_source = pige_provisional`) remplit chaque pied depuis sa première
   photo de terrain. **Conséquence : l'écran « GPS manquant » affichera les
   101 pieds** — c'est la liste de travail du media planner, pas un bug.
2. **La référence d'un pied finit par des chiffres** (`CDYLUP-004-P01`).
   `TaxCalculationService::referencePhysique()` ne retire qu'un suffixe
   `A`/`B` collé à un chiffre : un pied n'est donc jamais fusionné comme une
   face, et le calcul de l'ODP reste intact.
3. **Le code tourne avant la migration.** `AxeService::colonnesPresentes()`
   coupe tout si `axe_id` est absente. Sans ce garde-fou, toute réservation
   planterait entre le déploiement et le `migrate`.

### Déploiement, dans cet ordre

```bash
php artisan migrate                 # ajoute les 4 colonnes
php artisan axes:decouper           # SIMULATION : montre les 13 axes et 101 pieds
php artisan axes:decouper --appliquer
```

`axes:decouper` est rejouable, recopie les tarifs **tels quels** (jamais
« corrigés » : deux anomalies attendent une décision — cf.
`TECHNICAL_DEBT.md`), et accepte `--axe=CDYLUP-004` pour n'en traiter qu'un.

### Le sélecteur de réservation *(terminé le 2026-11-11)*

Chaque pied porte un badge **« ⛓ pied 3/11 · 3 faces »** — en vue cartes
comme en vue liste — et un bouton **« ⛓ 5 »** qui coche d'un geste les cinq
pieds libres de l'axe, ou les décoche s'ils l'étaient déjà. Le compteur
annonce exactement ce que le bouton va changer, pagination comprise. La
logique est écrite une fois, dans `DISPO._axeInfo()`.

🔴 **Un défaut sérieux trouvé en chemin.** `ReservationController::panneauxAjax`
— l'écran de sélection — **recalcule la disponibilité lui-même** : il
n'appelle pas `AvailabilityService`, il a ses propres requêtes sur
`reservation_panels` et `campaign_panels`. Un pied d'axe vendu en entier s'y
affichait donc **libre**, et on pouvait le revendre. Corrigé en lui faisant
appliquer la règle via `AxeService` — qui reste la seule source — mais la
duplication de fond est inscrite comme dette : cet écran devrait passer par
le service, comme les 22 autres appelants.

### Ce qui reste dans la phase 1

Rien. La phase 2 (le prix au pied) attend la décision ODP / TM.

> **Mise à jour du 2026-11-12** — la question ODP / TM est tranchée
> autrement (décisions 6 à 8, § 3) : ODP et TM d'un axe à la main, prix =
> somme des pieds. Livré le même jour : module Taxes sans axes, factures et
> devis à une ligne par axe avec cases ODP/TM, axe entier repris pied par
> pied (renouvellement, import), une carte par axe et la liste déroulante
> des pieds (Disponibilités, campagne, réservation, devis). Défaut corrigé :
> l'écran Disponibilités ne lisait pas `axe_id` — en production, chaque
> pied s'y affichait comme un panneau ordinaire. Contrôles :
> `docs/A_TESTER.md` § 20 et § 21. Ce qui reste : `docs/TECHNICAL_DEBT.md`.

### Non régression attendue

Le test le plus important n'est pas automatisable ici (base locale vide) :
**construire la facture des 3 campagnes vivantes avant et après le
découpage, et vérifier que chaque ligne est identique au franc près** —
`montant_ht_ligne`, `odp_ligne`, `tm_ligne`. C'est la promesse de la
phase 1.