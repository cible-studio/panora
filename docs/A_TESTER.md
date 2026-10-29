# À TESTER SUR LE POSTE PRINCIPAL

> Vérifications qui demandent la **vraie base de données**, donc
> impossibles depuis un poste dont la base locale est vide.
>
> Ce fichier est versionné exprès : il passe d'un poste à l'autre.
> Quand une ligne est faite, **cocher et noter le résultat**, puis
> retirer l'entrée une fois close.

---

## 1. Cohérence des chiffres financiers *(ajouté le 2026-10-29)*

**Pourquoi** : trois écarts faisaient diverger la page Finance de la liste
des factures (restant dû qui ne déduisait pas les versements, avoirs
comptés d'un côté seulement, repli `total_a_payer → amount_ttc` absent des
sommes SQL). Corrigés le 2026-10-29, mais **l'ampleur réelle sur les
données n'a pas pu être mesurée**.

```
php artisan finance:verifier
php artisan finance:verifier --annee=2026
```

Lecture seule, elle ne modifie rien. Six sections :

| # | Section | Ce qu'on cherche |
|---|---|---|
| 1 | Factures par statut | **Combien de FCFA en « Générée » / « Validée »** → sert à trancher le point 2 ci-dessous |
| 2 | `total_a_payer` à 0 | Des factures qui comptaient **zéro** dans les totaux de Finance |
| 3 | Avoirs | Montant écarté des totaux |
| 4 | Restant dû | **L'écart chiffré** entre l'ancienne formule et la vraie |
| 5 | Sur-paiements | Versements supérieurs au montant dû (saisie en double ?) |
| 6 | Rattachement client | Factures sans client existant, invisibles en recouvrement |

- [ ] Commande lancée, résultat noté ici :
      _(coller le résumé)_
- [ ] Les écrans concordent après correction : la liste des factures et
      `/admin/finance` annoncent le **même** restant dû.

⚠ **Lire la section 6 avant de modifier un de ces calculs** : elle liste
les quatre pièges rencontrés pendant l'audit, dont deux qui ne lèvent
aucune erreur et donnent silencieusement un total à zéro.

---

## 2. Règle métier à trancher — statuts « Générée » et « Validée »

**Non tranchée au 2026-10-29. Rien n'a été codé dans un sens ou dans
l'autre** (règle N°5 du CLAUDE.md : une règle financière doit être validée
par écrit avant d'être codée).

Aujourd'hui, `FinancialDashboardService` n'écarte que `brouillon` et
`annulee`. Une facture **générée** ou **validée** — donc *pas encore
envoyée au client* — entre dans « Facturé sur la période » et dans le
« Dû ».

La question à poser à la direction :

> Une facture générée ou validée, mais pas encore envoyée au client,
> doit-elle compter dans le chiffre d'affaires facturé et dans le montant
> dû ?

- [ ] Réponse de la direction :
- [ ] Si « non » : écarter `generee` et `validee` dans
      `FinancialDashboardService::openInvoicesQuery()` **et**
      `invoicesQueryForPeriod()`, puis vérifier les écrans voisins
      (accueil, performance commerciale, fiche client, exports).

La section 1 de `finance:verifier` donne le montant concerné : c'est lui
qui permet de juger si la question est urgente.

---

## 3. Écrans à ouvrir après les refontes du 2026-10-29

Ces changements ont été livrés sans avoir pu être vus dans l'application.

**Lignes de liste cliquables** (`3063c15d`) — un clic n'importe où dans la
ligne ouvre le détail :

- [ ] Campagnes, poses, réservations, clients : **cocher une case ne doit
      pas ouvrir la fiche**
- [ ] Piges : clic sur la photo → agrandissement, clic ailleurs → la fiche
- [ ] Taxes : clic sur une ligne → fiche commune ; les boutons 💰 🏛️ 📋
      gardent leur rôle
- [ ] Devis en corbeille : aucune ligne cliquable (leur fiche répond 404)
- [ ] Panneaux de régies externes : lignes inertes (pas de fiche propre)

**Retour intelligent** (`390dd155`) :

- [ ] Performance commerciale → « CA HT équipe » → le bouton
      « ‹ Performance commerciale » apparaît bien
- [ ] Ouvrir « Factures » **depuis le menu** → aucun bouton retour (voulu)
- [ ] Créer une équipe depuis Performance équipes → après enregistrement,
      on revient bien à Performance équipes
- [ ] Changer un filtre sur une liste → pas de bouton « Retour » vers le
      même écran

**Nom d'entreprise unique** (2026-10-29, `App\Support\NomEntreprise`) —
testé sur SQLite et dans un navigateur avec un serveur simulé, jamais
dans l'application :

