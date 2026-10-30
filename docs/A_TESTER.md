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

⚠ **Lire la section 5 avant de modifier un de ces calculs** : elle liste
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

## 4. Regroupement des fiches en double *(ajouté le 2026-10-30)*

Bouton **🔀 Regrouper** de la fiche client (Direction seulement), règle dans
`App\Services\Clients\RegroupementClients`. Testé sur SQLite **et** sur une
copie MySQL du poste (`panora_essai`) avec un serveur réel : aperçu, refus
sans « J'ai compris », regroupement, liaison, ancien lien, historique des
factures, refus de confirmer un panneau pris. **Jamais sur les vraies
données de production.**

Deux migrations partent avec ce lot, jouées au démarrage (`migrate --force`) :
`2026_10_30_090000_create_notifications_table` et
`2026_10_30_100000_create_client_regroupements_table`.

- [x] Coolify : la production a redémarré sans erreur après la mise en ligne
      *(fait le 2026-10-30 — les regroupements ont tourné en production)*
- [ ] Moteur des tables en production : `SHOW TABLE STATUS` → `Engine`
      = InnoDB (cf. TECHNICAL_DEBT, MyISAM). Le regroupement vérifie tout
      **avant** d'écrire, mais seul InnoDB annule un regroupement interrompu
- [x] Les 8 doublons, **quand personne ne travaille dans Panora** :
      *(fait le 2026-10-30 par la Direction, + ORO dans ASSEKE ORO : tous passés)*
      ouvrir la fiche à garder → 🔀 Regrouper → choisir l'autre → lire
      l'aperçu → cocher « relier » seulement si Panora le propose
- [ ] Après chaque regroupement : la campagne est toujours dans le même
      statut, Disponibilités montre les mêmes panneaux occupés, l'ancien
      lien de la fiche ouvre la fiche gardée
- [ ] DENTSU (deux fiches avec campagnes et factures) : regarder les NCC
      dans l'aperçu avant de valider
- [ ] Sheet → « Envoyer à Panora » **après** les regroupements : aucun
      « Entreprise supprimée » pour les fiches regroupées

**Contrôles de disponibilité ajoutés le même jour** (règle validée : refus
si un panneau est déjà pris par une réservation confirmée ou une campagne) :

- [ ] Confirmer une réservation dont un panneau est tenu par une campagne :
      message « Panneau déjà pris… » avec le panneau et le client
- [ ] Import PDF d'une campagne sur des panneaux déjà pris : encadré rouge
      dans l'aperçu, bouton bloqué
- [ ] Nuit suivante : une campagne planifiée bloquée par un conflit donne
      l'alerte « ⚡ … bloquée par un conflit de panneaux » (et non plus
      « sans panneau »)

---

## 5. Pour qui reprend le contrôle financier — pièges déjà rencontrés

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

---

## 6. Variables à poser sur le serveur *(ajouté le 2026-10-30)*

Ces trois réglages ont un défaut dans le code, mais c'est le `.env` du
serveur qui fait foi. **Tant qu'ils n'y sont pas, rien ne change en
production.**

```dotenv
# Le C majuscule demandé par la direction
BREVO_SENDER_NAME="CIBLE CI — Service Commercial"

# Le numéro commercial dans le pied des mails, des PDF et des pages publiques
CHARTE_TELEPHONES="+225 07 98 49 66 74 · +225 07 00 78 06 27 · +225 27 22 20 80 08"

# Copie des mails commerciaux et de facturation envoyés au client
MAIL_COPIE_COMMERCIALE=stephane.oponou@cible-ci.com
```

- [x] **Les trois posées sur le serveur, redéploiement fait le 2026-10-30.**

Contrôle en une commande, à lancer sur le serveur — si une valeur est
vide ou ancienne, c'est que le cache de configuration n'a pas été refait :

