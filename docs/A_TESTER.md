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
