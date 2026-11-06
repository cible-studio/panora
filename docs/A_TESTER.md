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

- [x] Commande lancée en production le 2026-10-30. Résumé :
      · comptées par Finance : 2 partiellement soldées (8 489 800), 1 litige
        (504 000), 1 soldée (205 200) ; écartées : 16 brouillons
        (195 242 564 — à faire relire par le comptable : générer ou annuler),
        1 annulée (4 130 000) ;
      · 4 brouillons à `total_a_payer` = 0 (FAC-2026-006/007/008/011,
        46 665 000) : sans effet, les brouillons ne sont pas comptés ;
      · restant dû réel 4 889 800 (l'ancienne liste annonçait 8 489 800 —
        corrigé le 29/10) ; aucun avoir, aucun sur-paiement, aucun client manquant.
- [ ] Les écrans concordent après correction : la liste des factures et
      `/admin/finance` annoncent le **même** restant dû.

⚠ **Lire la section 5 avant de modifier un de ces calculs** : elle liste
les quatre pièges rencontrés pendant l'audit, dont deux qui ne lèvent
aucune erreur et donnent silencieusement un total à zéro.

---

## 2. Statuts « Générée » et « Validée » — tranché

- [x] **Décision de la direction (2026-10-30) : OUI.** Une facture générée ou
      validée, même pas encore envoyée au client, compte dans le chiffre
      d’affaires facturé et dans le montant dû. C’est déjà ce que fait
      `FinancialDashboardService` (seuls `brouillon` et `annulee` sont
      écartés) : rien à changer dans le code.
- [x] **Règle appliquée partout le 2026-10-30** (`Invoice::facturees()`) :
      plusieurs écrans avaient leur propre copie, souvent différente
      (accueil, fiche client, page facture, performance commerciale, liste
      des factures, PDF de la liste). Contrôles ci-dessous.

### Après la mise en ligne de la règle unique du CA (2026-10-30)

Rien n’a pu être rejoué sur une vraie base MySQL (MySQL local arrêté) :
seuls les tests SQLite sont passés. À faire en production, ou sur
`panora_essai` quand MySQL tourne :

- [ ] `php artisan finance:verifier` : la ligne « Générée / Validée » n’est
      plus une anomalie, juste une information. Le « Reste réellement à
      encaisser » (section 4) doit égaler le « Restant » de la liste des
      factures et le dû de la page Finance.
- [ ] Accueil : « CA du mois / de l’année » et « Total à recouvrer » ne
      comptent plus les brouillons → ils peuvent **baisser** (16 brouillons
      = 195 M FCFA au 30/10). C’est voulu.
- [ ] Fiche client → « Synthèse financière » et total facturé de l’en-tête :
      même chiffre, brouillons et avoirs exclus.
- [ ] Page d’une facture → bloc « Client » : « Encaissé TTC » = somme des
      versements, « Reste dû TTC » = ce qui manque ; le lien « Toutes (N) »
      compte toutes les factures, brouillons compris.
- [ ] Liste des factures : cliquer « ✅ Marquer soldée » sur une facture
      envoyée → la tuile « CA Encaissé » **augmente** du solde (avant, elle
      sautait à un autre chiffre), le « Restant » baisse d’autant.
- [ ] Cette facture → onglet versements : un versement « SOLDE_AUTO_… » du
      solde, mode « autre ». « ↩ Retour en brouillon » le retire (un
      versement saisi à la main reste).
- [ ] Facturer une campagne qui a déjà une facture « Générée » ou
      « Validée » : le « reste à facturer » en tient compte (avant, non).
- [ ] Liste des factures → export PDF : la tuile « Encaissé » = versements
      reçus (acomptes compris), plus le TTC des seules factures soldées.
- [ ] Performance commerciale : « factures impayées » et « reste dû »
      comptent aussi les générées / validées ; le CA HT ne compte plus les
      brouillons ni les avoirs.

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
- [x] Moteur des tables en production — dans le terminal Coolify :
      *(fait le 2026-10-30 : `"InnoDB" => 67`, aucune table MyISAM)*
      `php artisan tinker --execute="dump(collect(DB::select('SHOW TABLE STATUS'))->countBy('Engine')->all());"`
      → attendu : InnoDB seul. (`SHOW TABLE STATUS` tapé tel quel dans le shell ne marche pas.)
      Pourquoi : `Engine`
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
- [x] Sheet → « Envoyer à Panora » **après** les regroupements : aucun
      « Entreprise supprimée » pour les fiches regroupées *(30/10 : 2 536 créés,
      seuls refus = contacts sans entreprise)*

**Identité du client figée sur les factures** (décision du 2026-10-30,
migration `2026_10_30_110000_add_identite_client_to_invoices`, jamais jouée
sur MySQL — le serveur local était arrêté) :

- [x] Après la mise en ligne : une facture déjà émise montre le même nom et
      NCC qu'avant (la migration les a figés avec la fiche du jour) *(vu le 30/10 ;
      le menu Devis apparaît aussi en production)*