```
php artisan tinker --execute="echo config('brevo.sender.name'), PHP_EOL, config('charte.coordonnees.telephones'), PHP_EOL, config('mails.copie_commerciale'), PHP_EOL;"
```

Attendu :

```
CIBLE CI — Service Commercial
+225 07 98 49 66 74 · +225 07 00 78 06 27 · +225 27 22 20 80 08
stephane.oponou@cible-ci.com
```

- [ ] Les trois valeurs sont celles attendues
- [ ] Un devis envoyé en test : Stéphane est bien en copie, le pied de
      page porte les trois numéros, l'expéditeur affiche « Service **C**ommercial »
- [ ] Une facture envoyée : même vérification
- [ ] Un mail de **suivi** (preuve d'affichage, maintenance) : Stéphane
      n'est **pas** en copie — c'est voulu
- [ ] *Clients → Journal des mails* : la colonne « Copie » porte bien
      l'adresse sur les mails commerciaux, et reste vide sur les autres

---

## 7. Nouveau calendrier de diffusion *(ajouté le 2026-10-30)*

Les catalogues partent désormais **avant** la période qu'ils annoncent :
celui du 1er du mois entre le **20 et le 25 du mois précédent**, celui du
15 entre le **1er et le 5**. Jour exact : le premier jour **ouvrable** de
la fenêtre, à 10 h.

```
php artisan dispos:diffuser --simuler=2026-11-20   # → catalogue de décembre
php artisan dispos:diffuser --simuler=2026-11-02   # → catalogue du 15 novembre
php artisan dispos:diffuser --simuler=2026-11-10   # → rien ce jour-là
```

- [ ] Les trois simulations donnent le résultat attendu
- [ ] Écran *Diffusion dispos* : la carte « Prochain envoi » annonce une
      **fenêtre d'envoi** et une période cohérentes

⚠ **Le catalogue de novembre 2026 ne partira jamais tout seul** : sa
fenêtre (20-25 octobre) était passée au moment de la livraison, et il n'y
a pas de rattrapage automatique — règle validée le 2026-10-30.

- [ ] Envoyer le catalogue de novembre **à la main**, depuis *Diffusion
      dispos → Envoi manuel*. Fait le : ______

---

## 7. Module Devis — optimisations *(ajouté le 2026-10-30)*

Quatre demandes livrées sur `develop`. **Le module Devis n'est pas en
production** (phase de test), donc tout se vérifie sur l'environnement de
test.

- [ ] `php artisan migrate` — `2026_10_30_100000_add_tva_applicable_to_quote_services`
      (TVA au choix par service). Additive, défaut `true` : aucun devis
      existant ne change de total.

**TVA sur les services annexes**

- [ ] Nouveau devis → ajouter deux services, en décocher un : la colonne
      TTC et le sous-total suivent, exactement comme sur la facture FNE
- [ ] Enregistrer, rouvrir : le choix est conservé
- [ ] Accepter le devis et le convertir : la facture reprend le même
      choix, et **le même montant**

**Numéro et titre**

- [ ] Nouveau devis : le numéro `DEV-2026-xxx` s'affiche, non modifiable
- [ ] Choisir un client → le titre se remplit ; ajouter une campagne → il
      se complète
- [ ] Écrire par-dessus, puis changer de client : le titre **ne doit plus
      bouger**

**Recherche de panneaux**

- [ ] Dans une ligne, taper `bouet` → « Port Bouët » doit sortir
- [ ] Taper `ABG` → `ABG-002` oui, `CABG-001` non
- [ ] Taper un nom de commune → les panneaux de cette commune sortent

**Aperçu en direct**

- [ ] Le récapitulatif et l'aperçu apparaissent à droite et suivent le
      défilement
- [ ] Chaque saisie les met à jour après une courte pause
- [ ] Le total de l'aperçu est **le même** que celui du devis une fois
      enregistré — c'est le contrôle qui compte
- [ ] Sur un écran étroit (< 1280 px), la colonne passe sous le formulaire