- [ ] Disponibilités → « Nouveau client » → taper `coris-bank` : encadré
      rouge « existe déjà » ; **Utiliser cette fiche** sélectionne CORIS
      BANK sans créer de fiche, l'interlocuteur saisi s'y ajoute
- [ ] Même fenêtre, `MOOV AFRICA` : encadré orange avec MOOV ; sans cocher
      « C'est une autre entreprise », la création est refusée
- [ ] Nouvelle campagne → « Nouveau client » : mêmes deux essais
- [ ] Clients → Nouveau, puis Modifier une fiche sans toucher au nom : pas
      d'alerte, l'enregistrement passe (même pour un doublon existant
      comme ADDOHA)
- [ ] Nouvelle campagne depuis une réservation, en changeant le client :
      refusé, « la campagne doit être sur le même client »

---

## 4. Migration en attente

- [ ] `php artisan migrate` — `2026_10_29_110000_add_catalogues_par_zone_to_diffusion_envois`
      (deux catalogues de disponibilités par envoi, Abidjan et intérieur).
      Vérifiée **up et down** sur SQLite, jamais jouée sur MySQL.

---

## 5. Modèle Brevo à remplacer

- [ ] Coller `docs/diffusion/modele-brevo-disponibilites-leger.html` dans
      Brevo (modèle n° 14). Tant que ce n'est pas fait, le mail client ne
      montre **qu'un seul** lien au lieu des deux (Abidjan / intérieur).
      `params.LIEN_PDF` reste fourni exprès pour que le bouton actuel ne
      devienne pas mort entre-temps. Cf. `docs/TECHNICAL_DEBT.md`.
      Le modèle peut être collé **avant** la mise en production des deux
      catalogues : sans lien par zone, il affiche le catalogue complet.
- [ ] Après collage : un envoi en mode test depuis dev montre **deux**
      liens (Abidjan, intérieur), et un envoi depuis la prod en montre
      **un** (« Voir les disponibilités — PDF »), qui s'ouvre.

---

## 6. Pour qui reprend le contrôle financier — pièges déjà rencontrés

> Contexte : l'audit du 2026-10-29 a été fait depuis un poste **sans
> données** (base locale vide). Les trois écarts corrigés l'ont été en
> comparant des *formules*, jamais des *chiffres*. D'où la section 1 :
> c'est elle qui dira si ces cas existent vraiment en base.

**Ce qu'il reste à faire, dans l'ordre :**

1. Lancer `php artisan finance:verifier` et coller le résumé en section 1.
2. Ouvrir `/admin/invoices` et `/admin/finance` côte à côte : le restant
   dû doit être **le même nombre** des deux côtés. C'est le contrôle qui
   prouve que la correction a pris.
3. Si la section 1 montre des montants en « Générée » / « Validée »,
   poser la question de la section 2 à la direction.

**Quatre pièges à connaître avant de toucher à ces calculs :**

- **Aucun écran n'est faux tout seul.** L'incohérence n'apparaît qu'en
  comparant deux endroits. Le cas trouvé le 2026-10-29 : la liste des
  factures affichait 3 638 576 sur la ligne et 6 660 000 dans son propre
  KPI, en haut de la même page. Toujours lire le partial de ligne **et**
  le contrôleur ensemble.

- **`COALESCE` ne suffit pas pour `total_a_payer`.** La colonne est
  `NOT NULL DEFAULT 0` : `COALESCE(total_a_payer, amount_ttc)` ne voit que
  `NULL` et laisse passer le **0**, qui est justement le cas à traiter.
  Utiliser `Invoice::SQL_MONTANT_TOTAL` (SQL) ou `Invoice::montantTotal()`
  (PHP), jamais une expression maison.

- **Changer une somme peut casser le chargement.** Passer de
  `sum('total_a_payer')` à `sum(fn ($i) => $i->montantTotal())` ne marche
  que si `amount_ttc` est dans le `select` de la relation ; sinon le total
  tombe à **zéro** sans erreur. Idem pour `remainingAmount()`, qui a besoin
  de `payments` et `schedules` sous peine d'une requête par facture.
  Vérifier le `with()` du service, pas seulement le diff de la vue.

- **Les DEVIS ne suivent pas la même règle, et c'est voulu.** `Quote` a
  les deux mêmes colonnes avec le même défaut à 0, mais **aucun** écran
  n'y applique de repli. Y ajouter `montantTotal()` par symétrie
  changerait tous les montants de devis d'un coup. Cf.
  `docs/TECHNICAL_DEBT.md`.