- [ ] Renommer une fiche client (essai sur dev) : le PDF d'une facture émise
      ne change pas ; sa fiche facture indique « Fiche aujourd'hui : … »
- [ ] Une facture en brouillon suit la fiche ; à sa génération, l'identité
      se fige

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
3. ~~Poser la question des statuts « Générée » / « Validée »~~ : tranchée
   le 2026-10-30 (ils comptent, cf. section 2).

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

- [x] ~~Envoyer le catalogue de novembre à la main~~ *(sans objet depuis le
      2/11 : le bouton envoie désormais le catalogue du 15 au 30 novembre,
      à faire dès que l'offre Brevo est payée, cf. § 10)*

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

---

## 8. Poses annulées masquées des listes *(ajouté le 2026-11-02)*

Une pose annulée (panneau retiré d'une campagne…) reste en base mais ne
s'affiche plus. Règle unique : `PoseTask::sansAnnulees()`.

- [ ] Gestion des poses : plus aucune ligne « Annulée » à l'ouverture ;
      la carte « Total » (« hors annulées ») baisse d'autant.
- [ ] Clic sur la carte « Annulées » → seules les annulées s'affichent,
      autant que le compteur de la carte. « Total » ramène la liste propre.
- [ ] Campagne RHINO/IVOIRE → page « Poses » : ABO-CH002, KSS-CH002,
      MRY-CH003, YOP-CH002 n'apparaissent plus ; « Total » ne les compte plus.
- [ ] Calendrier des poses (semaine du 15/10) : plus de cases rouges
      « Annulée ».
- [ ] Carte des poses : plus de points rouges en vue « Total ».

---

## 9. Droits par rôle — source unique *(ajouté le 2026-11-02)*

Qui voit quoi = `config/acces.php` (menu, routes, règles factures/devis,
tableau de bord). Garde-fou : `tests/Unit/AccesParRoleTest`.

À vérifier en se connectant avec chaque rôle :

- [ ] **MP** : plus de « Facturation » ; `/admin/invoices` tapé à la main →
      page interdite. « Devis » visible, en lecture seule (pas de bouton de
      création ni de modification des prix).
- [ ] **MP** — tableau de bord : plus de CA, encaissements, factures en
      retard, à recouvrer, prévision, top clients / communes. La 3ᵉ carte
      affiche « Panneaux occupés ». Les chiffres des devis restent.
- [ ] **MP** — fiche campagne : plus de bloc « 💰 Facturation ». Fiche
      client : plus de « CA Total », « Profil financier » ni « Historique
      des relances ».
- [ ] **MP** — classement des commerciaux : les montants restent, la carte
      « CA HT équipe » n'est plus cliquable.
- [ ] **Comptable** : plus de « Devis » ; `/admin/quotes` → page interdite.
      Tout le reste inchangé (factures, finance, CA du tableau de bord).
- [ ] **Commercial** et **admin** : rien ne change.

Tranché le 2026-11-02 (cf. 9 bis) : le comptable voit Réservations,
Inventaire, Régies et Carte, sans pouvoir modifier les prix des panneaux ;
le MP perd seulement « Messages », il garde Clients et Renouvellements.

---

## 10. Incident diffusion du 2026-11-02 — corrigé

Avec `DIFFUSION_MODE=production`, le bouton « Envoyer un test » visait la
liste **clients** (#3) : Panora y a recopié 830 clients et le catalogue
« [TEST] » du 15 au 30 novembre est parti à 300 d'entre eux (Brevo a
suspendu la campagne « test #8 », 15 % de rebonds). Corrigé : un test ne
vise que « Tests internes » (#4) et n'y écrit jamais ; si les deux listes
sont réglées sur le même numéro, le test est refusé.

- [ ] Campagne « test #8 » laissée suspendue (ne pas la reprendre)
- [x] Après déploiement : « Envoyer un test » → reçu par les 2 adresses de
      « Tests internes » seulement ; le mail de confirmation annonce 2
      destinataires
      *(2026-11-02 : la campagne Brevo #20 vise bien 2 destinataires, mais
      Brevo l'a suspendue, « 0 email restant » : le quota gratuit de 300/jour
      a été épuisé par « test #8 ». Panora l'affiche pourtant « envoyé », car
      il ne voit pas cette suspension (corrigé, cf. 10 quater). Refaire le
      test le 3/11 pour vérifier la réception.)*
- [x] Avant le premier vrai envoi : nettoyer les adresses en rebond (15 %
      sur les 300 premiers — Brevo exige < 2 %) *(fait le 2/11, cf. 10 bis et 10 ter : 465 destinataires)*

### 10 bis. Nettoyage des adresses — commande livrée le 2026-11-02

Sur le serveur de production (terminal Coolify) :

```
php artisan dispos:nettoyer-adresses              # rapport seul, rien n'est écrit
php artisan dispos:nettoyer-adresses --appliquer  # enregistre les exclusions
```

Écarte (motif « Adresse invalide », réversible depuis Diffusion dispos) :
les adresses bloquées par Brevo dans la liste #3 (rebonds de « test #8 »)
et celles d'un domaine sans serveur de mail (17 domaines / 32 adresses
repérés dans le Sheet, ex. cocan2023.ci).

- [x] Rapport lu, puis `--appliquer` lancé. Destinataires restants : **759** *(fait le 2026-11-02 : 46 bloquées par Brevo + 31 sur domaines morts)*
- [x] Contacts anciens : **tranché le 2026-11-02 — avant 2025 écartés** (cf. 10 ter)

## 9 bis. Droits — 2ᵉ série *(2026-11-02)*

- [ ] **MP** : plus de « Messages » dans le menu ; `/admin/messages` tapé
      à la main → page interdite. Clients : inchangé, il garde tout
      (décision revue le 2/11). Renouvellements : inchangé, il confirme.
- [ ] **Comptable** : les prix des panneaux (inventaire, campagne,
      réservation) ne sont plus modifiables ; Tarifs communes inchangé.

### 10 ter. Adresses trop anciennes écartées *(décision du 2026-11-02)*

Règle : une adresse dont le **dernier mail échangé** est antérieur au
**01/01/2025** ne reçoit plus le catalogue (date inconnue = reçoit). Si tous
les interlocuteurs clients d'une entreprise sont anciens, elle ne reçoit
rien. Réglage : `DIFFUSION_DERNIER_ECHANGE_MIN` (défaut 2025-01-01).
La date vient du Google Sheets : tant qu'il n'a pas été renvoyé, personne
n'est écarté.

- [x] Après déploiement : coller `docs/outils/panora-contacts.gs` dans le
      script du Sheets (Extensions → Apps Script, fichier `panora.gs`) *(fait le 2026-11-02)*
- [x] Menu 📇 Panora → **Envoyer à Panora** *(fait le 2026-11-02 : 2 536 à jour,
      159 refusés ; 2 536 interlocuteurs datés dans Panora)*
- [x] Diffusion dispos : le nombre de destinataires baisse (≈ 500 attendus,
      759 avant) *(constaté le 2026-11-02 : **465**)*

### 10 quater. Envois suspendus par Brevo repérés *(règles validées le 2026-11-02)*

Brevo accepte l'envoi puis peut le suspendre : quota épuisé, ou trop
d'adresses invalides. Désormais, Panora :
- vérifie avant l'envoi les envois restants chez Brevo ; s'il en manque,
  rien ne part ;
- relit toutes les 15 minutes les envois des dernières 24 h ;
- passe une campagne suspendue en « Suspendu par Brevo » (onglet
  « Échecs »), avec la cause probable, et prévient l'équipe.

Sur le serveur de production (terminal Coolify), après la mise en ligne :

- [x] `php artisan dispos:verifier-envois --heures=48` → les deux tests du
      2/11 (« test #8 » et celui de 16h10) passent « Suspendu par Brevo »
      dans le journal, avec leur cause. Une alerte arrive à la personne qui
      les a lancés. *(constaté le 2026-11-03 : les deux lignes affichent la
      bonne cause et le lien Brevo)*
- [x] `php artisan tinker --execute="echo app(App\Services\Brevo\BrevoClient::class)->envoisRestants();"`
      → doit donner le même nombre que « email restant » dans Brevo. Si
      c'est faux : me le signaler et, en attendant, poser
      `DIFFUSION_CONTROLE_QUOTA=false` dans Coolify.
      *(3/11 après le test #11 : **298**, soit les 300 envois gratuits du
      jour moins les 2 du test : le chiffre est cohérent)*
- [x] Le 3/11, « Envoyer un test » : un quart d'heure plus tard, la ligne du
      journal est toujours « Envoyé » (Brevo l'a bien envoyée).
      *(3/11 15h05 : « Échec — cURL error 28 », Brevo n'a pas répondu en
      30 s à la création de la campagne, mais l'a créée deux fois :
      brouillons #21 et #22, rien n'est parti. Corrigé, cf. 10 quinquies.
      Refait à 16h42 après la mise en ligne : « Envoyé », 2 destinataires,
      260 panneaux (99 Abidjan · 161 intérieur), campagne Brevo #23
      « test #11 » « Envoyée », non suspendue.)*

### 10 quinquies. Brevo qui ne répond pas à temps *(livré le 2026-11-03)*

Les appels qui envoient attendent désormais 120 s. Une création sans
réponse donne un échec clair (« rien n'est parti », brouillon à
supprimer). Un ordre d'envoi sans réponse donne « À vérifier dans
Brevo » : les boutons sont bloqués et Panora tranche dans les 15 min.
Relances J-7 et renouvellement : un mail sans réponse compte comme
envoyé et n'est jamais renvoyé (règle du 3/11).

- [x] Dans Brevo : supprimer les brouillons **#21 et #22** (« test #10 ») *(fait le 3/11)*
- [x] Après la mise en ligne : « Envoyer un test » → « Envoyé », 2
      destinataires *(constaté le 3/11 16h42 : Panora « Envoyé », Brevo #23
      « Envoyée » à 2 destinataires)*
- [x] Reste de ce test : mail ✅ reçu par l'équipe ; les 2 adresses de test
      reçoivent le catalogue (nom de l'interlocuteur, liens Abidjan et
      intérieur qui ouvrent les bons PDF). Ce point vaut aussi pour la case
      « reçu par les 2 adresses » du § 10. *(confirmé le 3/11 : catalogue reçu au nom de l'interlocuteur, les deux PDF s'ouvrent)*
- [ ] Si un jour une ligne « À vérifier dans Brevo » apparaît : ne pas
      relancer ; dans les 15 min elle doit passer « Envoyé », « Suspendu »
      ou « Échec », et un mail suit

### 10 sexies. Signature du catalogue et copie à Stéphane *(livré le 2026-11-03)*

Le test #11 n'avait que 2 numéros (modèle Brevo n° 14 collé avant le
30/10), pas de logo, et Stéphane ne le recevait pas. Désormais Panora
envoie au modèle les numéros (`CHARTE_TELEPHONES`) et le logo ; Stéphane
(`MAIL_COPIE_COMMERCIALE`) reçoit chaque vrai catalogue comme un client.
Le mail nomme l'entreprise du destinataire : « Voici, pour ORANGE CI, nos
emplacements… » (validé le 3/11).

Dans cet ordre, après la mise en ligne :

- [x] Brevo → Modèles → n° 14 → Modifier → coller **tout**
      `docs/diffusion/modele-brevo-disponibilites-leger.html` → Enregistrer,
      modèle **Actif** *(fait le 4/11 avant le test #12)*
- [x] Brevo → Contacts → liste « Tests internes » (#4) : ajouter
      `stephane.oponou@cible-ci.com` (les tests ne l'ajoutent pas seuls)
      *(test #12 du 4/11 : 3 contacts)*
- [x] Même liste : sur les 2 contacts de test, remplir le champ **SOCIETE**
      (ex. « CIBLE (test) ») pour voir l'entreprise dans le mail de test ;
      Stéphane le laisse vide *(test #12 : « Voici, pour ROMI CI, … »)*
- [ ] Diffusion dispos : la ligne « Copie du catalogue » affiche son adresse
- [x] « Envoyer un test » → 3 destinataires, logo, **3** numéros,
      entreprise nommée *(test #12 du 4/11 à 9h44)*

Retours sur le test #12, traités le 4/11 :
- logo retiré de la signature (le liseré aux 5 couleurs revient), gardé en haut ;
- colonne et texte centrés, comme les mails de Brevo (« trop à gauche ») ;
- Stéphane ajouté à la **confirmation** des vrais envois (par
  `MAIL_COPIE_COMMERCIALE`, pas par `DIFFUSION_CONFIRMATION_EMAIL`, qui sert
  aussi d'adresses de test aux mails clients).

- [x] Recoller le modèle n° 14 (nouvelle version centrée), puis « Envoyer un
      test » : mail centré, logo en haut seulement, liseré dans la signature,
      « Régie & Studio Créatif · Côte d'Ivoire · depuis 1994 » sous la signature
      *(confirmé le 6/11, avec le texte de la direction, § 10 octies : mail
      centré, logo en haut, « Régie & Studio Créatif »)*
- [ ] Diffusion dispos : « Confirmation envoyée à » affiche aussi
      stephane.oponou@cible-ci.com, suivi de « (en production ; un test : à
      son auteur) » tant que le mode test est actif
- [ ] Onglet Gmail « Promotions » ou « Notifications » : constaté au test
      #12, cf. réponse du 4/11 (pas de réglage qui garantit « Principale »)

### 10 septies. « Régie & Studio Créatif » partout chez les clients *(2026-11-04)*

« Régie publicitaire » devient **« Régie & Studio Créatif · Côte d'Ivoire ·
depuis 1994 »** (« Créatif » ajouté le 4/11 après-midi), source unique
`config/charte.php` (`CHARTE_ACTIVITE`) : pied de tous les mails Panora,
catalogue Brevo, PDF et pages que le client ouvre. Les en-têtes courts
(« CIBLE CI · Régie & Studio Créatif ») en lisent le début
(`charte.coordonnees.metier`). Le site vitrine suit aussi (develop
seulement). Restent « Régie Publicitaire » : les PDF internes (« Document
confidentiel ») et le type de contact « Régie publicitaire » (une régie
concurrente), voulu.

- [x] Coolify (prod) : **pas** de variable `CHARTE_ACTIVITE`, ou alors avec
      la nouvelle valeur — sinon l'ancienne ligne reste partout *(confirmé le 6/11)*
- [ ] Un devis ou une facture envoyés en test : pied « CIBLE · Régie &
      Studio Créatif · Côte d'Ivoire · depuis 1994 »
- [ ] Une proposition : la page client (en-tête et pied) et son PDF
- [x] Le PDF du catalogue des disponibilités : pied de page *(confirmé le 6/11)*
- [ ] Page « devis en ligne » et page satisfaction : pied de page
- [ ] Le modèle Brevo « relance campagne terminée », s'il sert encore :
      à recoller (texte corrigé en dur)
- [ ] Au premier vrai envoi : Brevo affiche un destinataire de plus que
      Panora (la copie) — c'est normal

### 10 octies. Texte et objet du catalogue — version de la direction *(2026-11-04)*

Le mail reprend le texte de la boss (« De nouveaux emplacements
publicitaires sont disponibles… », pastilles 📍 📅 🌍, liens 🟢 Abidjan /
🔵 intérieur, réponse « sous 1 heure ouvrée », signature « L'équipe
commerciale CIBLE — Votre visibilité, notre métier. »). Choix validés :
l'entreprise reste nommée (« notre sélection pour ORANGE CI : … ») ; la
phrase du PDF dit « son trafic estimé et sa description » (le PDF n'a pas
de rubrique « recommandations »). L'objet tourne entre ses trois formules,
une par catalogue (cf. `docs/DIFFUSION_DISPONIBILITES.md`, étape 7).

- [x] Coolify (prod **et** staging) : **pas** de variable `DIFFUSION_OBJET`
      — sinon elle remplace les trois objets *(confirmé le 6/11)*
- [x] Brevo → Modèles → n° 14 → coller **tout**
      `docs/diffusion/modele-brevo-disponibilites-leger.html` → Enregistrer,
      modèle **Actif** *(confirmé le 6/11)*
- [x] « Envoyer un test » (période du 15 au 30 novembre) : objet
      « [TEST] Anticipez vos campagnes : découvrez nos emplacements
      disponibles » *(confirmé le 6/11)*
- [x] Mail de test : « Bonjour [nom], », « …notre sélection pour ROMI CI :
      N emplacements… » *(confirmé le 6/11)*
- [ ] Le mail de Stéphane : « …notre sélection de N emplacements… »
- [x] Les deux liens 🟢 / 🔵 ouvrent chacun le bon PDF *(confirmé le 6/11)*
- [x] Signature : liseré, « L'équipe commerciale CIBLE », « Votre
      visibilité, notre métier. », Régie & Studio Créatif, 3 numéros, e-mail
      *(confirmé le 6/11 : Régie & Studio Créatif et les 3 numéros)*

---

## 11. Renvoyer les accès — personnel et clients *(ajouté le 2026-11-02)*

Nouveau bouton demandé par la direction : « un bouton simple pour
renvoyer les accès ». Décision prise le 2026-11-02 : **un nouveau mot de
passe temporaire**, un bouton par ligne, pas d'action groupée.

### 🔴 D'abord, la faille à reporter en prod

`origin/main` renvoyait la chaîne `password` comme mot de passe de
**tous** les espaces clients (depuis le 2026-04-24). Le correctif est sur
`develop` (`6bb2d891`). Tant qu'il n'est pas sur `main` :

```
# Sur le poste principal, pour mesurer l'exposition réelle :
php artisan tinker --execute="
  \$n = App\Models\Client::whereNotNull('password')->count();
  \$f = App\Models\Client::whereNotNull('password')
        ->get()->filter(fn(\$c) => \Illuminate\Support\Facades\Hash::check('password', \$c->password))->count();
  echo \"Comptes clients : {\$n} — dont encore ouvrables avec « password » : {\$f}\n\";
"
```

- □ Lancer la commande ci-dessus et **noter le chiffre ici** : ……
- □ Si > 0 : après la mise en ligne, renvoyer les accès à ces clients
  (bouton « Renvoyer les accès », un par un) — leur mot de passe actuel
  est connu de n'importe qui.

### Écrans à ouvrir

- □ **Personnel** (`/admin/users`) : le bouton 📧 apparaît sur chaque
  ligne, **sauf** la sienne, les comptes désactivés et ceux sans email.
- □ Cliquer 📧 : la confirmation doit dire « Son mot de passe actuel
  cessera de fonctionner ». Accepter → message vert, mail reçu, et
  l'ancien mot de passe ne connecte plus.
- □ Vérifier le mail reçu : objet « Votre mot de passe PANORA … a été
  réinitialisé », mot de passe de la forme `Bleu-Soleil-4821`.
- □ Tester sur un nom contenant une apostrophe (N'Guessan, M'Bahia) :
  la confirmation doit s'afficher normalement, pas planter.
- □ Une alerte « 📧 Accès renvoyés — <nom> » doit apparaître dans les
  alertes utilisateur.
- □ **Clients** (`/admin/clients`) : pastille « 🔐 Actif » → la modale
  propose « Renvoyer les accès » (et non plus « Réinitialiser le MDP »).
- □ Le client reçoit le mail, se connecte, et **doit** changer son mot de
  passe à l'arrivée (`must_change_password`).
- □ Créer un compte client neuf : le mot de passe envoyé doit être tiré
  (plus jamais `password`).

### À trancher

- □ **Le personnel n'est pas forcé de changer son mot de passe** : la
  table `users` n'a pas `must_change_password`. Le mot de passe envoyé
  par mail reste donc valable tant que la personne ne le change pas.
  Faut-il ajouter la contrainte (migration + écran + middleware) ?
  Inscrit dans `TECHNICAL_DEBT.md`.

---

## 12. Équipe créditée d'une pose — proposée, plus imposée *(2026-11-04)*

Constat : chaque pose attribuée à DAVID portait « 👥 Equipe Abengourou »,
même depuis la liste ou avec « Aucune (pose solo) ». Il est membre d'une
seule équipe : le serveur en déduisait l'équipe, la pose était créditée à
l'équipe et **sortait des chiffres personnels de David**.

Règle validée (option « b ») : les formulaires Nouvelle pose / Modifier
**proposent** l'équipe du technicien (ligne « 👥 Proposée : … »), un choix
manuel n'est jamais écrasé (« Aucune » compris), le serveur et
l'attribution depuis la liste ne mettent plus jamais d'équipe seuls.

- [ ] Poses → Équipes : DAVID est-il **vraiment** membre de l'Équipe
      Abengourou ? Sinon, le retirer de l'équipe
- [ ] Liste des poses : attribuer DAVID à une pose solo (action groupée
      « Technicien ») → **pas** de badge d'équipe
- [ ] Nouvelle pose : choisir DAVID → l'équipe est proposée avec la ligne
      d'aide ; remettre « Aucune » → enregistrer → pose solo
- [ ] Modifier une pose avec des piges validées : changer de technicien
      vers un membre d'une autre équipe → l'alerte « réattribuera ces
      piges » s'affiche
- [ ] Poses déjà créditées à tort (ex. ADJ-004A, Adjamé) : les cocher →
      « Équipe créditée » → « (retirer — pose solo) » → Appliquer.
      Elles reviennent dans les chiffres personnels du technicien

---

## 13. Page Piges : filtres et rapport PDF *(2026-11-04)*

Demande du MP : « toutes les piges de Moov, filtrées et exportées en PDF
prêt à envoyer », sur le modèle du Word « MOOV _ MILLIONS A SE PARTAGER
PHASE 2 ». Règles validées : piges **vérifiées** seulement ; tout est
coché, le MP décoche ; au plus 200 photos par PDF.

Corrigé sur la page :
- la recherche ne masquait que les 24 cartes affichées, et Entrée ne
  lançait rien → recherche sur toutes les piges (Entrée), y compris le
  nom du client et de la commune ;
- le filtre Campagne ne proposait que les campagnes en cours → les
  terminées aussi (marquées « · Terminée ») ;
- nouveaux filtres Client et Commune.

- [ ] Piges → Client = MOOV : seules ses piges ; la liste Campagne ne
      propose plus que les siennes, terminées comprises
- [ ] Recherche « moov » + Entrée : le compteur change (toutes les pages)
- [ ] Pagination : passer en page 2 garde les filtres
- [ ] « Exporter en PDF » : écran de préparation, tout est coché, titre
      proposé (la campagne, sinon « Piges [client] ») ; décocher une
      commune entière puis une photo → le compteur suit
- [ ] « Générer le PDF » : **identique au Word** « MOOV _ MILLIONS A SE
      PARTAGER PHASE 2 » (refait le 4/11, « pas identique » à la 1re
      version) — logo en haut à gauche, titre centré souligné, date, communes
      dans l'ordre de la tournée (heure des photos), « ❖ emplacement » puis
      la photo, environ deux panneaux par page, numéro en bas à droite
- [ ] Plus de 200 piges vérifiées dans le filtre → message « affinez »
- [ ] Temps de génération pour ~100 photos (photos réduites une à une) :
      noter la durée ; si c'est trop long, passer en arrière-plan
- [ ] Ouvrir le PDF sur téléphone et le joindre à un mail (poids)

---

## 14. Vagues d'une campagne annuelle *(2026-11-04)*

Un client garde ses panneaux toute l'année et change plusieurs fois de
message (« Phase 2 »…). La campagne reste le chapeau (contrat, facture,
nom) ; chaque changement de message est une **vague** nommée. Règles
validées : tout ou partie des panneaux ; facture unique ; nom obligatoire
(« Vague N » proposé) ; Vague 1 = nom de la campagne, créée au premier
changement ; retouche = vague de la pose réparée ; panneau ajouté en cours
d'année = vague en cours ; nom montré aux techniciens.

**Base (à faire sur le PC qui a la vraie base, avant/au déploiement)**
- [ ] `SHOW CREATE TABLE pose_tasks;` : pas déjà de colonne `vague_id`
- [ ] Migration `2026_11_04_100000_create_vagues_table` jouée (staging puis
      prod) : table `vagues`, colonne `pose_tasks.vague_id`. Additive et
      rejouable ; tant qu'elle n'est pas jouée, tout marche comme avant
      (champ Vague absent)

**Écrans**
- [ ] Fiche campagne → « Programmer un rechange » : champ **Vague**
      (« 🌊 Nouvelle vague » + nom proposé « Vague 2 ») ; masqué pour une
      retouche ; nom vide → refusé
- [ ] Après le rechange : bloc **🌊 Vagues (2)** sur la fiche campagne —
      V1 au nom de la campagne (toutes les anciennes poses), V2 « Phase 2 » ;
      renommer une vague (✓) ; « PDF des piges » d'une vague
- [ ] Le lendemain, rechange d'un autre panneau en choisissant
      « Continuer : Vague 2 · Phase 2 » → toujours 2 vagues
- [ ] Liste des poses : badge « 🌊 V2 · Phase 2 » sous la campagne ; même
      badge sur la fiche pose
- [ ] Rechange en lot depuis la liste des poses (plusieurs campagnes) :
      « Nouvelle vague » crée une vague par campagne, même nom
- [ ] Page Piges : campagne choisie → filtre **Vague** ; export PDF : titre
      proposé « MOOV 2026 — Phase 2 », vague en sous-titre
- [ ] Technicien : carte de pose (badge 🌊), lien de pose (« Visuel à
      poser »), feuille de route, fiche de pose PDF (cartes et liste)
- [ ] Une campagne sans changement de message : aucun bloc, aucun badge

---

## 15. Photo de pige : case « 📍 Joindre ma position » *(2026-11-04)*

Demande : cochée par défaut, décochable (tech qui envoie depuis chez lui
après un souci de réseau). Décochée : la photo part **sans** position ; la
pige est classée « Sans GPS » (`geo_check = no_gps`), comme un GPS muet.
Trois écrans : espace technicien (aperçu après la photo), lien de pose,
lien de campagne. Chaque photo repart cochée.

Corrigé au passage : dans l'espace technicien et le lien de campagne, une
photo envoyée **sans** position finissait en **erreur 500** après création
de la pige (le tech voyait un échec, la photo était pourtant reçue).

**Sur un vrai téléphone (Android + iPhone)**
- [ ] Espace tech → Photo : la case est cochée, le verdict GPS s'affiche
      comme avant ; décocher → bloc gris « Position non jointe » ; recocher
      → le verdict revient ; ⓘ montre une ligne d'aide
- [ ] Envoyer décoché → succès (pas d'erreur) ; côté admin, la pige
      affiche « Sans GPS » et aucune coordonnée
- [ ] Envoyer coché devant un panneau → « OK » côté admin (inchangé)
- [ ] Mode avion, photo décochée, puis réseau revenu : la photo rejouée
      arrive sans position
- [ ] Lien de pose (pose sans tech assigné) : case sous « Prendre une
      photo » ; décochée → pas de demande de localisation, envoi OK ;
      recochée après l'envoi ; idem « Remplacer » / « Refaire cette photo »
- [ ] Lien de campagne : case dans la fenêtre d'envoi, recochée à chaque
      ouverture

**Tranché le 2026-11-04**
- [x] **Galerie** : option a — l'attribut `capture` est retiré des 4 champs
      photo de pige (espace tech, lien de pose ×2, lien de campagne) ; le
      téléphone propose « Appareil photo ou Galerie ». La photo d'un
      signalement garde l'appareil photo direct.
- [x] Tracer « position retirée par le technicien » : **non** (même badge
      « Sans GPS » qu'un GPS en panne).

**À vérifier sur téléphone (galerie)**
- [ ] Android et iPhone : toucher « Photo » propose bien appareil photo OU
      galerie ; une photo de galerie s'envoie (iPhone : format HEIC accepté)
- [x] Date d'une photo de galerie : celle de l'ENVOI, pas de la prise de
      vue (le serveur ne lit pas l'EXIF) — **voulu**, option a du 4/11

---

## 16. Campagnes : filtre « Lancée du / au » et exports *(2026-11-05)*

Constat : « les campagnes lancées en septembre » ne sortaient pas — le
filtre Du / Au ne gardait que les campagnes débutées après le « Du » ET
finies avant le « Au ». Règles validées le 5/11 (source unique
`App\Services\Campagnes\CampagneFiltres`) :
- **Lancée du / au** : date de début dans la période, fin indifférente ;
  raccourci **Mois de lancement** qui remplit les deux dates ;
- lien de l'indicateur « CA TTC équipe » (Performance commerciale) :
  campagnes **actives** pendant la période, hors annulées — comme le chiffre ;
- bandeau « se terminent dans moins de 14 jours » : mêmes campagnes que
  son compteur ;
- exports Excel et PDF = **exactement** l'écran : tous les filtres, et un
  commercial n'exporte plus que SES campagnes (avant : tout le monde).

- [ ] Campagnes → Mois de lancement = septembre 2026 : les campagnes
      lancées en septembre sortent, même finies en décembre
- [ ] Changer à la main une des deux dates : le mois se vide
- [ ] Performance commerciale → clic « CA TTC équipe » : puce « Actives du
      … au … ✕ » ; le nombre de campagnes colle à l'indicateur (écart
      possible : campagnes sans commercial, que l'indicateur ne compte pas)
- [ ] Bandeau « se terminent dans moins de 14 jours » → Voir : puce
      « Finissent dans moins de 14 jours ✕ » ; changer un autre filtre ne
      la fait pas disparaître ; ✕ la retire
- [ ] Filtrer (ex. septembre + un commercial), puis Excel et PDF : mêmes
      campagnes qu'à l'écran
- [ ] Connecté en commercial : l'export ne contient que ses campagnes

### 16 bis. PDF « Liste des campagnes » et PDF des piges retravaillés *(2026-11-05)*

Liste des campagnes (bouton PDF de la liste), à envoyer tel quel à
l'administration : la sélection en clair en titre (« Lancées en septembre
2026 · Statut : En cours ») au lieu de « Filtres appliqués depuis
l'interface admin », chiffres clés (campagnes, dont en cours, panneaux,
montant), colonne **Commercial** (au lieu de « Créée par »), ligne de total,
nom de fichier parlant.

PDF des piges : chaque photo est réencodée — redressée si le téléphone l'a
marquée « tenu debout », proportions lues sur l'image réelle (avant : 4:3
supposé quand la taille était illisible → photos étirées), PNG / WebP
convertis ; hauteur 75 mm pour garder deux panneaux par page ; titre 16 pt ;
ligne **« Dimensions : 4×3m · 12 m² »** sous chaque emplacement.

- [ ] Liste des campagnes → PDF avec « Mois de lancement = septembre » :
      titre, chiffres clés, total et nom du fichier
- [ ] PDF des piges sur une vraie campagne : aucune photo couchée, étirée
      ou coupée ; dimensions affichées (format renseigné sur le panneau)
- [ ] Un panneau sans format renseigné : pas de ligne « Dimensions »

**v3 du 2026-11-05** (« ces cases font trop IA », « le doc est trop simple ») :
mise en page éditoriale commune aux deux PDF — en-tête logo + coordonnées
alignés, filet, titre et une ligne de synthèse (plus de cartes de chiffres),
date d’édition et page au pied. Liste : tableau à filets, statut en texte
avec pastille. Piges : une fiche par panneau — photo dans un cadre fixe
104 × 76 mm (centrée), numéro, emplacement, référence, dimensions, date ;
trois fiches par page.

- [ ] Les deux PDF sur la vraie base : en-tête aligné, pied complet

## 17. Harmonisation de tous les PDF *(2026-11-05)*

Décisions du 5/11 : **1-a** — facture et devis inchangés (le devis garde
son ancien pied, figé dans `pdf.partials.charte-footer-devis`) ;
**2-non** — proposition commerciale, liste des disponibilités et catalogue
gardent leur corps, **seul le pied change** ; **3-ok** — quatre PDF vérifiés
sur données fictives (liste des campagnes, piges, performance commerciale,
fiche de pose), le reste ici.

Ce qui change partout :
- **pied commun** : à gauche CIBLE · activité, puis téléphones · e-mail ·
  site ; à droite la mention (« Document confidentiel », « Document
  interne », « Proposition RES-… ») · édité le jj/mm/aaaa à hh:mm, puis
  « Page N ». Disparus : « Régie OOH », « Régie Publicitaire », « Édité par
  Panora le… », « Document généré automatiquement » recopiés à la main ;
- **documents internes** : en-tête éditorial (logo et coordonnées alignés,
  filet, type de document, titre, sous-titre), tableaux à filets sans fond
  noir ni zébrures, statuts en texte avec pastille de couleur, chiffres clés
  en bande au lieu de cases ; plus de « Réf. » tirée au hasard dans l'en-tête ;
  un intertitre ne reste plus seul en bas de page ;
- **performance (commerciaux, équipes, techniciens) et signalements** : la
  période passe en sous-titre (« Du 01/09/2026 au 30/11/2026 · 91 jours ») —
  avant, la durée s'affichait parfois « 91.999999999988 jours ».

À ouvrir sur la vraie base (en-tête aligné, aucun texte coupé ou
superposé, pied complet sur chaque page, numéros de page) :

- [ ] Campagnes → fiche de pose (une campagne) et fiche de pose en liste
- [ ] Clients → export PDF de la liste
- [ ] Finance → récapitulatif PDF et relances PDF
- [ ] Panneaux → fiche PDF d'un panneau, et rapport réseau PDF
- [ ] Factures → export PDF de la liste
- [ ] Rapports → synthèse, occupation des panneaux (couverture conservée),
      détail d'occupation, classification des panneaux, zones et communes,
      feuille de décapage
- [ ] Performance → commerciaux, équipes, techniciens : période en
      sous-titre, durée en jours entiers
- [ ] Signalements (SLA) → PDF
- [ ] Taxes → détail et rapport des taxes communales
- [ ] Documents clients, **pied seulement** : proposition commerciale,
      liste des disponibilités, catalogue des disponibilités (y compris
      celui envoyé par Brevo) — le corps est identique à avant
      *(catalogue : nouveau pied vu au test du 6/11 ; restent la proposition
      et la liste)*
- [ ] Facture et devis : **aucun changement** par rapport à hier
- [ ] Un PDF de plusieurs pages (ex. liste des clients) : le pied se répète,
      « Page 2 », « Page 3 »…
