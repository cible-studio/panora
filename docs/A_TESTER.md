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
- [ ] Le mail de Stéphane : « …notre sélection de panneaux : N emplacements… »
- [x] Les deux liens 🟢 / 🔵 ouvrent chacun le bon PDF *(confirmé le 6/11)*
- [x] Signature : liseré, « L'équipe commerciale CIBLE », « Votre
      visibilité, notre métier. », Régie & Studio Créatif, 3 numéros, e-mail
      *(confirmé le 6/11 : Régie & Studio Créatif et les 3 numéros)*

Phrase d'accroche revue le 6/11 : « Anticipez votre visibilité en découvrant
notre sélection de panneaux pour ROMI CI : N emplacements disponibles… »
(sans entreprise : « …notre sélection de panneaux : N emplacements… »).

- [x] Brevo → Modèles → n° 14 → coller **tout**
      `docs/diffusion/modele-brevo-disponibilites-leger.html` → Enregistrer,
      modèle **Actif**, puis « Envoyer un test » : la nouvelle phrase s'affiche
      *(confirmé le 6/11)*

Texte revu le 6/11 après-midi : « Découvrez pour chaque support une fiche
détaillée… », « Répondez… souhaitées. » puis « Notre équipe… » à la ligne,
« Anticipez vos réservations et sécurisez… ». Mise en page : le **titre** et les
paragraphes **« Anticipez… »**, **« Découvrez… »** et **« Répondez… »** sont
alignés à gauche, tout le reste est centré (demandes du 6/11, captures à
l'appui). Logo agrandi à 220 px
de large, proportions conservées (120 px avant).

- [ ] Brevo → Modèles → n° 14 → coller **tout**
      `docs/diffusion/modele-brevo-disponibilites-leger.html` en remplaçant le
      code existant → Enregistrer, modèle **Actif**
- [ ] « Envoyer un test » : ouvrir le mail reçu sur ordinateur et sur
      téléphone. Logo plus grand et non déformé, titre et paragraphes
      « Anticipez… », « Découvrez… », « Répondez… » à gauche, le reste centré,
      nouveau texte, aucun code `{% if %}` visible

### 10 nonies. Premier vrai envoi du catalogue *(Brevo payé, signalé le 2026-11-06)*

Contrôlé le 6/11 sans toucher à la prod : le domaine est authentifié pour Brevo
(code Brevo, DKIM et DMARC dans le DNS public) ; la lecture de la liste est
paginée et le retrait des anciennes adresses se fait par lots de 150 (à lire
dans le code, ce retrait n'a encore jamais tourné en prod). Non contrôlable
d'ici : tout ce qui suit.

**Parti le vendredi 6/11 à 17h51** (choix de l'utilisateur, le lundi 9/11 était
conseillé) : campagne Brevo n° 33, 467 clients + Stéphane = 468 destinataires,
255 panneaux (95 Abidjan, 160 intérieur).

- [ ] Coolify : dernier déploiement de main en « Success »
- [x] Coolify : pas de `DIFFUSION_OBJET` en prod *(objet lu sur la prod le 6/11 :
      « Anticipez vos campagnes… », sans [TEST])*
- [ ] Coolify : `DIFFUSION_OBJET` retirée du staging, qui reste en
      `DIFFUSION_MODE=test` et `DIFFUSION_AUTO=false`
- [x] Brevo → Paramètres → Webhooks (Marketing) : URL de la **prod**, événements
      Désinscrit, Hard bounce, Spam (`docs/DIFFUSION_DISPONIBILITES.md`, étape 9)
      *(6/11 : l'ancien webhook de la prod était sur le canal **SMS**, donc aucun
      événement de mail n'arrivait à Panora. Remplacé par un webhook canal e-mail,
      n° 2662418, avant l'envoi)*
- [ ] Désinscriptions manquées avant ce webhook : `php artisan dispos:nettoyer-adresses`
      (simple rapport), `--appliquer` seulement après lecture
- [ ] Brevo : modèle n° 14 à la dernière version (logo 220 px), test reçu et validé
- [x] `envoisRestants` en prod : environ 10 000 au lieu de 300 *(9 982 le 6/11)*
- [x] Prod seulement : `DIFFUSION_MODE=production`, `DIFFUSION_AUTO` encore à
      `false`, redéployer *(6/11)*
- [x] Diffusion dispos : environ **465** destinataires ; sinon, arrêter et regarder
      *(467 le 6/11)*
- [x] « Envoyer maintenant… » : période du 15 au 30 novembre, même nombre
      dans la fenêtre de confirmation *(6/11 à 17h47)*
- [x] Journal « Envoyé » : 467 ; Brevo : un destinataire de plus (Stéphane), 468
      *(constaté le 6/11 ; mail ✅ pas encore confirmé)*
- [x] Rapport Brevo relu le 9/11 : campagne « Envoyée » (18h03), non suspendue,
      404 délivrés sur 468. **Rebonds définitifs : 30, soit 6,4 %, au-dessus des
      2 %.** Panora a exclu 30 adresses depuis l'envoi : le webhook e-mail fonctionne
- [x] Onglet Délivrabilité : les 34 autres sont des rebonds temporaires (18 cause inconnue, 12 connexion au fournisseur, 4 boîte pleine ou inactive). Hors robots : 206 ouvertures, 24 clics. Au 9/11 : aucune réponse, aucun devis
- [ ] Envoi du 20/11 : regarder si les mêmes adresses rebondissent encore
- [ ] Jeudi 12/11 : refaire le point réponses et devis
- [ ] Vérifier l'offre Brevo payée : l'onglet Délivrabilité propose encore « Passer à Standard »
- [ ] Diffusion dispos : environ **437** destinataires au lieu de 467
- [ ] Équipe commerciale : corriger les 30 adresses (Diffusion dispos, Adresses exclues)
- [ ] Envoi du 20/11 : rebonds définitifs sous 2 %
- [ ] Le mail de Stéphane : « …notre sélection de panneaux : N emplacements… »
- [x] `DIFFUSION_AUTO=true` avant le **vendredi 20/11 à 10 h** *(posé le 11/11)*
- [x] Écran Diffusion dispos : le prochain envoi automatique affiché est bien le 20/11 *(vu le 11/11)*
- [ ] Ensuite, côté code : retirer `LIEN_PDF` (`docs/TECHNICAL_DEBT.md`)

### 10 decies. Sauvegardes et PDF de test *(2026-11-09)*

La copie vers Hetzner a échoué les nuits du 7 au 9/11. Rattrapée à la main le
9/11 (545 Mo), puis 2 morceaux envoyés à la fois au lieu de 3. Les PDF de test
pesaient 342 Mo sur ces 545 Mo : ils sont supprimés après 7 jours, à 1h15.

- [ ] Pas de mail « Échec de la sauvegarde » les nuits suivantes. *(10/11 : nouvel échec à 2h,
      `503 Slow Down` sur une simple lecture : sauvegarde déplacée à 4h40, 3 essais à 30 min
      d'écart.)* Si ça échoue encore : choisir une autre heure avec `BACKUP_HEURE`
- [ ] Après déploiement : `php artisan dispos:purger-pdf-tests --simuler` annonce
      les tests d'avant le 2/11 ; le test #8 suspendu n'en fait pas partie
- [ ] Lendemain : `php artisan backup:list`, archive plus légère. Après le
      13/11 (tests du 6/11 supprimés) : autour de 200 Mo
- [ ] Journal Diffusion dispos : un vieux test n'a plus de lien PDF, le vrai
      envoi du 6/11 garde les trois

### 10 undecies. Interlocuteurs manquants et réponses automatiques *(2026-11-09)*

Sans interlocuteur, les mails de suivi partent à tous les contacts cochés du
client. Avant de lancer la reconduction, chaque commercial renseigne le sien.

- [ ] Après déploiement : `php artisan campagnes:sans-interlocuteur` affiche le décompte
- [ ] `php artisan campagnes:sans-interlocuteur --mail=<adresse>` : un Excel par commercial
- [ ] Écran **Campagnes → 👤 Interlocuteurs** (`/admin/campaigns/interlocuteurs`, refait le 9/11) : un commercial
      ne voit que ses campagnes ; l'interlocuteur actuel s'affiche avec Modifier ; choisir dans la liste
      n'enregistre rien tant qu'on n'a pas cliqué sur **Appliquer** ; « + Nouveau » → Créer et appliquer ;
      « Appliquer aussi à ses autres campagnes » ; filtres À renseigner / Déjà renseignées.
      *Parcours vérifié dans un navigateur le 9/11, avec des réponses simulées : à refaire sur la vraie base*
- [ ] Le fichier Excel reste disponible, mais l'écran le remplace pour la saisie
- [ ] Simulation de la reconduction : chaque ligne `[→]` vise la bonne personne
      *(11/11 : `RELANCE_FIN_MODE=production` posé ; simulation : 9 campagnes partiront le 12/11,
      seule #20 EUROLAIT - YOPLAIT reste sans adresse)*
- [ ] 12/11 après 10 h : lignes J-60 en vert sur Renouvellements ; Brevo → Transactionnel → Journal, tag `renouvellement-j60`
- [ ] #20 EUROLAIT - YOPLAIT : interlocuteur avant le 15/11 à 10 h (sinon le J-45 du 16/11 part directement)
- [ ] Fiche client (10/11) : l'interlocuteur d'une campagne en cours porte le badge vert « Interlocuteur de campagne »
      (au survol, ses campagnes) à la place de « sans mails de campagne »

Réponses automatiques au catalogue (« X ne fait plus partie de l'entreprise ») :
le nouveau contact entre par le Sheet (statut Client), l'ancien est supprimé
dans Panora et sa ligne retirée du Sheet. Il ne revient pas : Panora refuse une
adresse supprimée (« Supprimé dans Panora : ne revient pas »). Exception : si
l'adresse est celle de la **fiche client**, c'est la fiche qu'il faut corriger.

- [ ] Les 11 réponses du 6/11 traitées

---

## 11. Renvoyer les accès — personnel et clients *(ajouté le 2026-11-02)*

- [x] Inscription libre `/register` fermée le 11/11 (404 vérifié en prod)
- [ ] Liste des comptes du personnel : aucun compte inconnu créé par
      `/register` avant sa fermeture (commande tinker « created_at | rôle |
      actif | nom | email »). En cas d'inconnu : le désactiver
- [ ] Rôle du compte « DIRECTION CIBLE » : `admin` pour tout voir
- [ ] PDF « Rôles et droits » validé par la DG ; écarts à corriger listés
      dans `docs/TECHNICAL_DEBT.md` (audit du 11/11)

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

---

## 18. Axes multi-pieds — décisions en attente *(ajouté le 2026-11-11)*

Chantier documenté dans **`docs/AXES_MULTI_PIEDS.md`** : le plan y est
détaillé et **attend validation**. Aucun code écrit à ce jour.

L'inventaire réel a été relevé en production le 11/11 (script de lecture
seule) : **13 axes, 101 pieds, 291 faces**, dans un parc de 348 panneaux
actifs. `CDYLUP-004` (St Jean – Carrefour Lavie) compte bien **11 pieds**.

### Tranché le 11/11

- **130 000 FCFA/mois = le prix d'UN pied**, ses 3 faces comprises.
  L'unité de réservation est le pied.
- L'axe entier est aujourd'hui vendu au prix d'un seul pied, et **aucun
  commercial n'a corrigé ce prix** (0 sur 14 lignes).

### En attente de décision — à me revenir

- [ ] **ODP** : un axe de 11 pieds, c'est **11 ODP ou 1** ? L'ODP est gérée
      autrement aujourd'hui ; décision reportée, à confirmer.
- [ ] **TM** : par pied, ou par face (3 faces par pied) ?
- [ ] ⚠ **Point dur à connaître avant de trancher** : `InvoiceCalculator`
      applique `quantite` **aux trois calculs** — loyer, ODP et TM. Facturer
      au pied multiplie donc l'ODP et la TM par le même nombre,
      automatiquement. « Facturer au pied » et « ne pas toucher l'ODP » ne
      peuvent pas être vrais en même temps. C'est pourquoi le plan sépare
      une phase 1 fiscalement neutre d'une phase 2 sur le prix.

### Deux anomalies de tarif à élucider

- [ ] **`CDYPUB-09`** : 100 000 au lieu de 130 000, et référence `CDYPUB`
      hors convention. Erreur de saisie ou tarif négocié ?
- [ ] **`CDYLUP-10`** : réservé deux fois à PU = 0. Volontaire ?

### Décidé de ne PAS faire

- **`FAC-2026-019`** (brouillon, `CDYLUP-002`, 130 000 pour un axe de
  6 pieds) : laissée telle quelle le 11/11 — les brouillons attendent.

### Contrainte de calendrier

Les 6 axes `libre` totalisent 40 pieds. Sans regroupement d'affichage,
`NB_PANNEAUX` passerait de **255** (chiffre réellement parti le 6/11) à
environ 290, et le PDF gagnerait ~30 pages pour 15 photos disponibles.
**Le découpage ne part pas en production avant l'envoi automatique du
20/11.**

### 18 bis. Axes multi-pieds — phase 1 livrée *(2026-11-11)*

Validée, livrée sur `develop`, puis **reportée sur `main` le 11/11**
(cherry-pick `490355e9` → `acc4a8de`). Détail complet :
`docs/AXES_MULTI_PIEDS.md § 8`. **28 tests** passent ; la suite complète est à
765 passants, 23 échecs — les mêmes tests d'auth SQLite qu'avant, chiffres
identiques sur les deux branches.

#### ⛔ Le code est en production, le découpage NON

Le déploiement de `main` ne change **rien** de visible : `axe_id` est nul
partout et `est_axe` vaut `false`, donc tous les panneaux restent vendables.
Même la migration jouée automatiquement est neutre. Ce qui change le parc,
c'est la commande ci-dessous, et elle est manuelle.

**Correction du 11/11 (soir)** — une version antérieure de cette note
annonçait que « les 101 pieds prennent la place des 13 axes au
catalogue ». **C'est faux**, et vérifié dans le code :
`DiffusionDisponibilitesService::panneauxDiffusables()` termine par
`regrouperLesPieds()`, qui recolle les pieds en **une entrée par axe**,
avec le nom et la photo de la ligne d'axe. Le nombre d'entrées du
catalogue ne change donc pas, et `NB_PANNEAUX` non plus.

Ce qui change réellement dans le catalogue client, juste après le
découpage :

- l'entrée d'un axe gagne la ligne **« Pieds proposés : N sur N »** ;
- la couverture gagne l'encadré **« Comment lire cette disponibilité »** ;
- la **disponibilité est identique** : les pieds héritent du blocage de
  leur axe, donc aucun axe ne devient partiellement libre du seul fait du
  découpage.

Le risque restant n'est donc pas une rupture de contenu mais un
**changement d'apparence d'un document client que personne n'a encore
relu**. D'où la consigne, moins dure qu'annoncée : regarder le PDF via un
**envoi de test** avant de laisser partir celui du 20/11.

#### Déploiement, dans cet ordre

```bash
php artisan migrate
php artisan axes:decouper              # SIMULATION, n'écrit rien
php artisan axes:decouper --appliquer  # crée les 101 pieds
```

Rejouable. Recopie les tarifs tels quels. `--axe=CDYLUP-004` pour n'en
traiter qu'un et regarder le résultat avant d'y aller en entier.

**Simulation passée en production le 11/11** — 13 axes, **101 pieds**, et
elle confirme le document métier contre le vieux seeder : CDYLUP-004
Carrefour Lavie annonce bien **11 pieds** (le seeder disait 14, il a
tort). Toutes les structures se lisent : 3 faces par pied partout, sauf
BK-002 Carrefour Adiaké Assinie qui en a 2 (« 12Panneaux double face »).

Deux tarifs sortent du rang, et les deux portent une référence à 2
chiffres au lieu de 3 — à faire trancher :

- **CDYPUB-09** (2 Plateaux – Carrefour Laspalmas) : **100 000** alors que
  tous les autres axes Lumipub sont à 130 000. Référence en `CDYPUB` et
  non `CDYLUP`.
- **CDYLUP-10** (Opéra – Pharmacie St Gabriel) : 130 000, mais déjà
  réservé deux fois à PU = 0.

`BK-002` à 100 000 est probablement normal : c'est un Classique double
face, pas un Lumipub — produit différent.

#### 🔴 Le contrôle qui compte — la neutralité fiscale

La promesse de la phase 1 est que **la facture ne change pas d'un franc**.
À vérifier sur la vraie base, AVANT et APRÈS le découpage :

```
php artisan tinker --execute="
  foreach (App\Models\Campaign::whereNull('deleted_at')
      ->where('end_date','>=',date('Y-m-d'))->get() as \$c) {
    \$l = App\Models\InvoiceLine::whereHas('invoice', fn(\$q)=>\$q->where('campaign_id',\$c->id))->get();
    echo \$c->id,' | ',\$c->name,' | lignes=',\$l->count(),
         ' | HT=',\$l->sum('montant_ht_ligne'),
         ' | ODP=',\$l->sum('odp_ligne'),' | TM=',\$l->sum('tm_ligne'),\"\n\";
  }"
```

- [x] **Chiffres AVANT le découpage — relevés en production le 11/11 :**

| campagne | lignes | HT | ODP | TM |
|---|---|---|---|---|
| 4 AKWABAO FIGURINE | 2 | 20 908 800 | 1 728 000 | 1 728 000 |
| 6 ADDOHA – CITÉ SONGON – 22M | 1 | 13 000 000 | 2 700 000 | 540 000 |
| 9 ITB | 1 | 1 200 000 | 144 000 | 144 000 |
| 27 BNI 2026 | 2 | 17 520 000 | 864 000 | 864 000 |
| 76 LOKO 2026 | 1 | 639 600 | 72 000 | 72 000 |
| 95 DAIKIN | 1 | **0** | 6 300 000 | 360 000 |
| 102 LADILI CONSULTING – 2026 | 5 | 7 750 000 | 680 000 | 68 000 |
| **TOTAL** | **13** | **61 018 400** | **12 488 000** | **3 776 000** |

Les 38 autres campagnes vivantes n'ont aucune ligne de facture. **Ce sont
ces quatre totaux qu'il faut retrouver à l'identique après le découpage.**

🔶 **À faire trancher — campagne 95 DAIKIN** : une ligne à **HT = 0** qui
porte pourtant **6 300 000 d'ODP** et 360 000 de TM. À elle seule, la
moitié de l'ODP de tout le carnet vivant, sur une ligne sans montant de
base. Sans rapport avec les axes, antérieur, non corrigé — mais à
regarder, parce que ce chiffre entre dans la comparaison avant/après.
- [ ] Les comparer APRÈS : **ils doivent être identiques**.

**⚠ Correction du 11/11 (soir) — ce contrôle est plus faible qu'annoncé.**
Les lignes de facture sont des valeurs **stockées** ; `axes:decouper`
n'écrit que dans `panels`. Les totaux ci-dessus ne *peuvent* donc pas
bouger. Le relevé garde sa valeur de filet (il attraperait un observer ou
un recalcul inattendu) mais il **ne prouve pas** la neutralité fiscale.

**Le vrai test, c'est de REGÉNÉRER une facture** depuis une campagne qui
porte un axe, après le découpage, et de comparer ce qu'elle propose.

Sonde du 11/11 — une seule ligne de facture de tout le parc porte un axe :

| ligne | facture | statut | qté | PU | mois | HT | ODP | TM |
|---|---|---|---|---|---|---|---|---|
| 102 | `FAC-2026-019` | **brouillon** | 1 | 130 000 | 1,00 | 130 000 | 15 000 | 3 000 |

C'est le brouillon laissé de côté sur décision du 11/11. **Aucune facture
émise ne porte d'axe** : l'exposition financière du découpage est nulle.

- [ ] Après le découpage, sur la campagne **102 LADILI** (planifiée
      décembre 2026, 1 axe) : relancer la génération de facture et
      vérifier qu'elle propose toujours **PU 130 000, qté 1, HT 130 000,
      ODP 15 000, TM 3 000**. Si la qté passe à 11, c'est que
      `FACTURER_AU_PIED` a été basculé par erreur.

#### Campagnes et réservations qui portent un axe (sonde du 11/11)

| campagne | statut | période | axes |
|---|---|---|---|
| 2 BEM | actif | 2026-01-01 → 2026-12-31 | **4** |
| 8 MOOV 2026 | actif | 2026-01-01 → 2026-12-31 | 1 |
| 102 LADILI CONSULTING | planifié | 2026-12-01 → 2026-12-31 | 1 |
| 19 VOODOO – MANSA BANK | terminé | 2026-05-18 → 2026-06-18 | 3 |
| 50 KOTON (Lup) | terminé | 2026-04-01 → 2026-06-30 | 2 |
| 72 KOTON – SOLDES | terminé | 2026-08-01 → 2026-10-31 | 2 |

Soit **6 axes engagés** jusqu'au 31/12 (BEM 4, MOOV 1, LADILI 1) et
**7 axes libres**, qui deviennent vendables au pied dès le découpage. Les
pieds des 6 engagés héritent du blocage de leur axe (règle 2) : ils ne
doivent PAS apparaître comme libres avant 2027.

**Limite assumée de la phase 1** : les campagnes existantes continuent de
pointer sur la *ligne d'axe*, pas sur ses pieds. BEM, MOOV et LADILI
gardent donc l'ancien fonctionnement — une seule pose, une seule pige pour
tout l'axe — jusqu'à ce que quelqu'un refasse la sélection. Le découpage ne
migre aucun contrat en cours, et c'est voulu : y toucher déplacerait des
poses et des piges déjà réalisées.

#### ✅ DÉCOUPAGE APPLIQUÉ EN PRODUCTION — 2026-11-11

`php artisan axes:decouper --appliquer` : **13 axes, 101 pieds créés**,
aucune erreur. Les 13 axes portent `est_axe`, et **0 axe n'est encore
proposé à la vente**.

Vérification de la règle 2 (un pied hérite du blocage de son axe), sur la
période 2026-11-11 → 2026-12-31, via `AvailabilityService` :

| axe | pieds bloqués | verdict |
|---|---|---|
| CDYLUP-001 | 10 / 10 | occupé en entier |
| CDYLUP-002 | 6 / 6 | occupé en entier |
| CDYLUP-007 | 9 / 9 | occupé en entier |
| CDYLUP-008 | 9 / 9 | occupé en entier |
| TVILUP-001 | 10 / 10 | occupé en entier |
| TVILUP-002 | 5 / 5 | occupé en entier |
| CDYLUP-003 · -004 · -005 · -006 · CDYPUB-09 · BK-002 · CDYLUP-10 | 0 | libres |

**49 pieds bloqués sur 101.** Six axes occupés — exactement les six
engagés par BEM (4), MOOV (1) et LADILI (1) — et 10+6+9+9+10+5 = 49, le
compte est juste. Aucun axe vendu n'apparaît libre : **le risque de
revente d'un axe déjà vendu est écarté, prouvé sur les vraies données.**

Aucun axe en état `PARTIEL`, ce qui est le résultat attendu juste après le
découpage : l'héritage est tout-ou-rien par axe. Les états partiels
(« 7 pieds sur 11 ») n'apparaîtront qu'avec les ventes au pied à venir.

Gain métier effectif : **7 axes libres = 52 pieds** vendables à l'unité,
là où il fallait prendre l'axe entier.

#### Écrans à ouvrir après le découpage

- [ ] **Inventaire** : les 13 axes portent le badge orange « AXE · N pieds »,
      les pieds le badge gris « pied N ». Les axes restent ouvrables.
- [ ] **Réservation → sélection des panneaux** : les axes n'apparaissent
      plus, leurs pieds oui, cochables un par un.
- [ ] **Un pied d'un axe déjà vendu** (`CDYLUP-001`, `-007`, `-008`,
      `TVILUP-001`, `-002` sont occupés) : il doit sortir **indisponible**,
      avec le motif « … — axe vendu en entier ». Si un seul ressort libre,
      on revend un axe déjà vendu : arrêter tout.
- [ ] **PDF des disponibilités** : un axe = UNE page, ligne « Pieds proposés :
      7 sur 11 », et l'encadré « Comment lire cette disponibilité » sur la
      première page de garde. La photo doit être là (elle appartient à la
      ligne de l'axe).
- [ ] **Catalogue Brevo** (aperçu, pas d'envoi) : même regroupement.
      `NB_PANNEAUX` doit rester proche de 255 — un axe compte pour 1 entrée,
      comme avant. S'il bondit vers 290, le regroupement n'est pas appliqué.
- [ ] **Devis** : la recherche de panneaux ne propose plus les axes.
- [ ] **GPS manquant** : les 101 pieds y apparaissent. **C'est voulu** —
      recopier la position de l'axe ferait rendre « trop loin » au contrôle
      géo des piges. À remplir depuis le terrain, ou laisser la
      géolocalisation automatique le faire depuis les premières piges.

#### À trancher avant la phase 2

- [ ] `NB_PANNEAUX` du catalogue : un axe compte pour **1 entrée** (choix
      actuel, continuité avec les 255 déjà annoncés) ou pour **ses pieds
      disponibles** (plus juste, mais le chiffre bondit) ?
- [ ] ODP et TM (cf. § 18) — la phase 2 ne s'ouvre pas sans.

#### Rappel de calendrier

**Ne pas déployer le découpage avant l'envoi automatique du catalogue du
20/11.** Le regroupement d'affichage est livré, donc le risque est maîtrisé,
mais trois jours avant un envoi réel n'est pas le moment de découvrir un
écart.

#### 18 ter. Le sélecteur de réservation *(2026-11-11)*

- [ ] Vue **cartes** et vue **liste** : chaque pied porte « ⛓ pied 3/11 · 3 faces ».
- [ ] Bouton « ⛓ 5 » sur un pied → coche les 5 pieds libres de l'axe d'un
      geste. Re-cliquer les décoche tous.
- [ ] Le compteur du bouton correspond au nombre de pieds **affichés** et
      libres (pagination comprise).
- [ ] 🔴 **Le plus important** : filtrer sur `statut = libre` et une période
      où `CDYLUP-001` est vendu. **Aucun de ses 10 pieds ne doit apparaître.**
      Cet écran recalculait la disponibilité tout seul, sans passer par
      `AvailabilityService` — le défaut est corrigé, mais c'est le test qui
      le prouve. S'ils apparaissent, ne pas réserver : on revendrait un axe
      déjà vendu.

## 19. Panneaux d'un renouvellement confirmé retenus dans Panora *(2026-11-11)*

Règle validée le 11/11 (« 1a 2a 3oui ») : après « Renouvellement
confirmé », les panneaux de la campagne sont réservés à **son client** du
lendemain de la fin jusqu'à **30 jours après**. Un autre client est refusé
comme sur un panneau occupé ; le même client passe. « Veut renouveler » :
simple information, rien n'est bloqué. Source unique :
`RenouvellementService::retenues()`.

#### D'abord, sur la vraie base : des engagements déjà pris ?

Avant ce correctif, rien n'empêchait un autre client de prendre ces
panneaux. Ce contrôle liste, pour chaque renouvellement confirmé, les
réservations et campagnes d'**autres clients** qui tombent dans la retenue :

```
php artisan tinker --execute="
  \$j = (int) config('diffusion.renouvellement.retenue_jours', 30);
  foreach (App\Models\Renouvellement::where('statut','confirme')
      ->whereDate('fin_campagne','>=',now()->subDays(\$j)->toDateString())->get() as \$r) {
    \$du = \$r->fin_campagne->copy()->addDay()->toDateString();
    \$au = \$r->fin_campagne->copy()->addDays(\$j)->toDateString();
    \$ids = DB::table('campaign_panels')->where('campaign_id',\$r->campaign_id)->where('type','interne')->pluck('panel_id')->filter()->all();
    \$resa = DB::table('reservation_panels as rp')->join('reservations as x','x.id','=','rp.reservation_id')
      ->whereIn('rp.panel_id',\$ids)->whereIn('x.status',['en_attente','confirme'])->whereNull('x.deleted_at')
      ->where('x.client_id','!=',\$r->client_id)->where('x.start_date','<=',\$au)->where('x.end_date','>=',\$du)->pluck('x.reference');
    \$camp = DB::table('campaign_panels as cp')->join('campaigns as c','c.id','=','cp.campaign_id')
      ->whereIn('cp.panel_id',\$ids)->whereIn('c.status',['planifie','actif','pause'])->whereNull('c.deleted_at')
      ->where('c.client_id','!=',\$r->client_id)->where('c.start_date','<=',\$au)->where('c.end_date','>=',\$du)->pluck('c.name');
    echo 'Campagne ',\$r->campaign_id,' (retenue ',\$du,' -> ',\$au,') : ',
      \$resa->merge(\$camp)->unique()->implode(', ') ?: 'rien', \"\n\";
  }"
```

- [ ] Résultat noté : ……
- [ ] Toute ligne autre que « rien » est un panneau promis à deux clients :
      à régler avec le commercial **avant** la date de la retenue.

#### Écrans à ouvrir

- [ ] **Disponibilités**, période juste après la fin d'une campagne au
      renouvellement confirmé : ses panneaux portent « Renouvellement » et le
      badge vert foncé « 🔁 Renouvellement confirmé · client ». L'ⓘ donne la
      date de fin de retenue. La case ne se coche pas ; si la période dépasse
      la retenue, le bouton propose « 📅 Dès le … » (le lendemain).
- [ ] Même écran, client qui a répondu « Je souhaite renouveler » sans
      confirmation : badge clair « Renouvellement demandé · client », panneau
      **toujours cochable**.
- [ ] Filtre « Libre » : les panneaux retenus n'y sont plus. Filtre
      « Occupé » : ils y sont.
- [ ] Fiche de la campagne qui renouvelle → **🔁 Renouveler la campagne** :
      la nouvelle campagne garde **tous** ses panneaux (le même client passe).
- [ ] Réservation d'un **autre** client sur un panneau retenu (ajout de
      panneau, confirmation, proposition acceptée, import PDF) : refus
      « retenu pour le renouvellement de … ».
- [ ] PDF et Excel des disponibilités avec statut affiché : le panneau est
      « Occupé » jusqu'à la fin de la retenue, **sans** nom de client.

#### Ce qui n'a pas changé

- Les devis ne vérifient toujours aucune disponibilité.
- La carte et l'inventaire montrent le statut enregistré du panneau, pas la
  période.
- Le client concerné ne peut pas cocher ses propres panneaux retenus dans
  l'écran Disponibilités (le client n'y est choisi qu'à la fin) : il passe
  par « Renouveler la campagne ». Si le renouvellement est annulé (statut
  changé dans le dossier), la retenue tombe aussitôt.

## 20. Axes Lumipub : taxes et factures *(2026-11-12)*

Règles validées le 12/11 (cf. `docs/AXES_MULTI_PIEDS.md` § 3, décisions 6 à 8) :
prix d'un axe = **somme des prix HT des pieds réservés** ; ODP et TM d'un
axe **jamais calculées** — 0, saisies par le comptable ; **une ligne par axe**.

#### 🔴 Module Taxes — les chiffres depuis le 11/11 au soir sont faux

Depuis le découpage, le module comptait les 101 pieds **en plus** des 13
lignes d'axe. C'est corrigé : axes et pieds sortent de tout calcul
automatique (liste, fiche commune, taxes de l'année, rapports).

- [ ] Toute déclaration ou tout export taxes fait entre le 11/11 au soir et
      le déploiement de ce correctif, sur Cocody, Treichville ou
      Adiaké-Assinie : **à refaire**.
- [x] **Fait le 12/11 en production : pieds 0, lignes d axe 0.** Contrôle après déploiement — doit afficher `dont pieds : 0` et
      `dont lignes d axe : 0` :

```
php artisan tinker --execute="
  \$l = app(App\Services\TaxCalculationService::class)->generateLines('annuel', 0, 2026);
  \$p = \$l->whereIn('panel_id', App\Models\Panel::whereNotNull('axe_id')->pluck('id')->all());
  \$a = \$l->whereIn('panel_id', App\Models\Panel::where('est_axe', true)->pluck('id')->all());
  echo 'Total taxes 2026 : ', \$l->sum('amount'), \"\n\";
  echo 'dont pieds : ', \$p->sum('amount'), \"\n\";
  echo 'dont lignes d axe : ', \$a->sum('amount'), \"\n\";"
```

- [x] Total noté le 12/11 : **104 026 000 FCFA** de taxes 2026 (pas de relevé d avant le correctif pour comparer). À rapprocher du total que la direction obtenait en retirant les Lumipub à la main (il doit être **plus bas** qu'avant, et proche de ce
      que la direction obtenait en retirant les Lumipub à la main).

#### Factures

- [ ] Facture générée depuis une campagne qui porte des pieds : **une ligne
      par axe**, « CDYLUP-004 — … — 5 pieds », PU = somme des 5 pieds,
      quantité 1. Sous la désignation : ⓘ + cases **ODP** et **TM** à 0.
- [ ] Saisir des montants dans ces cases : le récapitulatif se met à jour.
      Enregistrer, rouvrir : les montants sont conservés.
- [ ] Facture manuelle, campagne choisie : un axe = **une** option
      « … — N pieds », pas une option par pied.
- [ ] Deux clients sur des pieds différents du même axe, même période : la
      facture du second n'est plus refusée « déjà facturé ».
- [ ] ⚠ Remplace le contrôle du § 18 bis sur **102 LADILI** : la facture
      propose PU 130 000, qté 1, mais **ODP et TM à 0**, à saisir à la main
      (avant : 15 000 et 3 000 calculés).

#### Axe entier d'un ancien contrat — repris pied par pied (point 1a)

BEM (4 axes), MOOV (1) et LADILI (1) tiennent encore la **ligne** de l'axe,
c'est-à-dire tous ses pieds. Règle du 12/11 : on ne la reprend plus telle
quelle, sinon on revendrait les pieds déjà pris par un autre client.

- [ ] **🔁 Renouveler la campagne** sur une campagne qui tient un axe : la
      nouvelle campagne reçoit les **pieds** un par un, sans ceux déjà pris,
      avec l'avertissement « ⛓ Axes : CDYLUP-001 : 6 pied(s) sur 10 repris… ».
- [ ] 🔶 **Prix** : chaque pied repris est au tarif d'un pied (130 000).
      Un axe de 10 pieds tenu à 130 000/mois sur l'ancien contrat passe donc à
      1 300 000/mois sur la campagne renouvelée. **Voir le prix avec la
      direction avant tout envoi au client.**
- [ ] **📅 Prolonger** une campagne qui tient un axe entier, alors qu'un de ses
      pieds est pris par un autre client sur la prolongation : **refus**, avec
      la liste des pieds pris et l'invitation à passer par « Renouveler ».
- [ ] **Import PDF** avec un code d'axe : l'aperçu annonce « ⛓ Axes : … »,
      et la campagne importée porte les pieds libres.

## 21. Axes : une carte par axe et la liste déroulante des pieds *(2026-11-12)*

Demande du MP : retrouver les axes comme avant dans les disponibilités, et
choisir leurs pieds dans une liste déroulante pour les attribuer à une
réservation ou une campagne.

**Défaut corrigé au passage** : la requête de l'écran Disponibilités ne
lisait pas les colonnes des axes. En production, depuis le 11/11, chaque pied
y arrivait comme un panneau ordinaire : ni badge « pied 3/11 », ni bouton
« ⛓ N ». C'est très probablement ce que le MP a vu.

- [ ] **Disponibilités**, période en janvier 2027 : `CDYLUP-004` apparaît en
      **une** carte, avec la photo de l'axe, « ⛓ AXE · 11 PIEDS · 3 FACES » et
      « N / 11 libres ». Aucun pied n'apparaît seul dans la grille.
- [ ] « ⛓ Choisir les pieds ▾ » déplie la liste : chaque pied avec son statut,
      les pieds pris grisés, la case désactivée. Le compteur « 2 sélectionnés ·
      9 disponibles » suit les cases.
- [ ] « Tout cocher / décocher » coche les pieds libres de l'axe.
- [ ] Confirmer la sélection → réservation : elle contient les **pieds** cochés.
- [ ] Filtre « Libre » : un axe y est s'il lui reste un pied libre, et sa liste
      montre quand même tous ses pieds. Filtre « Occupé » : un axe y est si un
      de ses pieds est pris.
- [ ] Compteurs du haut (« ✅ N disponibles ») : en **pieds**.
- [ ] Vue **liste** : même liste déroulante, sous le nom de l'axe.
- [ ] **Fiche campagne → Ajouter des panneaux** : une ligne par axe, « ⛓ Choisir
      les pieds ▾ », « X / Y pieds libres », cases par pied ; « ✅ Ajouter »
      ajoute les pieds cochés.
- [ ] **Fiche réservation → Ajouter un panneau** (menu déroulant) : un groupe
      par axe, ses pieds dessous, les pieds pris grisés.
- [ ] **Modifier une réservation** : même liste déroulante dans le tableau.
- [ ] **Devis** : la recherche propose **un résultat par axe** (« … — 11 pieds »). Le
      choisir affiche ses pieds, tous cochés : en décocher met à jour le prix (somme
      des pieds cochés) et « — N pieds » dans la désignation. Dessous : ⓘ + cases
      **ODP** et **TM** à 0, à saisir ; le récapitulatif les reprend.
- [ ] **Devis depuis Disponibilités** (« Créer un devis » avec des pieds cochés) :
      une ligne par axe, avec les pieds cochés.
- [ ] **Devis accepté → réservation** : la réservation porte les **pieds** de la
      ligne (pas l axe entier), le prix de la ligne réparti sur eux.
- [x] 🔧 **Passée en production le 12/11 (lot 85).** Migration `2026_11_12_100000_add_axes_to_quote_lines` (3 colonnes
      facultatives sur `quote_lines`) : vérifier qu elle est passée après le
      déploiement (`php artisan migrate:status | grep axes_to_quote`). Tant
      qu elle ne l est pas, les devis s enregistrent mais sans les pieds.

## 22. Espace technicien — refonte, étape 1 : « Ma journée » et les axes *(2026-11-12 — en production le 12/11 au soir, main ddcd7312)*

> **Passé en production le 12/11 au soir** avec les § 22 bis, 22 ter, 23 et 24
> (8 commits reportés sur main, aucune migration). Sur chaque téléphone de
> technicien : fermer l'onglet de l'espace technicien et le rouvrir une fois
> (service worker v2.13.0). Les cases ci-dessous restent à cocher en production.
> Positions : 10 axes sur 12 sans position (relevé sur dev le 12/11) — leurs
> pieds affichent « Position du panneau inconnue » ; laissé de côté pour
> l'instant (décision du 12/11), cf. `geo:backfill --panels`.

Livré sur **develop** pour essai sur **dev.panora-cible.com**. Pas en
production tant que ce n'est pas validé (maquette validée :
https://claude.ai/artifact/NmxHhUh7yyjZHVqZVRj1RY).

Règles validées le 12/11 : « Je suis arrivé » sur l'axe démarre tous ses
pieds ; un pied sans position est contrôlé sur celle de l'axe à ± 500 m ;
axe entier d'un ancien contrat (choix 4b) : une photo par pied sur la même
tâche, fermée par le technicien avec « Terminé ».

**Préparer** sur dev : un technicien qui a (a) des pieds d'un axe découpé sur
une campagne active (ex. `CDYLUP-004` après `axes:decouper`), (b) une pose
ordinaire, (c) si possible une pose sur un axe entier (BEM, MOOV, LADILI).
Ouvrir son lien `/tech/{jeton}/poses` **sur un téléphone**.

- [ ] **En-tête** : l'initiale ouvre les préférences, « Bonjour Prénom »,
      date et zones, « ? » ouvre l'aide, la cloche ouvre les alertes (point
      rouge s'il y a du non-lu). « N / M aujourd'hui » ne compte que la
      journée (l'ancienne barre comptait depuis toujours).
- [ ] **Barre du bas** : Ma journée · Carte · Mes photos (pastille si des
      photos sont à refaire) · Alertes (pastille du nombre de non-lus).
- [ ] **Recherche** : une référence, une rue ou une campagne → seules les
      poses concernées restent, les communes vides disparaissent ; « Aucune
      pose ne correspond » sinon.
- [ ] **Maintenant** : la pose la plus urgente en grand (photo du panneau,
      « Y aller », « Photo ») ; elle n'est pas répétée plus bas.
- [ ] **Carte d'axe** : « CDYLUP-004 · Axe · N pieds », les segments, « x / N
      pieds photographiés ». Toucher l'en-tête plie / déplie la route ; le
      pied à faire en premier est en bleu.
- [ ] « **Y aller** » sur l'axe → confirmation → Maps ; la carte passe « En
      route ». Côté admin (poses de la campagne) : **tous** les pieds de
      l'axe sont en route.
- [ ] « **Je suis arrivé** » → « Sur place » ; admin : tous les pieds sur
      place (50 %), **aucun** terminé sans sa photo.
- [ ] **Photo d'un pied** : l'aperçu dit « Tu es à X m **de l'axe** », vert
      jusqu'à 500 m. Après l'envoi : ni écran « Bravo » ni rechargement ; le
      pied passe « Envoyée, à valider », son segment orange, le compteur et
      l'en-tête +1, le pied suivant passe en bleu, message « Pied 2 envoyé ·
      plus que 3 pieds ».
- [ ] Dernier pied envoyé : « Axe terminé », la carte passe « Terminé ».
- [ ] Toucher un pied (hors bouton Photo) ouvre le tiroir de la pose, avec la
      photo de l'axe.
- [ ] **Pose ordinaire** : comme avant (écran « Bravo » puis rechargement),
      mais l'écran « Bravo » donne le vrai compteur du jour (il affichait
      « 0 / 0 » depuis le retrait de l'ancien en-tête).
- [ ] 🔶 **GPS d'une pose ordinaire** : l'aperçu suit maintenant les seuils du
      serveur — vert ≤ 50 m, orange ≤ 200 m, rouge au-delà — au lieu de
      100 / 500 m. C'est ce que l'admin voyait déjà sur la pige (« bien
      enregistrée » sur le téléphone, « à vérifier » chez l'admin). **À
      confirmer sur le terrain** : si c'est trop strict, c'est le serveur
      (`GeoService::FRAUD_OK_M` / `FRAUD_WARN_M`) qu'il faut changer, le
      téléphone suivra.
- [ ] **Axe entier** (ancien contrat) : encadré orange « Contrat signé avant
      le découpage » ; chaque photo s'ajoute (vignettes, « N photo(s)
      envoyée(s) »), la tâche reste ouverte et passe « Sur place ».
      « Terminé » avec moins de photos que de pieds → « Il manque N photos…
      Terminer quand même ? ». Après « Terminé » : pose réalisée côté admin.
- [ ] Admin : une pige saisie par l'admin sur une pose d'**axe entier** ne la
      clôt plus non plus (même règle).
- [ ] **Hors réseau** : photo d'un pied → « 📤 En attente » ; au retour du
      réseau, elle part **une seule fois** (pas de doublon dans les piges).
- [ ] **Photos à refaire** → « Refaire » sur un pied refusé : l'appareil photo
      s'ouvre sur ce pied.
- [ ] « ? » ouvre toujours l'aide, même avec des alertes non lues (la cloche
      les a).
- [ ] 🔧 `geo:backfill --force` (si on le relance) reclasse les anciennes
      piges des axes entiers avec la tolérance de 500 m.

### 22 bis. Étape 2 : plus de rechargement, menu, alertes *(2026-11-12, develop seulement)*

Même préparation que ci-dessus. Fermer l'onglet et le rouvrir une fois
(nouvelle version du service worker, v2.11.0).

- [ ] **Photo d'une pose ordinaire** : plus d'écran « Bravo » ni de
      rechargement. La pose quitte la liste en fondu, et une feuille « Photo
      envoyée » s'ouvre : « N / M poses faites aujourd'hui », puis la **pose
      suivante** (photo, référence, nom, « Commune · à 1,6 km »).
- [ ] La pose suivante est **celle que la page aurait mise en « Maintenant »**
      après un rechargement : « Maintenant » est remplacée sous la feuille.
      🔶 Avant, l'écran « Bravo » proposait une pose de la **même commune**,
      puis la page rechargée en montrait parfois une autre (une pose en
      retard ailleurs, par exemple). Voir la question à trancher dans le
      rapport du 12/11.
- [ ] « **Continuer** » ouvre la fiche de la pose suivante (pour un axe : la
      carte se déplie sur le pied). « **Ma liste** » ferme la feuille.
- [ ] **Dernier pied d'un axe** : la feuille s'intitule « Axe terminé » et
      propose la suite. Tant qu'il reste des pieds : pas de feuille, la carte
      avance seule (comme à l'étape 1).
- [ ] **« Terminé » sur un axe entier** : même feuille « Axe terminé ».
- [ ] **Dernière pose de la journée** : l'écran « fin de journée » (inchangé).
- [ ] **Initiale** (en haut à gauche) : feuille « Aide : les 3 gestes »,
      « Appeler mon chef » (si le numéro est configuré : `tech_space.chief_phone`),
      « Ma feuille de route papier », « Texte plus gros », « Économie de
      batterie » (interrupteurs qui restent dans leur position au retour).
- [ ] **Alertes** : toucher une alerte la marque lue (la pastille baisse) et
      mène à sa pose — « Nouvelle pose » ouvre la pose, « Photo refusée »
      ouvre « Photos à refaire », « Photo validée » ouvre « Mes photos ».
- [ ] **Y aller** : la confirmation affiche le temps **en voiture** (ordre de
      grandeur : vol d'oiseau × 1,4 à 20 km/h). Second « Y aller » sur un axe
      déjà en route : **plus** d'avertissement « tu es déjà en route vers une
      autre pose » à propos de ses propres pieds.
- [ ] « Y aller » / « Je suis arrivé » **depuis le tiroir d'un pied** : la
      carte de l'axe passe « En route » / « Sur place » sans rechargement.

### 22 ter. Étape 3 : Carte et Mes photos *(2026-11-12, develop seulement)*

Fermer l'onglet et le rouvrir une fois (service worker v2.12.0).

**Défaut corrigé** : la carte, « Mon chemin » et la feuille de route ne
lisaient que la position **propre** du panneau. Depuis le découpage des
axes, leurs pieds (nés sans position) **avaient disparu de la carte** et
de « Mon chemin ». Ils prennent maintenant la position de leur axe
(`Panel::positionTerrain`, la même règle que « Ma journée » et le contrôle
GPS).

- [ ] **Carte** (« Ma tournée ») : un arrêt **par axe** — pastille « ⛓ 5 »
      — à la position de l'axe ; les poses ordinaires comme avant. En-tête :
      « N arrêts · M sans position » et le bouton feuille de route papier.
- [ ] Toucher un arrêt d'axe : « ⛓ Axe · 5 pieds à poser », « Y aller »,
      « Voir l'axe » → « Ma journée » avec la carte de l'axe dépliée.
      Toucher une pose : « Voir » ouvre sa fiche. (Avant : « Voir » ne
      menait nulle part.)
- [ ] « **Me localiser** » et « **Mon chemin** » sous la carte. « Mon chemin »
      passe **une fois** par axe, les étapes sont numérotées sans trou, et le
      total en km ne compte plus les poses des campagnes en pause.
- [ ] Panneau sans aucune position (ni lui ni son axe) : encadré bleu « N
      panneau(x) sans position — REF : « Y aller » cherche l'adresse dans
      Maps ».
- [ ] **Mes photos** : en-tête « Mes photos — La dernière par panneau ·
      tout l'historique », 4 filtres (Toutes / À valider / Validées /
      Refusées), puis une **galerie** de photos (pastille d'état, référence,
      « Aujourd'hui 10h47 », motif du refus en rouge).
- [ ] Photo refusée → « **Refaire** » : « Ma journée » s'ouvre sur « Photos à
      refaire », appareil photo à portée.
- [ ] **Photos à refaire** (Ma journée) : « DISTANCE PANNEAU » s'affiche
      aussi pour un pied d'axe (c'est la distance enregistrée à l'envoi).
- [ ] **Barre du bas** sur les trois pages ; l'onglet de la page est en
      orange. « Alertes » depuis Carte ou Mes photos ramène à « Ma journée »
      avec le tiroir des alertes ouvert (et sa liste remplie).
- [ ] **Feuille de route papier** : un pied d'axe affiche les coordonnées de
      son axe (avant : vide).

## 23. Suivi des axes côté admin *(2026-11-12 — en production le 12/11 au soir)*

Décisions du 12/11 : rapport PDF de piges = **une fiche par axe** ;
compteurs côté client **en pieds** (affichage regroupé « 3/5 pieds
pigés ») ; e-mail « photo validée » = **un seul par axe**, quand son
dernier pied est validé ; pose suivante du technicien = règle « Maintenant ».

### 23 a. Phase A : les défauts

- [ ] **Carte des poses** (admin → Poses → Carte) : les pieds d'un axe
      découpé y sont, à la position de leur axe (avant : absents). Le popup
      porte la référence du pied.
- [ ] **Modale de validation** d'une photo de pied : la photo de référence
      (celle de l'axe) et la distance s'affichent (avant : vides).
- [ ] **Fiche d'un axe** (admin → Panneaux → CDYLUP-004) : « Dernières piges »
      montre les plus récentes, y compris celles de ses pieds (« · Pied 3 ») ;
      une photo refusée est marquée « Refusée » (avant : « En attente »).
- [ ] **Piges, filtre « panneau » = un axe** : on y trouve les photos de ses
      pieds.
- [ ] **Espace technicien → Mes photos**, axe entier : chaque photo (une par
      pied) reste visible ; avant, il n'en restait qu'une. Une photo refusée
      d'un axe entier disparaît de « Photos à refaire » quand une nouvelle
      photo arrive sur l'axe **après** le refus.

### 23 b. Phase B : l'admin regroupé par axe

- [ ] **Poses** (liste générale et onglet « Poses » d'une campagne) : au-dessus
      des pieds d'un axe, une ligne « ⛓ Axe CDYLUP-004 · Riviera 3 — 5 pieds ·
      3/5 posés · 2/5 pigés · 1/5 validés » ; les pieds dessous, décalés,
      « Pied 3 ». Les compteurs portent sur **tous** les pieds de l'axe sur la
      campagne, même ceux d'une autre page. Replier la campagne replie aussi
      la ligne d'axe.
- [ ] **Fiche d'une pose de pied** : carte « ⛓ Les pieds de l'axe CDYLUP-004 »
      — chaque pied, sa pose, ses photos (📸 N · M ✓), son statut ; la pose
      ouverte est surlignée ; un clic ouvre la pose du pied.
- [ ] **Piges** (liste) : sous le nom d'une photo de pied, « ⛓ Pied 3 ·
      CDYLUP-004 » ; un clic filtre sur l'axe (toutes les photos de ses pieds).
- [ ] **Validation des piges** (carrousel) : « — ⛓ Pied 3 · CDYLUP-004 » après
      le nom ; la carte mini du pied le place à la position de l'axe.
- [ ] **Créer une pose / une pige** pour une campagne : chaque pied porte
      « ⛓ Pied 3 · CDYLUP-004 ».
- [ ] Inchangé (décision du 12/11, compteurs en pieds) : tuiles de la fiche
      campagne, tableau des vagues, pilotage en direct, performance.

### 23 c. Phase C : ce que voit le client

- [ ] **Rapport PDF de piges** (Piges → Exporter) avec des photos de pieds :
      **une fiche par axe** — « Riviera 3 », « Axe : CDYLUP-004 · 3 pieds »,
      puis chaque photo légendée « Pied 1 · CDYLUP-004-P01 · 12/11/2026 à
      10:42 ». Résumé du haut : « N emplacements, dont X axe(s) (Y pieds),
      dans M communes ». Sans axe dans le rapport : inchangé (« N panneaux »).
- [ ] Écran de préparation de l'export : chaque photo de pied porte « ⛓ Pied
      3 · CDYLUP-004 ».
- [ ] **E-mail « photo validée »** (avec un client qui a une adresse) : valider
      les pieds d'un axe **un par un** — aucun e-mail tant qu'il en reste un ;
      au **dernier** pied validé, **un seul** e-mail « Photos de l'axe
      CDYLUP-004 disponibles » avec la photo de chaque pied. Revalider un pied
      plus tard : pas de nouvel e-mail. Un panneau ordinaire : un e-mail par
      photo, comme avant. (Journal : `pige.notify.axe_en_attente`.)
- [ ] Le bouton de l'e-mail ouvre une page « Axe CDYLUP-004 » avec les photos
      de tous les pieds (lien `/p/…`, valable 30 jours).
- [ ] **Espace client → une campagne** : **une carte par axe** (photo de l'axe,
      « ⛓ Axe · 5 pieds », « 3/5 posés », « 2/5 pigés »), et dedans chaque
      pied : sa vignette validée (clic = agrandir), « Posé · photo en
      validation » ou « À poser ». Les compteurs du haut restent en pieds.
- [ ] **ZIP des photos** (espace client) : un dossier par axe, fichiers
      « Pied-03_CDYLUP-004-P03_pige-123.jpg » ; panneaux ordinaires
      « COC-020_pige-45.jpg » (avant : « pige-45.jpg »).
- [ ] **Lien de pige de campagne** (`/pige/{jeton}`) : dans chaque commune, les
      pieds d'un axe sous une ligne « ⛓ CDYLUP-004 · Riviera 3 — 5 pieds · 2/5
      pigés » ; chaque carte « Pied 3 · CDYLUP-004-P03 ».
- [ ] **Fiche de pose PDF** (campagne → Fiche de pose) : un pied a la photo de
      son axe et « ⛓ Pied 3 · CDYLUP-004 » ; en mode liste, même mention.

## 24. Retours du test sur dev du 12/11 *(en production le 12/11 au soir)*

Fermer l'onglet de l'espace technicien et le rouvrir une fois (service
worker v2.13.0).

- [ ] **Aperçu de la photo** (avant « Envoyer ») : la photo occupe toute la
      largeur, bandes noires si besoin ; plus de bande blanche à droite.
- [ ] **Carte « Maintenant »** : grande photo sur toute la largeur, pastille
      « Sur place » visible en bas à droite (avant : photo écrasée à gauche).
- [ ] **Alertes** : la rangée « Toutes / Refusées / Nouvelles poses » n'est
      plus coupée, même avec une longue liste.
- [ ] **Pose reprogrammée** (admin : changer la date d'une pose attribuée, à
      l'unité ou en lot) : le technicien reçoit « 📅 Pose reprogrammée : REF —
      Prévue le 13/11 à 19h00 », et l'alerte « Nouvelle pose » d'origine
      affiche la nouvelle date. Minuit = date seule ; autre année = année
      affichée (« Prévue le 01/01/2027 »).
- [ ] **Photo validée** (côté technicien) : « Le Media Planner a validé ta
      photo. Bon boulot ! » (les anciennes alertes gardent leur texte).
- [ ] **Jusqu'à 3 photos** par panneau ou pied : après la 1re, la feuille
      « Photo envoyée » affiche « 1/3 photos · REF » et « 📷 Ajouter une photo
      (2/3) » ; la pose est déjà réalisée. « Terminer » ferme ; « Continuer »
      (ou « Pied suivant » sur un axe) passe à la suite. À 3/3, plus de bouton
      « Ajouter » ; une 4e photo est refusée. Une photo refusée ne compte pas.
- [ ] **Mes photos** : les 2 ou 3 photos d'une même pose apparaissent toutes ;
      une rechange (autre pose) remplace les anciennes.
- [ ] **Admin, fiche d'une pose de pied → « Les pieds de l'axe »** : 📸 et
      « N/5 pigés » justes (avant : 0 partout).
- [ ] **Admin, liste des poses et onglet Poses** : la pastille photos (« N ·
      M validées ») est juste ; avant elle valait toujours 0.
- [ ] **Feuille de route papier** : en-tête « Feuille de route — Nom », pastilles
      « N à poser · M communes · X axes · Y en retard », une fiche par pose avec
      sa case ; les pieds d'un axe sous l'axe, une case par pied. Lisible sur
      téléphone, propre à l'impression.
- [ ] 🔧 « Position du panneau inconnue » sur CDYLUP-004-P01 : l'axe CDYLUP-004
      n'a pas de position sur dev (vérifier ses latitude / longitude).

## 25. Découpage des axes entiers des anciens contrats *(2026-11-13)*

Les contrats signés avant le découpage (BEM #2, MOOV #8, LADILI #102) tiennent
la LIGNE de l'axe : une seule pose pour 9 ou 10 pieds, d'où une carte « axe
entier » différente dans l'espace technicien. Décision du 13/11 : découper ces
campagnes en pieds, comme les nouvelles ventes. Commande
`axes:decouper-campagnes` (simulation par défaut).

Ce qu'elle fait, pour chaque axe entier d'une campagne planifiée / active / en
pause :
- la campagne tient ses **pieds** au lieu de la ligne de l'axe ;
- la **réservation garde la ligne de l'axe et son prix** : la facture générée
  plus tard est la même qu'avant, au franc près (une ligne, prix du contrat,
  ODP/TM à saisir à la main) ;
- chaque pose **ouverte** de l'axe devient une pose par pied (même technicien,
  équipe, date, statut, vague, consigne), sans alerte ni WhatsApp ; la pose de
  l'axe passe « annulée » avec la mention « Découpée en N pieds » ;
- les poses **réalisées** et leurs photos restent (historique) ;
- **ne découpe pas** : un axe dont la pose ouverte a déjà des photos (à fermer
  d'abord avec « Terminé »), un axe absent de la réservation, une réservation
  qui tient déjà des pieds ; un pied pris par une autre campagne ou réservation
  sur la période est laissé de côté (signalé).

En production :
- [x] `php artisan axes:decouper-campagnes` → lire la simulation (6 axes
      attendus : CDYLUP-001, -007, -008, TVILUP-002 pour BEM ; TVILUP-001
      pour MOOV ; CDYLUP-002 pour LADILI). **CDYLUP-008 est « en cours »** : si
      sa pose a déjà des photos, la simulation le dit — la fermer avec
      « Terminé » ou la laisser en axe entier.
- [x] Si la simulation est bonne : `php artisan axes:decouper-campagnes --appliquer`
      → **appliqué en production le 13/11** : 6 axes, 49 pieds, 49 poses de pied
      (poses #538, 540, 541, 543 BEM · #547 MOOV · #404 LADILI annulées « découpées »).
- [x] Relancer `php artisan axes:decouper-campagnes` : « Aucun axe entier dans une
      campagne en cours : rien à faire. » — vu le 13/11. Poses de pied : BEM 33
      (23 planifiées, 9 en cours CDYLUP-008, 1 réalisée CDYLUP-007-P09 à 10h18 après
      le découpage), MOOV 10, LADILI 6.
- [ ] LADILI : attribuer un technicien aux 6 poses de pied (en lot) avant le 01/12.
- [ ] Espace technicien de Yannick (ou du technicien des poses) : BEM, MOOV et
      LADILI s'affichent en carte d'axe avec ses pieds, comme les autres.
- [ ] Admin, campagne BEM → onglet Panneaux : les pieds ; onglet Poses : une
      pose par pied, la pose de l'axe annulée (masquée par défaut).
- [ ] Admin, « Générer la facture » sur BEM **en brouillon de test** (puis le
      supprimer) : une ligne par axe au prix du contrat, comme avant.
- [ ] Portail client BEM : une carte par axe, « Axe entier · photo du … » pour
      l'ancienne photo, compteurs en pieds (jamais « 6/5 pigés »).
- [ ] Une ancienne alerte « Nouvelle pose : CDYLUP-001 » chez le technicien
      mène à la pose annulée : normal, les nouvelles poses de pied sont dans
      « Ma journée ».
- [ ] LADILI : la facture brouillon existante n'est pas touchée.

---

## § 22 — Disponibilité selon la période : devis + inventaire (2026-11-13)

**Le défaut corrigé.** Le sélecteur de panneaux du devis affichait le statut
du jour (`panels.status`) au lieu de la disponibilité sur la période de
campagne saisie. CDY-048, occupé jusqu'au 22/11/2026, s'affichait `OCCUPE`
pour un devis de 2027. `searchPanels()` ne recevait aucune date.

**La règle, validée par écrit le 2026-11-13.**

| Situation sur la période saisie | Affichage | Sélection |
|---|---|---|
| Aucun chevauchement | `LIBRE` | ✅ |
| Occupé, se libère **pendant** la période | `LIBRE DÈS LE jj/mm/aaaa` | ✅ |
| Occupé jusqu'à la fin de la période ou au-delà | `OCCUPÉ — libre le jj/mm/aaaa` | ⛔ |
| Maintenance | `MAINTENANCE` | ⛔ |
| Aucune date saisie | statut du jour | ✅ (on ne bloque pas sur l'inconnu) |

Centralisée dans `AvailabilityService::verdictSelection()`, à côté de
`displayStatusForPeriod()`. Jamais recopiée en JavaScript.

### Devis — à vérifier
- [ ] Nouveau devis **sans dates** : bandeau bleu « Renseigne la période »,
      les badges disent `SAISIS LES DATES`, rien n'est bloqué.
- [ ] Période **01/01/2027 → 31/12/2027**, chercher `CDY-048` : badge `LIBRE`
      (et non `OCCUPE`). C'est le cas d'origine.
- [ ] Période **01/11/2026 → 30/11/2026**, chercher `CDY-048` : badge
      `OCCUPÉ — LIBRE LE 23/11/2026`, ligne `🔒 AOS AFRICA · 22/10/2026 →
      22/11/2026`, **impossible à sélectionner** (rayé, curseur interdit).
- [ ] Période **01/11/2026 → 31/12/2026** : badge `LIBRE DÈS LE 23/11/2026`,
      **sélectionnable**. Une fois choisi, bandeau bleu sous la ligne.
- [ ] Choisir un panneau libre, **puis** changer les dates pour une période où
      il est pris → bandeau rouge ⛔ sous la ligne, sans rien effacer.
- [ ] Survoler un résultat dans la liste : l'adresse et la commune restent
      lisibles sur le fond orange (c'était le défaut de lisibilité signalé).
- [ ] Dates inversées (début après la fin) : bandeau rouge immédiat, bordures
      rouges, l'enregistrement est bloqué avant l'aller-retour serveur.
- [ ] Axe Lumipub sur une période où certains pieds sont pris : les pieds
      occupés sont décochés, grisés, marqués 🔒 ; le compteur dit
      « N/M pieds libres ». L'axe reste proposable s'il reste un pied libre.
- [ ] **Garde-fou serveur** : laisser la page ouverte, faire réserver le
      panneau ailleurs, puis enregistrer → refus avec le nom du panneau.
      Seuls les panneaux **nouvellement ajoutés** sont contrôlés : un devis
      existant dont un panneau s'est fait prendre reste modifiable.

### Devis — une seule ligne (capture 2)
- [ ] Choisir un panneau du parc : le champ libellé en dessous disparaît,
      il ne reste que la ligne du sélecteur.
- [ ] Bouton « ✎ Modifier le libellé » : rouvre le champ pour un libellé
      négocié, puis « ✕ Garder le libellé du panneau ».
- [ ] Retirer le panneau (croix) : le champ libellé revient et redevient
      obligatoire. **Enregistrer doit marcher dans les deux cas** (le champ
      masqué perd `required`, sinon le navigateur refuse de soumettre).
- [ ] Désignation libre (sans panneau du parc) : inchangée.

### Inventaire — vérifier la disponibilité sur des dates (capture 1)
- [ ] Bloc « 📅 Vérifier la disponibilité » sous les filtres : Du / Au /
      Afficher. Vide par défaut → l'écran est exactement celui d'avant.
- [ ] Saisir une période : chaque ligne reçoit sous son statut un second
      badge `✅ Libre sur la période` / `🕗 Libre dès le …` / `⛔ Occupé —
      libre le …`, avec le client et les dates qui bloquent.
- [ ] « Libres sur la période » : le compteur en haut doit correspondre au
      nombre réel sur **tout le parc filtré**, pas seulement la page 1
      (le filtre s'applique avant la pagination).
- [ ] « Occupés sur la période » : liste complémentaire de la précédente.
- [ ] Pagination page 2+ : la période est conservée.
- [ ] Dates inversées : bandeau rouge, la période est ignorée (serveur
      **et** client).
- [ ] « ↺ Réinitialiser » vide aussi les trois champs de période.

### Pose et maintenance — tranché le 2026-11-13 : aucun changement
Vérifié dans le code, pas supposé :
- **Pose** : le sélecteur n'affiche aucun statut commercial. Il montre
  `task_status` et `task_date` (l'état de la pose), et travaille dans une
  campagne déjà vendue. La disponibilité n'a pas de sens ici.
- **Maintenance** : affiche bien `panels.status`, mais c'est le bon
  renseignement — on signale une panne le jour où elle arrive, et savoir
  qu'une campagne tourne dessus est précisément ce qui compte.

Décision enregistrée pour ne pas y revenir. Si le MP veut malgré tout une
période sur la maintenance (planifier une intervention hors campagne),
c'est une évolution à part, pas une incohérence à corriger.

### Période obligatoire dès qu'un panneau est engagé — corrigé le 2026-11-13

**Ce que j'ai trouvé en creusant.** Les dates inventées par
`QuoteConverter` (« aujourd'hui → aujourd'hui + durée max des lignes »)
ne servaient pas qu'au contrôle de disponibilité : elles étaient écrites
dans `start_date` / `end_date` de la **réservation ferme** créée dans la
foulée. Un devis sans dates produisait donc un engagement contractuel sur
une période que personne n'avait choisie — et comme le client accepte
seul, depuis son espace ou le lien public, aucun commercial n'était là
pour le rattraper.

**Trois verrous** (le fallback ne survit que pour un devis de services
seuls, qui ne bloque aucun emplacement) :

- [ ] **Saisie** — devis avec un panneau du parc et sans dates :
      l'enregistrement est refusé, message sur le champ « Début de campagne ».
- [ ] **Formulaire** — poser un panneau fait passer le bandeau de la période
      en rouge « ⛔ Période obligatoire » ; retirer le panneau le repasse en
      bleu informatif. Le bouton Enregistrer ne part pas tant que c'est rouge.
- [ ] **Envoi** — un devis créé AVANT cette règle (donc sans dates) ne peut
      plus être envoyé : message d'erreur au commercial.
- [ ] **Conversion** — si un tel devis est quand même accepté par le client :
      aucune réservation n'est créée, le devis passe en `ACCEPTE_AVEC_CONFLIT`
      avec le motif « Période de campagne absente », et une alerte part au
      commercial. **Le client voit « Les dates de campagne restent à préciser »**
      et non « certains panneaux ne sont plus disponibles » (vérifier dans
      l'espace client ET sur le lien public).
- [ ] Devis de **services seuls**, sans aucun panneau : toujours
      enregistrable et convertible sans dates. Comportement inchangé.

À surveiller en production : des devis existants peuvent être sans dates.
Les repérer avant qu'un client ne les accepte —
`Quote::whereIn('status', ['envoye'])->whereNull('period_start')->whereHas('lines', fn($l) => $l->whereNotNull('panel_id'))->pluck('reference')`

### Tests automatisés
`tests/Unit/DevisDisponibilitePeriodeTest.php` — 18 tests, 43 assertions,
sans base de données (tournent sur le poste à base vide).

---

## § 23 — Taxes : maintenance, démantèlement et pieds Lumipub (2026-11-13)

### Trois défauts corrigés

**1. La maintenance écartait le panneau de TOUTES les périodes.** Le filtre
lisait `panels.status`, l'état *du jour* : un panneau passé en maintenance
aujourd'hui disparaissait de janvier, février, mars… où il était pourtant
debout et occupé. Même défaut que le sélecteur du devis — un état courant
qui juge le passé.

**RÈGLE VALIDÉE PAR ÉCRIT** : « l'ODP reste due tant que le mât occupe le
sol ». Une panne ne déplante pas le mât. Le panneau reste donc dans la liste
et reste taxé. La TM se régule seule : elle exige une campagne sur la
période, donc un panneau en panne et vide donne 0.

➜ **Le sens de la case s'inverse** : la maintenance est désormais **incluse**
par défaut, et une case « Exclure » retrouve l'ancienne vue.

**2. Les panneaux démantelés n'apparaissaient jamais dans la liste.** Le
démantèlement est modélisé par le soft delete (`deleted_at` = la date où le
mât a quitté le sol). Le **prorata existait déjà** — `moisODPDansPeriode()`
reçoit `$panel->deleted_at` — mais `whereNull('deleted_at')` empêchait la
ligne d'arriver. Or les totaux par commune (`panneauxPourCalcul`, en
`withTrashed`) la comptaient : **le total d'une commune ne se raccordait pas
à ses propres lignes.**

**3. Les Lumipub étaient entièrement hors du calcul** depuis le 12/11 (axe
*et* pieds), pour corriger un double comptage.

**RÈGLE VALIDÉE PAR ÉCRIT** : une ligne par **pied**. Un pied = un mât
planté. La **ligne d'axe n'est jamais taxée** dans aucun cas — elle ne
correspond à aucun mât, et la compter en plus de ses pieds, c'est reproduire
le bug du 12/11 (11 pieds comptés 12 fois). Décoché par défaut.

### À vérifier
- [ ] `/admin/taxes/details` : la case Maintenance dit maintenant
      « **Exclure** », décochée. Un panneau aujourd'hui en maintenance
      apparaît dans la liste, avec son ODP.
- [ ] Cocher « Exclure » : il disparaît (ancienne vue retrouvée).
- [ ] Un panneau démantelé en cours d'année apparaît avec le badge rouge
      « **DÉMANTELÉ jj/mm/aaaa** », et son ODP est calculée **jusqu'à cette
      date seulement**. Vérifier le montant à la main sur un cas.
- [ ] Ses campagnes passées génèrent toujours leur TM (c'est la demande
      « retracer les taxes sur les périodes où ils étaient occupés »).
- [ ] Case « **Lumipub · Inclure les pieds** », décochée : **le total ne
      doit pas bouger** par rapport à aujourd'hui. C'est le contrôle le plus
      important : rien ne doit changer tant qu'on ne le demande pas.
- [ ] Cocher : une ligne par pied, badge orange « PIED LUMIPUB ».
      **Compter les lignes d'un axe connu** (CDYLUP-004 → 11 pieds = 11
      lignes, PAS 12). Si 12 apparaissent, la ligne d'axe est revenue : stop.
- [ ] `/admin/taxes` (dashboard) : mêmes deux cases, et **le même total que
      l'écran de détail** pour la même période et les mêmes cases.
- [ ] Export PDF et Excel : l'en-tête annonce « maintenance=exclue » et/ou
      « lumipub=inclus » quand les cases sont cochées. Un document remis à
      la mairie doit dire sur quelle base il est bâti.
- [ ] URL enregistrée contenant l'ancien `include_maintenance=1` : elle ne
      plante pas, le paramètre est simplement sans effet.

### Point de méthode à trancher avec le MP
`type_panne` est un **champ texte libre** (« Ex: Éclairage défaillant, Bâche
déchirée… »). Impossible de s'y fier pour détecter un démantèlement :
« démantèlement », « demantelement », « démantelé » s'écriraient autrement
selon qui saisit.

**La date fiable est le `deleted_at` du panneau.** Donc : quand un mât est
démantelé, il faut **supprimer le panneau dans Panora** (suppression douce,
il reste en base et dans l'historique). Cette date devient le point d'arrêt
du prorata. Une simple maintenance intitulée « démantèlement » ne suffira
pas — le mât resterait taxé.

### Resté en l'état, volontairement
- `TaxController::showCommune()` exclut toujours la maintenance de son
  **instantané du jour** (nombre de panneaux, taux d'occupation). C'est un
  KPI de l'état courant, pas un calcul de période. À noter : le taux
  d'occupation s'en trouve calculé sur un dénominateur réduit.
- `TaxReportService` (Rapport Taxes + Dashboard KPI) garde ses propres
  règles. **La divergence de formule signalée le 12/11 reste ouverte** : il
  calcule `taux × nb_panneaux` sans la surface, là où le module Taxes fait
  `taux × m² × mois`. Les deux écrans ne peuvent pas s'accorder tant que ce
  n'est pas tranché.

### Tests automatisés
`tests/Unit/TaxesMaintenanceEtLumipubTest.php` — 10 tests, 29 assertions,
sans base de données.

---

## § 24 — « Sans bon de commande, pas de campagne » (2026-11-13)

### Le défaut corrigé

Quand le client cliquait « J'accepte » sur sa proposition,
`PropositionService::confirmer()` faisait **trois choses dans la même
transaction** : bloquer fermement les panneaux, annuler les options
concurrentes, et **créer la campagne**. Or le client n'avait pas forcément
envoyé son bon de commande — et sans BC, il ne doit pas y avoir de campagne.

### La règle validée par écrit

1. **Le client s'engage** (clic proposition, devis accepté, résa ferme
   saisie, renouvellement accepté) → la réservation **reste en option**,
   fenêtre BC de **30 jours**. Les panneaux ne sont **pas** bloqués
   fermement et les options concurrentes **ne sont pas** annulées.
2. **Relances** au commercial du dossier, **MP en copie**, à **J-15, J-7, J-2**.
3. Le **commercial** enregistre la réception du BC — **verbalement suffit**,
   la référence est un champ libre **facultatif**.
4. Le **MP** crée la campagne dédiée.
5. Sans BC à l'échéance, l'option **expire** et les panneaux repartent au
   catalogue.

Tout vit dans `app/Services/BonDeCommandeService.php` et
`config/bon_de_commande.php`. Aucune durée n'est écrite ailleurs.

### ⚠ Le piège désamorcé
`reservations:expire-old-options --days 7` tourne tous les jours à 01h20 et
annule les options de plus de 7 jours. **Sans correctif, elle aurait tué
tous les dossiers en attente de BC au 7ᵉ jour**, avant même la première
relance (J-15 sur une fenêtre de 30 jours). Elle ignore désormais les
dossiers portant une échéance BC.

### Déploiement
- [ ] `php artisan migrate` — ajoute les 7 colonnes `bc_*` sur `reservations`
      et **bascule les options déjà en cours** avec 30 jours à compter du
      jour de la migration (décision du 13/11 : sinon l'expiration des
      7 jours les tuait le lendemain). En production, **HAVAS AFRICA /
      RES-MSSWBY5Q** est concernée — vérifier qu'elle a bien une échéance.
- [ ] Vérifier le planificateur : `reservations:expire-bc` à 01h22 et
      `reservations:relancer-bc` à 09h05.
- [ ] `php artisan reservations:relancer-bc --dry-run` puis
      `php artisan reservations:expire-bc --dry-run` : les deux doivent
      tourner **sans rien modifier** et afficher ce qu'elles feraient.

### Parcours à vérifier
- [ ] **Client accepte une proposition** → la résa reste `en_attente` /
      `option`, bandeau « 📄 En attente du bon de commande » avec l'échéance
      et le compte à rebours. **Aucune campagne créée.**
- [ ] Le client **ne reçoit plus** le mail « votre campagne est lancée » à
      ce moment-là (il annonçait une campagne inexistante).
- [ ] Alerte au commercial : elle parle du **bon de commande**, pas de
      création de campagne.
- [ ] **Commercial** : bouton « 📥 BC reçu », avec le champ N° de BC laissé
      **vide** → doit marcher (confirmation verbale). Le MP reçoit l'alerte.
- [ ] **MP** : bouton « 🚀 Créer la campagne » → campagne créée, panneaux
      bloqués fermement, options concurrentes annulées, tâches de pose
      générées, et c'est **là** que part le mail « campagne lancée ».
- [ ] Un MP ne doit **pas** voir « BC reçu » ; un commercial ne doit **pas**
      voir « Créer la campagne ».
- [ ] **Résa ferme saisie à la main** : plus de campagne immédiate, message
      « En attente du bon de commande jusqu'au … ». Le nom de campagne saisi
      est conservé dans les **notes** du dossier.
- [ ] **Devis accepté** : même comportement (il passe par `ReservationService`).
- [ ] **Renouvellement** : un client qui répond « Je souhaite renouveler »
      déclenche une **option automatique** sur les panneaux de sa campagne,
      avec fenêtre BC. « Je souhaite ajuster » ne crée **rien** (le réseau
      change). Un envoi **de test** ne doit créer aucune réservation.
- [ ] **Deux dossiers sur le même panneau** : créer deux options qui se
      chevauchent, les faire accepter toutes les deux → le bandeau doit
      afficher « ⚠ 2 dossiers attendent un bon de commande sur ces
      panneaux ». Le premier BC enregistré emporte les panneaux ; la
      création de campagne annule l'autre.
- [ ] **Expiration** : forcer `bc_attendu_jusqu_au` dans le passé sur un
      dossier de test, lancer `reservations:expire-bc` → résa annulée,
      panneaux de retour au catalogue, alerte au commercial.
- [ ] **Relances** : placer l'échéance à J+15, J+7 puis J+2 et lancer
      `reservations:relancer-bc` → une relance à chaque palier, **une seule
      fois** (relancer la commande le même jour ne doit rien renvoyer).

### ⚠ Hypothèse à confirmer — période du renouvellement
Quand le client accepte un renouvellement, la nouvelle option reprend la
**durée** de la campagne source, à partir du **lendemain de sa fin**.
Ce n'était pas spécifié : c'est un défaut raisonnable, le MP ajuste à la
création de la campagne. **À faire trancher** si ce n'est pas la bonne règle.

### Resté en l'état
- `CampaignController` (création / duplication directe) et
  `CampaignImportController` (import PDF) créent toujours une campagne sans
  BC : ce sont des gestes admin délibérés, pas un engagement client.
- `PropositionService::confirmer()` garde tout son comportement d'origine ;
  seul un verrou « BC reçu » a été ajouté à son entrée. Aucun chemin ne peut
  créer une campagne sans bon de commande.

### Tests automatisés
`tests/Unit/BonDeCommandeTest.php` — 17 tests, 56 assertions, sans base de
données.

---

## § 25 — Surface taxable d'un pied d'axe : les faces (2026-11-13)

### Ce qui a été mesuré en production
Le format d'un axe porte la surface d'**une seule face**, pas celle du pied
entier. Sonde du 13/11 sur les 13 axes :

| Axe | Pieds | Faces annoncées | Format | Surface format |
|---|---|---|---|---|
| CDYLUP-001 … CDYLUP-008, CDYLUP-10, CDYPUB-09, TVILUP-001/002 | 2 à 11 | **3** | `3m²` | **3,00** |
| BK-002 (Adiaké-Assinie) | 12 | **2** | `6m²` | **6,00** |

Un pied Lumipub, c'est donc **3 faces × 3 m² = 9 m²**, et Panora n'en
comptait que **3**. ODP et TM étaient **divisées par 3** sur tout le parc
Lumipub.

### La règle appliquée
```
surface taxable = surface d'une face × nombre de faces du pied
```
Le nombre de faces vient du **nom de l'axe** (`(11P x 3F)`,
`(12Panneaux double face)`), lu par `AxeService::lireStructure()`.
**Pas** de `nombre_faces` : la dette technique du 11/11 a mesuré qu'il porte
deux significations (1 sur 320 panneaux, 4 sur les chevalets) — s'en servir
multiplierait par 4 la surface de chaque chevalet. Sur les 101 pieds créés
il vaut 1 partout.

⚠ **Conséquence** : un nom d'axe mal saisi fausse la surface taxable. Le
détail `3 m² × 3 faces` est donc affiché sur chaque ligne de taxe pour que
l'erreur se repère à l'œil.

### ⚠ Anomalie d'ordre de grandeur — NON RÉSOLUE
Avec les faces, l'ODP annuelle des **seuls Lumipub** passe de 14,7 M à
**43,3 M FCFA**. Or le fichier de la DG donne **13 776 000 pour tout le parc
d'Abidjan** (192 panneaux). Les Lumipub seuls feraient plus de **trois fois**
l'ODP déclarée de toute la ville.

La correction des faces est juste — elle est confirmée par les données. Il
reste donc une erreur **en amont**, et la piste la plus probable est la
question posée le 12/11 et restée sans réponse :

> **Le tarif ODP au m² (Cocody 5 000, Plateau 15 000, les autres 1 000) est-il
> par MOIS ou par AN ?** Le code applique du mensuel. En annuel, les Lumipub
> tomberaient à 3,6 M — cohérent avec les 13,7 M de la DG.

**Tant que ce point n'est pas tranché, l'écran de taxes ne peut pas servir à
contester une réclamation de mairie.**

### Ce qui limite le risque
Les pieds Lumipub sont **décochés par défaut** (cf. § 23) : cette correction
ne change **aucun total affiché** tant que la case « Lumipub · Inclure les
pieds » n'est pas cochée.

### À vérifier
- [ ] Écran de détail, case Lumipub **décochée** : le total ne bouge pas.
- [ ] Case **cochée** : chaque ligne de pied affiche `3 m² × 3 faces` sous
      la dimension, et la colonne m² indique **9**, pas 3.
- [ ] Un pied de CDYLUP-006 à Cocody sur 12 mois : ODP = 5 000 × 9 × 12 =
      **540 000** (c'était 180 000).
- [ ] Un panneau **ordinaire** (hors axe) : surface inchangée, aucune
      mention de faces.
- [ ] Un **chevalet** (`nombre_faces = 4`) : surface inchangée — on ne lit
      pas cette colonne.
- [ ] Fiche commune (totaux) et écran de détail doivent donner **le même
      montant** : les deux multiplient par les faces.

### ⚠ À trancher — BK-002
`Axe Carrefour Adiaké Assinie (12 Panneaux double face)`, format 6 m²,
2 faces. **Ce n'est pas un Lumipub.** Par la même logique il passe à 12 m²
par pied, soit 864 000 → **1 728 000** par an. La règle du 13/11 ne portait
que sur les Lumipub : **à confirmer** avant de considérer ce montant comme
acquis. Le code l'applique déjà (la règle est générique) — si BK-002 doit
être exclu, me le dire.

### Tests automatisés
`tests/Unit/LumipubFacesSurfaceTest.php` — 8 tests, 49 assertions, sans base
de données. Les 13 noms d'axes réels de production y sont figés : si l'un
cesse d'être analysé, le test tombe.

---

## § 26 — ODP des pieds : 2 mois au lieu de 12 (2026-11-13)

### Le défaut constaté à l'écran
Sur un exercice **annuel**, l'ODP d'un pied Lumipub se calculait
`1 000 × 9 m² × **2 mois**` au lieu de 12.

**Cause** : `Panel::booted()` remplit `odp_start_date` à la date du jour pour
tout panneau créé. Les 101 pieds nés du découpage des 11 et 13/11/2026 ont
donc une date de départ ODP **en novembre 2026** — alors que les mâts sont
plantés depuis des années : seule leur **ligne dans Panora** est récente.
Sur l'exercice 2026, l'ODP ne courait donc que novembre + décembre.

Le docblock de `Panel::booted()` avertissait précisément de ce cas :
> ⚠ Si un jour on importe un lot de panneaux HISTORIQUES, il faut passer
> `odp_start_date` explicitement, sinon ils hériteront de la date du jour.

Le découpage est exactement cet import.

### La règle
Un pied porte la **même** ancienneté que son axe — y compris `NULL`, qui
signifie « panneau préexistant, ODP due sur toute la période » (TX-10,
validé par la patronne). `forceFill` contourne `booted()`, qui n'agit qu'à
la création : c'est le seul moyen de reposer `NULL`.

### Correctif également apporté : la source du nombre de faces
Le § 25 lisait le **nom de l'axe** pour compter les faces. Il existe une
colonne dédiée, **`faces_par_pied`**, que `axes:decouper` écrit déjà sur
chaque pied et sur la ligne d'axe. Elle prime désormais ; le nom ne sert
plus que de repli. Le risque « un nom mal saisi fausse la taxe » disparaît.

### À faire en production, dans cet ordre
- [x] `php artisan axes:reparer-odp` → **simulation** lancée le 13/11.
      Résultat : **101 pieds sur 101** portaient `2026-11-11` (date du
      découpage) alors que **les 13 axes ont tous `NULL`**. Aucune
      exception, aucun cas partiel à arbitrer.
- [x] Dates attendues vérifiées : `NULL (préexistant)` partout — le cas
      normal pour un parc antérieur à Panora.
- [x] **`axes:reparer-odp --appliquer` exécuté en production le
      2026-11-13 → ✅ 101 pied(s) corrigé(s).**
      La commande n'a signalé **aucun** pied sans `faces_par_pied` : le
      nombre de faces vient donc bien de la colonne dédiée, pas d'une
      lecture du nom d'axe.
- [ ] Rouvrir l'écran de taxes, période **annuelle**, case Lumipub cochée :
      un pied doit maintenant compter **12 mois**, pas 2.
- [ ] La commande signale aussi les pieds sans `faces_par_pied` — s'il y en
      a, le calcul des faces retombe sur le nom de l'axe (ça marche, mais
      c'est moins sûr).

### ⚠ Rappel — l'anomalie du § 25 reste entière
Corriger les mois **multiplie encore** les montants : un pied Lumipub à
Cocody passe de 180 000 à 540 000 sur l'année. L'écart avec le fichier de la
DG (13,7 M pour tout Abidjan) s'en trouve aggravé, pas résolu.
**La question du tarif mensuel ou annuel reste la pièce manquante.**

### Tests automatisés
`tests/Unit/LumipubFacesSurfaceTest.php` — 12 tests, 56 assertions.

---

## § 27 — Faces : état réel du parc après les réparations (2026-11-13)

Tout ce qui suit a été **exécuté en production le 13/11**.

| Quoi | Combien | État |
|---|---|---|
| Ancienneté ODP des pieds | 101 | ✅ alignée sur l'axe (`NULL` = préexistant) |
| `nombre_faces` ↔ `faces_par_pied` | 114 (101 pieds + 13 axes) | ✅ convergées |
| Lumipub autonome `MRYLUP-001` | 1 | ✅ ses 3 faces comptent pour la taxe |
| Chevalets | 27 | ⛔ **volontairement hors du calcul** |

### Ce qui reste à trancher — les chevalets
Les 27 chevalets portent `nombre_faces = 4`, et leur nom le confirme :
`Chevalet 4×6m²`, `4×20m²`, `4×2m²`, `4×15m²` — soit 4 faces, le format
portant la surface d'**une** face. La règle appliquée aux Lumipub
(`surface taxable = format × faces`) leur vaudrait donc ×4.

**Aucun traitement automatique ne les vise**, ni l'inventaire ni
`axes:reparer-odp`, et un test échoue si quelqu'un les fait entrer dans le
calcul. La décision peut attendre sans bloquer les exports.

→ **Avant de trancher**, demander le chiffrage commune par commune (ODP et
TM, avant/après). Vu les écarts constatés aujourd'hui, ne pas appliquer à
l'aveugle.

### Rectification d'une analyse erronée du matin
J'avais écrit (§ 25) que `nombre_faces` portait deux significations
contradictoires, en m'appuyant sur `docs/TECHNICAL_DEBT.md`. **C'est faux.**
Le champ porte partout le même sens : le nombre de faces du support. La
dette visait un **autre usage** — s'en servir pour deviner que deux lignes
Panora sont le même mât physique — où il est effectivement inutilisable.
Cette confusion a fait restreindre la règle aux seuls axes, d'où le
Lumipub autonome oublié.

### ⚠ Toujours ouvert — l'anomalie d'ordre de grandeur (§ 25)
Les quatre correctifs du jour vont tous dans le sens de la hausse. La
question du **tarif au m², mensuel ou annuel**, reste la pièce manquante
pour confronter Panora aux réclamations des mairies.

---

## § 28 — Suivi des mairies : arriérés, règlements, solde (2026-11-16)

La fiche de la direction « GESTION DES TAXES (ODP ET TM) » tenue par Panora :
menu **Suivi des mairies**. Règles validées par écrit le 16/11 :
- les panneaux et les tarifs de Panora font foi ;
- TM de toutes les campagnes, chaque mois dans le trimestre où il commence ;
- arriérés reportés d'un exercice à l'autre ;
- un règlement = une ligne jamais effacée, annulée avec motif ;
- prévu hors solde ;
- **2026 = exercice de reprise** : le dû est ce que chaque mairie a réclamé,
  saisi par les comptables, et le calcul de Panora sert de contrôle ;
  à partir de 2027, le dû est le calcul de Panora.

### À faire en production, dans cet ordre
- [x] Migrations (2 tables : `taxe_reglements`, `taxe_reprises`) : jouées
      au déploiement (la commande de reprise a pu écrire dans `taxe_reglements`).
- [x] Reprise des versements déjà saisis — **faite en production le 16/11** :
      5 versements 2026, 805 000 FCFA, tous issus de `commune_tax_payments`
      (Abengourou 296 000 · Odienné 288 000 + 51 000 · Bingerville 100 000 ·
      Bassam 70 000). Aucune ligne payée dans l'ancienne table `taxes`.
      Rejouable sans doublon.
- [x] `storage/app/private` est sur le volume persistant (vérifié le 16/11 :
      volume `…-diffusion-dispo` monté sur `/app/storage/app/private`). Les
      quittances y sont rangées, `taxes/quittances/<exercice>/<mairie>/`.

### Écrans à contrôler
- [ ] Menu « Suivi des mairies » visible pour admin, MP et comptable ;
      onglets Abidjan et banlieue / Intérieur / Toutes.
- [ ] **2026** : bandeau « N mairies sans montant réclamé saisi ». Ouvrir
      Cocody, saisir ce qu'elle a réclamé (fiche DG : arriérés 47 040 000,
      TM 1 342 000 / 1 233 000 / 2 488 000) → total dû 52 103 000.
      Le calcul Panora s'affiche à droite avec l'écart.
- [ ] (18/11) Sous chaque champ de montant (réclamé et nouveau règlement), le
      montant s'écrit en clair pendant la frappe : `1342000` → « 1 342 000 FCFA ».
      À droite, la ligne « Réclamé 2026 : ODP + TM (sans les arriérés) » montre
      son détail « ODP 0 + TM 5 063 000 ». La ligne « Écart : réclamé − calcul
      Panora » montre l'opération (5 063 000 − 18 254 000), l'écart ODP et l'écart
      TM (ODP −18 180 000 · TM +4 989 000 sur Cocody en dev) et son sens
      (« La mairie réclame 13 191 000 de moins que le calcul »).
- [ ] Saisir un règlement avec une quittance (PDF ou photo) → ligne ajoutée,
      lien « 📎 Voir » qui ouvre le fichier ; en saisir un second sur le même
      exercice → **deux lignes**, rien d'écrasé.
- [ ] Annuler un règlement : le motif est obligatoire ; la ligne reste,
      barrée, et sort du total payé et du solde.
- [ ] **2027** : arriérés = solde 2026 de la mairie ; ODP et TM par
      trimestre calculées ; colonne « À venir » = campagnes planifiées.
- [ ] Relevé PDF d'une mairie, PDF de l'exercice, Excel (feuilles ABIDJAN,
      INTERIEUR, REGLEMENTS — même présentation que le fichier de la DG).
- [ ] Page **Taxes** : « Déjà payé » et « Solde restant » = ceux du suivi
      pour la même année ; le bouton « 💰 Règlements » ouvre la fiche du
      suivi ; la fenêtre « Enregistrer paiement » n'existe plus.
- [ ] **Libellés** (16/11) : « À jour », « Payé en partie », « Rien payé »,
      « Payé en trop », « Rien à payer », « Réclamé à saisir » (2026, rien de
      réclamé saisi), « Dû à ce jour », « À venir ». Survoler un statut affiche
      son explication.
- [ ] Ancienne adresse `/admin/taxes/commune/<id>` → redirige vers la fiche du suivi.
- [ ] Page Taxes, colonne TM : elle peut **augmenter** pour un panneau loué
      à plusieurs clients dans l'année (avant, seule la dernière campagne
      comptait) et changer en mensuel (même règle de mois partout).
      Comparer une commune avec le détail par panneau : mêmes montants.
- [ ] Rapport Taxes : matrice recalculée avec les m² (avant : tarif ×
      nombre de panneaux) — chiffres différents d'avant, attendus ; bloc
      « Dû aux mairies vs reversé » = chiffres du suivi.
- [ ] Alerte de trimestre : `php artisan taxes:notify-due-soon --forcer`
      → mail aux comptables et admins, mairies avec un montant « dû à ce jour ». Sans
      `--forcer`, elle ne part que le premier jour ouvré (lundi-vendredi) du
      trimestre — prochain envoi : vendredi 1er janvier 2027, férié, mais le
      mail part quand même.
- [ ] Synthèse mensuelle du 1er décembre : lignes « Taxes du mois (calcul
      Panora) », « Règlements versés », « Reste à payer aux mairies ».

### Tarifs communaux — corrigé le 2026-11-18
- [ ] Réglages → Tarifs ODP / TM : « Historique » s'ouvre (avant : « Erreur de
      chargement », l'adresse oubliait « settings ») ; « Programmer » enregistre.
- [ ] Une commune sans historique (« — » dans Dernier changement, ex.
      ADIAKE-ASSINIE) modifiée au crayon : l'historique montre deux lignes,
      l'ancien tarif jusqu'à hier, le nouveau dès aujourd'hui. Avant, la
      modification s'appliquait aussi au passé.
- [ ] **MP** (décision du 18/11) : menu « Tarifs communes » visible, Historique et
      Programmer fonctionnent ; le crayon de Réglages → Communes (tarif courant)
      reste réservé à l'admin. Commercial : toujours pas d'accès.
- Règle pour les ajustements : un tarif valable à partir de 2027 →
  « Programmer » au 01/01/2027 ; une correction qui vaut dès maintenant →
  crayon (Réglages → Communes). Les factures déjà émises ne bougent jamais.

### Pour les comptables — avant le 31/12/2026
- [ ] Pour chaque mairie : ce qu'elle a réclamé pour 2026 (arriérés compris),
      depuis l'avis ou la fiche de la DG.
- [ ] Tous les versements 2026 avec leur quittance. Sans eux, le solde 2026
      — donc l'arriéré 2027 — sera faux.

### À confirmer
- [x] **Pieds Lumipub** — tranché le 16/11 : « décoché » sur tous les écrans.
      Le suivi, le rapport Taxes et la synthèse mensuelle les laissent hors du
      dû, comme la page Taxes par défaut ; leur ODP / TM se gère à part
      (réglage unique `taxes.inclure_lumipub`, config/taxes.php). À contrôler :
      page Taxes en annuel, case décochée = mêmes ODP / TM que le suivi 2027.
- [x] **Ordre de grandeur** — accepté le 16/11 : avec les tarifs actuels, 2027 sera calculé
      autour de 160 M Lumipub compris (relevé du 13/11 : ODP ≈ 140 M dont
      Cocody 60 M et Plateau 32 M), soit de l'ordre de 115 M sans les pieds
      Lumipub (≈ 43 M d'ODP, § 25) — contre ≈ 36 M réclamés en 2025. Si un tarif est faux, il se corrige dans la
      commune avec une date d'effet (`/admin/settings/communes/tariffs`) et
      tout se recalcule.

---

## § 29 — Panneau sorti du parc, devis de renouvellement, services en un clic *(2026-11-16)*

### Ce qui a changé

**1. Un panneau démantelé ne casse plus les pages qui le citent.**
`/admin/piges` renvoyait une 500 (`Missing required parameter for
[Route: admin.panels.show]`). `Panel` est en suppression douce : démanteler
un panneau laissait la pige intacte mais rendait `$pige->panel` nul.
Les relations d'historique (pige, pose, ligne de facture, ligne de devis,
photo) récupèrent désormais le panneau même supprimé, comme `Maintenance`
le faisait déjà. La fiche du panneau reste consultable, avec un bandeau
« Panneau sorti du parc » et sans les actions PDF / Modifier / Disponibilités.

**2. Devis généré depuis un dossier de renouvellement.**
Bouton « 🧾 Générer le devis » sur la fiche, dès que le dossier est
« Renouveler », « Ajuster » ou « Confirmé ». Le formulaire s'ouvre
pré-rempli : client, interlocuteur, campagne, période suivante, panneaux.
L'option ouverte automatiquement pour ce renouvellement **reste vivante**
tant que le client n'a pas accepté (un devis ne bloque aucun panneau) ;
elle est **annulée à l'acceptation** du devis.

**3. Services annexes proposés en un clic** — FRAIS DE POSE, IMPRESSION —
sur le devis **et** la facture FNE, depuis `config/billing.php`.

### À vérifier sur la vraie base

#### Le panneau démantelé
- [ ] `/admin/piges` s'ouvre sans erreur (c'était la 500 d'origine).
- [ ] La carte d'une pige dont le panneau est sorti du parc affiche bien
      sa référence, et le lien ouvre la fiche avec le bandeau gris daté.
- [ ] Sur cette fiche : pas de bouton Export PDF / Modifier / Disponibilités.
- [ ] `/admin/poses/{id}` et `/admin/sla` pour un panneau démantelé.
- [ ] Export PDF des piges (`rapport-piges`) avec un panneau démantelé dans
      la sélection.
- [ ] Une facture et un devis qui citent un panneau démantelé restent
      lisibles, référence comprise.

#### Le devis de renouvellement
- [ ] Ouvrir un dossier « Renouveler » ou « Confirmé » → le bouton
      « 🧾 Générer le devis » est présent.
- [ ] Le formulaire s'ouvre avec : le bon client, son interlocuteur, la
      campagne, les dates de la période suivante, et **toutes** les lignes
      de panneaux (les pieds d'un axe regroupés en une seule ligne).
- [ ] **La durée des lignes correspond à la période** : une période d'un an
      doit donner 12 mois, pas 1. C'était faux avant pour toute
      pré-sélection de panneaux, y compris depuis Disponibilités.
- [ ] Le bandeau bleu « Devis de renouvellement » annonce la référence de
      l'option et la date limite du BC.
- [ ] Enregistrer le devis → l'option est toujours **en attente de BC**,
      les panneaux toujours tenus (c'est voulu).
- [ ] Faire accepter le devis → une réservation ferme est créée **et**
      l'option passe en « Annulé » avec la mention du devis dans ses notes.
      Il ne reste qu'un dossier vivant sur ces panneaux.
- [ ] Les statuts des panneaux sont recalculés après cette bascule.

#### Les services en un clic
- [ ] Devis : les boutons « + FRAIS DE POSE » et « + IMPRESSION » ajoutent
      une ligne avec le libellé rempli, le curseur sur le prix.
- [ ] Un second clic sur le même bouton **n'ajoute pas** de doublon : il
      surligne la ligne existante.
- [ ] Même chose sur la facture FNE.
- [ ] Le PDF du devis et celui de la facture reprennent ces lignes.

### À trancher — reste ouvert

- [ ] **BC et devis accepté.** La règle validée le 13/11 dit que le flux
      « sans BC, pas de campagne » s'applique aussi aux devis acceptés.
      Ce n'est pas le cas : `QuoteConverter` crée une réservation
      **confirmée** sans ouvrir de fenêtre BC, donc la campagne peut être
      créée tout de suite. Lui ouvrir une fenêtre BC ne suffit pas : les
      relances J-15/J-7/J-2 et l'expiration ne visent que les réservations
      `en_attente`. Il faut décider si la réservation issue d'un devis
      accepté reste **en option** jusqu'au BC (cohérent avec le reste, mais
      elle ne bloque alors plus fermement les panneaux) ou si elle reste
      **ferme** avec un simple verrou sur la création de campagne.

- [ ] **Campagnes et réservations sur un panneau démantelé.**
      `CampaignPanel::panel()` et `ReservationPanel::panel()` n'ont
      volontairement pas été élargis : un panneau démantelé disparaît donc
      silencieusement de l'écran de sa campagne, alors qu'il a pu être
      facturé. Les élargir changerait ce que voient les écrans de
      disponibilité et de facturation — à décider avant de le faire.

---

## § 30 — Emplacements des clients annuels hors des disponibilités *(2026-11-16)*

### Ce qui a changé

Une recherche de disponibilités sur 2027 faisait ressortir **tout le parc** :
les campagnes 2026 se terminent avant, donc tous les panneaux semblaient
libres. Les emplacements des clients fidèles partaient ainsi au catalogue,
et à la concurrence, avant même qu'on les ait appelés.

**La règle validée le 16/11 :** un panneau tenu par une campagne de 12 mois
ou plus (`Campaign::estAnnuelle`), **en cours aujourd'hui ou se terminant
plus tard**, n'est plus proposé. Dès que cette campagne se termine sans être
renouvelée, le panneau revient aussitôt — on ne masque jamais du stock
réellement libre.

- **Écran Disponibilités** : masqués **par défaut**, avec une pastille
  « 🔒 N annuels masqués » cliquable. La case « Inclure leurs emplacements »
  les réintègre.
- **Catalogue envoyé aux clients (Brevo)** : toujours écartés, sans case à
  cocher — l'envoi est automatique, personne n'arbitre au départ.
- **Rien n'est bloqué** : c'est une règle de ce qu'on *propose*. Un panneau
  annuel reste réservable si on le choisit délibérément.
- Cumulé avec la retenue des **renouvellements confirmés** (§ du 11/11),
  qui, elle, bloque pendant 30 jours.

### À vérifier sur la vraie base

- [ ] Disponibilités sans période : le compteur « 🔒 N annuels masqués »
      apparaît et N correspond au nombre de panneaux portés par une
      campagne de 12 mois ou plus encore en cours.
- [ ] Tirer une période sur **2027** : les panneaux des clients annuels
      2026 n'apparaissent plus. C'était le défaut d'origine.
- [ ] Cocher « Inclure leurs emplacements » → ils réapparaissent, une
      étiquette « 🔒 Clients annuels inclus » s'affiche, le bouton
      « Réinitialiser » devient visible.
- [ ] Cliquer sur la pastille « N annuels masqués » → même effet que
      cocher la case.
- [ ] « Réinitialiser » → les annuels sont de nouveau masqués.
- [ ] « Tout sélectionner » puis export PDF liste / PDF images / Excel :
      **aucun panneau annuel ne doit figurer dans l'export** quand la case
      est décochée, et tous doivent y figurer quand elle est cochée.
- [ ] Pagination : le total annoncé correspond bien au nombre de panneaux
      réellement parcourables (l'exclusion a lieu avant la pagination).
- [ ] **Axes Lumipub** : si un axe entier est tenu par une campagne
      annuelle, ses pieds doivent disparaître aussi — pas seulement la
      ligne de l'axe.
- [ ] Catalogue client : `php artisan dispos:diffuser --preparer` puis
      ouvrir le PDF → aucun emplacement de client annuel.
- [ ] Une campagne annuelle **terminée ou annulée** ne doit plus masquer
      son panneau : il revient dans les disponibilités.

### Points d'attention

- [ ] **Volume masqué.** Si la pastille annonce un nombre très élevé
      (la majorité du parc), c'est que beaucoup de campagnes dépassent
      12 mois sans être de vrais contrats annuels. Dans ce cas, revoir
      le seuil plutôt que de décocher la case en permanence.
- [ ] Le sélecteur de panneaux du **devis** n'applique pas cette règle
      (décision du 16/11) : le commercial y cherche volontairement un
      panneau précis.

---

## § 31 — PDF devis aligné sur le modèle officiel, quantité sur les services *(2026-11-16)*

Référence : `docs/devis/DEVIS HAVAS 05 PANNEAUX 12M².pdf` (non versionné).

### Ce qui a changé

**1. Quantité sur les services annexes.** Le modèle facture
« 30 × 20 000 = 600 000 ». `prix_ht` devient le prix **unitaire**, le montant
de ligne vaut prix × quantité. Défaut **1** : aucun devis ni aucune facture
déjà émis ne change de total. Posé sur `quote_services` **et**
`invoice_services` — un devis accepté devient une facture, les deux doivent
compter pareil.

**2. Trois défauts de montant, trouvés en comparant au modèle :**
- Le PDF annonçait « Total Services Additionnels » en **HT** alors que le
  MONTANT NET additionne les services **TTC** : les chiffres du document ne
  se recoupaient pas.
- La TVA des services n'apparaissait nulle part sur le PDF.
- L'écran de détail du devis appliquait la TVA à **tous** les services, en
  ignorant l'interrupteur `tva_applicable` du 30/10. Il pouvait donc
  annoncer un montant différent du PDF et de la facture.

**3. Habillage.** Le devis reprend la palette du modèle (bleu nuit `#1C2E3D`,
bleu moyen `#327DAF`, or `#FAAF00`), extraite des opérateurs de couleur du
PDF de référence. La charte du 01/10 (rouge/noir/jaune) reste en vigueur sur
les autres PDF. Les **polices** restent celles de la charte.

**4. Structure.** Colonne désignation réduite à l'emplacement seul ; ligne
« Total HT » en doublon supprimée ; bande de sous-total brut ajoutée ; page
de signatures séparée supprimée (les cadres suivent le bandeau « Bon pour
accord »).

### À vérifier sur la vraie base

#### Les montants — le plus important
- [ ] Créer un devis avec un service **quantité 30 à 20 000 FCFA** :
      le formulaire doit afficher 600 000 HT, et le PDF
      `30 | 20 000 FCFA | 600 000 FCFA` puis `TVA (18%) 108 000` puis
      `Total Services Additionnels 708 000 FCFA`.
- [ ] **Le MONTANT NET TOTAL À PAYER doit tomber juste** : TTC panneaux +
      taxes publicitaires + services TTC.
- [ ] Un service avec l'interrupteur sur **HT** : il ne doit porter aucune
      TVA, ni sur l'écran de détail, ni sur le PDF, ni sur la facture.
- [ ] **Ouvrir un devis et une facture ANTÉRIEURS au 16/11** : leurs totaux
      ne doivent pas avoir bougé d'un franc (quantité = 1 par défaut).
- [ ] Accepter un devis avec services en quantité → la facture qui en
      découle doit porter les mêmes montants.
- [ ] Facture FNE : la carte service affiche Qté / PU HT, et le
      récapitulatif de droite suit la saisie.

#### Le PDF
- [ ] Comparer côte à côte avec `DEVIS HAVAS 05 PANNEAUX 12M².pdf`.
- [ ] La somme en toutes lettres doit correspondre au total. *(Le PDF de
      référence se trompe sur ce point : il annonce « six millions… » pour
      1 634 280 FCFA. Panora écrit la bonne somme — c'est voulu.)*
- [ ] La durée de validité doit être celle saisie sur le devis. *(La
      référence écrit « quinze (7) jours », contradictoire. Non reproduit.)*
- [ ] Les quatre chemins donnent le même PDF : bouton admin, espace client,
      lien public, pièce jointe du mail d'envoi.

### Écarts assumés avec le modèle — à trancher

- [ ] **Le PDF fait 3 pages, le modèle 2.** Les conditions générales de
      Panora sont plus longues : elles proposent une **Option B** de
      paiement (70 % à la signature, 30 % à J-15) que le modèle n'a pas,
      et deux cases sur la page des modalités au lieu d'une. Supprimer
      l'Option B ramènerait très probablement le devis à 2 pages — mais
      c'est une condition commerciale, pas une question de mise en page :
      à confirmer avant de la retirer.

- [ ] **Les coordonnées de l'émetteur diffèrent du modèle.** Elles viennent
      de la configuration (`config/billing.php` / `.env`), pas du gabarit :
      | Modèle | Panora aujourd'hui |
      |---|---|
      | CIBLE SARL | CIBLE |
      | Rue des Majorettes, Biétry — Abidjan | 08 BP 687 ABIDJAN (VILLE) |
      | Tél. +225 27 22 20 80 08 · Mob. 07 98 49 66 74 | 27 22 20 80 08 |
      | CC : N°018 46 70 J | N°0184670J |
      | finance@ \| commercial@cible-ci.com | commercial@cible-ci.com |
      À corriger dans le `.env` de production si le modèle fait foi.

- [ ] **Cachet et signature scannés** de la Directrice Commerciale : absents
      (décision du 16/11). Fournir l'image pour les intégrer — en sachant
      que tout devis sortirait alors déjà signé, brouillon compris.

---

## § 32 — Changer le client d'une affaire (campagne + réservation) *(2026-11-16)*

Constat : la campagne 122 était au nom de 2A CONSULTING au lieu de 2A
ÉDITIONS, et rien ne permettait de corriger. La campagne refusait un client
différent de sa réservation, et la réservation refusait de changer dès
qu'elle avait sa campagne (deux verrous du 29/10). Correction urgente de la
campagne 122 faite par commande en production le 16/11.

Désormais, changer le client sur la fiche **campagne** OU sur la fiche
**réservation** (« Modifier ») change celui de toute l'affaire
(`ChangementClientAffaire`). Règles validées par écrit le 16/11 :
- une facture validée ou envoyée empêche le changement (il faut un avoir) ;
  les factures en brouillon suivent ;
- les relances de fin de campagne, le renouvellement sans réponse et
  l'enquête pas encore envoyée suivent ; ce qui est déjà parti reste ;
- le devis converti en cette réservation et la proposition suivent ;
- les panneaux des régies externes de la campagne suivent ;
- admin et Media Planner.

Le nom « Campagne <ancien client> » devient « Campagne <nouveau client> »
(sauf s'il est déjà pris chez le nouveau client). Aucun mail ne part.

### À vérifier
- [ ] Campagne 122 : client 2A ÉDITIONS, réservation RES-ISKWZZ1R aussi ;
      choisir un interlocuteur de 2A ÉDITIONS (Modifier la campagne).
- [ ] Sur une affaire de test : changer le client depuis la campagne →
      la réservation suit ; puis depuis la réservation (si elle est encore
      modifiable) → la campagne suit.
- [ ] Une affaire avec une facture validée : le changement est refusé,
      avec la référence de la facture dans le message.
- [ ] Espace client : la campagne apparaît chez le nouveau client et
      disparaît chez l'ancien.
- [ ] L'icône ⓘ à côté du champ Client explique ce qui suit.

---

## § 33 — Historique des panneaux d'une campagne *(2026-11-16)*

### Ce qui a changé

Demande : « une campagne commence avec 10 panneaux et se termine avec 6 ;
plus tard on veut savoir quel panneau a été retiré, à quelle période, par
qui et pour quelle raison ».

Avant, rien n'était requêtable : un `Log::info` dans des fichiers qui
tournent, une alerte, et **aucun motif demandé**.

**Nouvelle table `campaign_panel_mouvements`** — une ligne par entrée et
par sortie : date et heure, auteur, sens, motif, note, origine du
mouvement. Ni modifiée ni supprimée : c'est un journal, l'état courant
reste dans `campaign_panels`.

**Source unique :** `App\Services\Campagnes\MouvementsPanneaux`. Tous les
chemins y passent, y compris ceux qui touchaient la table directement :

| Chemin | Motif posé |
|---|---|
| Retrait manuel d'un panneau | choisi par le MP, **obligatoire** |
| Retrait d'un panneau de régie | choisi par le MP, **obligatoire** |
| Ajout de panneaux en cours de campagne | « Ajouté en cours de campagne » |
| Création depuis une réservation | « Panneau initial de la campagne » |
| Import de campagne | « Panneau initial », source *import* |
| Résolution de conflit | « Conflit de réservation résolu » |
| Annulation de campagne | « Campagne annulée » |

**Le motif est obligatoire** et choisi dans une liste fermée (demande du
client, panneau démantelé, maintenance, remplacé, négociation, erreur de
saisie, autre), plus une note libre facultative. Un motif tapé au clavier
s'écrit de dix façons et ne se compte jamais.

La **référence du panneau est figée** à l'écriture : un panneau démantelé
ou renommé plus tard ne rend pas l'historique illisible.

### À vérifier sur la vraie base

- [ ] Ouvrir une campagne, retirer un panneau : la fenêtre **exige** un
      motif, le retrait est refusé sans.
- [ ] Le bloc « 🧾 Historique des panneaux » apparaît sous la fiche, avec
      la date, l'heure, la référence, le sens, le motif, la note et l'auteur.
- [ ] Retirer un second panneau : le motif du précédent **ne doit pas**
      rester sélectionné dans la fenêtre.
- [ ] Ajouter un panneau en cours de campagne → une ligne « Ajouté ».
- [ ] Créer une campagne depuis une réservation → une ligne « Panneau
      initial de la campagne » par panneau. C'est ce qui permet de dire
      « elle a démarré avec 10 ».
- [ ] Retirer un **panneau de régie partenaire** : même exigence de motif.
- [ ] Résoudre un conflit depuis `/admin/conflits` en retirant le panneau
      d'une campagne → ligne « Conflit de réservation résolu », auteur
      renseigné. **C'était le trou principal** : cette page supprimait la
      ligne sans laisser de trace.
- [ ] Annuler une campagne → une ligne « Campagne annulée » par panneau.
- [ ] Retirer un panneau puis le démanteler depuis l'inventaire :
      l'historique doit **continuer d'afficher sa référence**.
- [ ] Importer une campagne → lignes « Panneau initial », origine import.

### Le défaut du 16/11 (corrigé le jour même)

Les tout premiers mouvements enregistrés sont sortis **sans identifiant de
panneau** : l'opérateur `+` sur deux tableaux ne remplace pas une clé déjà
présente à gauche, contrairement à `array_merge()`. La référence était bien
enregistrée, mais ni le lien vers la fiche ni la distinction parc / régie.
À l'écran, **tous** les mouvements portaient le badge « 🤝 régie ».

- [ ] Après déploiement : `php artisan campagnes:reparer-mouvements`
      (simulation), puis `--appliquer`. Elle retrouve le panneau par sa
      référence et rattache les lignes. Une référence ambiguë ou
      introuvable est signalée et laissée en l'état.
- [ ] Vérifier ensuite qu'un panneau du parc affiche bien un **lien
      cliquable** et **pas** le badge « régie ».

### Limite à connaître

- [ ] **L'historique démarre le jour de la mise en production.** Les
      retraits déjà effectués ne sont reconstituables nulle part : ils
      n'ont jamais été enregistrés ailleurs que dans les fichiers de log,
      qui tournent. Les campagnes en cours afficheront donc un historique
      partiel — seulement ce qui bouge à partir de maintenant.

### Piste pour plus tard

- [ ] Un rapport transversal « panneaux retirés » (toutes campagnes, par
      motif, par période, par client) deviendra possible une fois quelques
      mois de données accumulés. La table est indexée pour ça
      (`campaign_id + created_at`, et `panel_id` pour « dans quelles
      campagnes ce panneau est-il passé ? »).

---

## § 34 — Annuler la validation d'une pige *(2026-11-17)*

### Le blocage

Une pige validée par erreur était un cul-de-sac complet. Quatre portes,
toutes fermées par la même règle, **pour tout le monde** — admin et Media
Planner compris, qui avaient pourtant les routes :

| Action tentée | Réponse de Panora |
|---|---|
| Supprimer | « Impossible de supprimer une pige déjà vérifiée. » |
| Rejeter | « Impossible de rejeter une pige déjà vérifiée. » |
| Changer le statut depuis « Modifier » | champ verrouillé dès que la pige est vérifiée |

Conséquence terrain : la tâche de pose restait « terminée » et
**disparaissait de la liste du technicien**. Personne ne pouvait la faire
revenir. Cas constaté le 17/11 sur ADJ-005B (campagne BIDC, Adjamé).

### Ce qui a changé

Nouveau bouton **« Annuler la validation »** sur la carte d'une pige
validée (`/admin/piges`). Il demande un **motif obligatoire**, puis :

- la pige repasse en **rejetée** avec ce motif ;
- la tâche de pose revient de « terminée » à « en cours » (progression
  redescendue à 60 %, date de fin effacée) → **elle réapparaît dans la
  liste du technicien** ;
- le technicien est prévenu : WhatsApp s'il a un numéro, plus une
  notification dans son espace ;
- le **GPS du panneau est recalculé** sans cette pige — il avait pu être
  établi à partir d'elle ;
- une alerte prévient l'équipe, et le journal garde la trace en
  *warning*.

Ouvert à **l'admin et au Media Planner** : c'est le MP qui valide au
quotidien, donc lui qui s'aperçoit de l'erreur le premier.

La réouverture de la pose est désormais **centralisée** : le rejet
ordinaire et l'annulation passent par la même méthode. Deux copies
auraient fini par diverger — l'une rouvrant la pose, l'autre non.

### À vérifier sur la vraie base

- [ ] **ADJ-005B (BIDC)** : ouvrir `/admin/piges`, retrouver la pige
      validée par ABDOUL AZIZ KOFFI le 17/11, cliquer « Annuler la
      validation », saisir un motif.
- [ ] La tâche réapparaît dans la liste de **Ignace** sur l'espace
      technicien.
- [ ] Ignace reçoit le motif (WhatsApp si numéro renseigné, et la
      notification dans l'espace tech).
- [ ] Le refus sans motif est bloqué.
- [ ] Un Media Planner y a accès ; un commercial, non.
- [ ] Sur une pige **en attente**, le bouton n'apparaît pas (c'est le
      rejet ordinaire qui s'applique).
- [ ] Le compteur « vérifiées » de l'écran baisse de 1, « rejetées »
      monte de 1.
- [ ] Vérifier que le GPS du panneau reste cohérent après l'annulation.

### Limite à connaître

- [ ] **L'e-mail envoyé au client à la validation ne se reprend pas.**
      Valider une pige déclenche un avis au client (`AvisPigeValidee`) —
      pour un axe, quand son dernier pied est validé. BIDC a donc
      probablement déjà reçu la photo d'ADJ-005B. La fenêtre
      d'annulation le dit avant de cliquer ; le motif saisi sert de trace
      pour expliquer l'écart au client si la question revient.

## § 35 — Baromètre clients : envoi du questionnaire par Panora *(2026-11-17)*

### Ce qui a changé

Menu Commercial → **Baromètre clients** (admins + `BAROMETRE_ACCES`).
Panora calcule à qui envoyer (un mail par entreprise, règle validée le
17/11), envoie l'invitation par Brevo avec un **lien personnel** qui
expire seul à la date limite, apprend du formulaire Google qui a répondu,
et ne relance que les autres. Un clic ne fait que **demander** l'envoi :
la tâche `barometre:envoyer` (chaque minute) l'exécute.

Sécurité : lien 256 bits, expiration à 23:59:59 le jour limite, coupé si
le client est retiré, page neutre (« questionnaire clos », jamais la
raison interne) ; jeton Google dans l'en-tête, 404 sinon ; mode test par
défaut ; nombre affiché = nombre envoyé (sinon refus) ; jamais deux
invitations à une même entreprise ni à une même adresse ; formulaire
Google fermé tout seul à la date limite.

### Réglages

**Sur le serveur (Coolify)**, trois seulement :

```
BAROMETRE_MODE=test                 # production le jour de l'envoi (prod seulement)
BAROMETRE_JETON_GOOGLE=<php -r "echo bin2hex(random_bytes(32));">
BAROMETRE_ACCES=<adresse du compte Panora de Josiane>
```

**À l'écran** (⚙ Réglages, refonte du 17/11 après le premier essai) :
le lien prérempli du questionnaire, la date limite, la signature et
l'adresse qui reçoit les réponses. Lien prérempli : Google Forms → ⋮ →
« Obtenir le lien prérempli », taper un nom dans « Votre structure »,
« Obtenir le lien », le coller. Panora n'accepte qu'un lien
docs.google.com/forms. Valeurs décidées le 17/11 : signature
**Josiane SAY**, Commerciale 360° & Customer Experience Officer, CIBLE CI ;
le mail part de l'adresse Brevo (commercial@) sous le nom « Josiane SAY –
CIBLE CI », et les réponses arrivent à commercial@, qui est l'adresse de
Josiane (17/11) : le champ « réponses » de ⚙ Réglages reste vide.

### À faire, dans l'ordre

- [ ] Mise en ligne (les migrations `barometre_*` partent au démarrage du conteneur)
- [ ] Dev : `BAROMETRE_ACCES` = adresse du COMPTE PANORA de Josiane ; l'écran s'ouvre pour
      un admin et pour Josiane, pas pour un autre commercial
- [ ] Dev : ⚙ Réglages → coller le lien prérempli et la signature (réponses : vide
      = commercial@) → la case « Questionnaire » passe au vert
- [ ] Dev : « Envoyer un test » (ADDOHA) → mail reçu, objet et texte de la
      direction, signé Josiane, « Répondre » vise commercial@,
      bouton → questionnaire au nom « TEST — ADDOHA ».
      **Ne pas valider le questionnaire** depuis un test (sinon : réponse
      de test, non comptée, mais visible dans la feuille Google)
- [x] Prod : mêmes réglages à l'écran (l'écran de dev ne règle pas la prod)
      *(fait le 2026-11-18 : questionnaire relié, expéditeur « Josiane SAY – CIBLE CI »,
      réponses à commercial@ ; `BAROMETRE_ACCES` posé en prod pour Josiane)*
- [x] Prod : exclusions (les 9 régies, G2I et les 4 nouveaux clients) :
      ```
      php artisan barometre:exclure "UNFOLD" "CORAL MEDIA AFRICA" "JCDECAUX" "JOSHO GROUP" "LEDCOM" "LIGHT MEDIA" "PUB RÉGIE" "RÉGIE MEDIA CENTER" "SN PUBLISTAR" --motif="Régie concurrente"
      php artisan barometre:exclure "G2I" "BLUE LIONS" "PRODIGE COM" "SDTM" "HAVAS AFRICA" --motif="Nouveau client"
      ```
      Lire la sortie : un nom « introuvable » ou « ambigu » se retire à
      l'écran (« Retirer » au bout de la ligne).
      *(fait le 2026-11-18 : 9 + 5 exclus, aucun introuvable)*
- [ ] Prod : carte « À envoyer » (≈ 126 attendus après exclusions) et
      carte « Sans destinataire »
- [x] Google : coller `docs/outils/barometre-panora.gs` dans le projet
      Apps Script du questionnaire, propriétés `PANORA_URL` et
      `PANORA_JETON`, puis `bpInstaller` : le journal donne la date limite,
      et la description du formulaire passe à « Durée estimée : 5 minutes. »
      (alignée sur le mail, décision du 17/11). La case « Réponses Google »
      de l'écran passe au vert à la première réponse.
      *(fait le 2026-11-18 : projet « Baromètre-Cible », fichier panora.gs ;
      journal « Panora joint : baromètre 2026, réponses jusqu'au 2026-11-30 » ;
      durée passée à 5 minutes. Un 1er essai avait été refusé (« Panora refuse
      le jeton »), corrigé côté jeton/Coolify avant le 2e)*
- [ ] Contacts à compléter : bouton « 📥 Contacts à compléter » (Excel par
      commercial, colonnes jaunes) envoyé à l'équipe le 18/11, retour attendu
      lundi 23/11 matin ; les commerciaux complètent la fiche client dans
      Panora (interlocuteur + e-mail), le client passe alors en « À envoyer »
- [ ] Jour J : `BAROMETRE_MODE=production`, puis « Envoyer à N clients »
- [ ] 1 h après : carte « Invités », Brevo → Transactionnel → tag
      `barometre-2026`, aucune ligne « Échec »
- [ ] Une réponse réelle arrive → le client passe dans « Ont répondu »
- [ ] 23/11 : « Relancer » (seuls les invités sans réponse)
- [ ] 1/12 : un lien ouvert affiche « questionnaire clos », le formulaire
      Google est fermé
### Limites à connaître

- « A ouvert le lien » : certaines messageries d'entreprise testent les
  liens avant le client. Indicatif seulement ; la relance se fonde sur
  « A répondu », pas sur « ouvert ».
- Le client est reconnu à son nom (pré-rempli). S'il le modifie, sa
  réponse arrive dans « Réponses à rattacher » : la rattacher à la main.
- Salutation : un nom d'interlocuteur qui n'est pas une personne
  (« contact », « audreyasseke », « Comptabilité RCG ») n'est pas salué :
  « Bonjour MANOOVA, » (Salutation::personneNommable, décision du 17/11).
  Étendu le 18/11 à TOUS les mails de suivi (campagne démarrée, terminée,
  décapage, maintenance, satisfaction, J-7, renouvellement, accusé) :
  [ ] vérifier sur un vrai mail d'un client dont l'interlocuteur s'appelle
  « contact » ou un mot en minuscules → « Bonjour CLIENT, ».

## § 36 — Tarif des panneaux éclairés, tarif à date passée *(2026-11-18)*

### Ce qui a changé

Règle validée par écrit le 18/11 : une commune peut avoir un **tarif ODP
et/ou TM différent pour les panneaux éclairés** (case « Éclairé » de la
fiche panneau). Vide = même tarif : toutes les autres communes gardent
exactement leur calcul. Pas d'écran LED chez CIBLE.

- Source unique : `Commune::ratesAt($date, $eclaire)`. S'applique aux taxes
  dues aux mairies (page Taxes, Suivi des mairies, rapport, tableau de bord,
  synthèse), aux factures depuis une campagne, aux factures et devis saisis
  (ligne liée à un panneau éclairé), et à l'aperçu du formulaire facture.
- Ligne saisie sans panneau : tarif non éclairé (le comptable corrige le
  montant si besoin). Factures déjà émises : jamais modifiées.
- **Tarifs communes → « Programmer »** accepte une **date passée** (ex.
  01/01/2026), avec un **motif obligatoire** : les taxes sont recalculées
  depuis cette date. L'historique se referme proprement autour.
- Le MP gère les tarifs (cf. § 28, décision du 18/11).

### Tarifs donnés le 18/11 — à saisir une fois confirmés

| Commune | ODP | ODP 💡 | TM | TM 💡 | Effet |
|---|---|---|---|---|---|
| Port-Bouët | 3 000 | (vide) | 1 000 | 3 000 | 01/01/2026 |
| Adjamé | 1 000 | 3 000 | 1 000 | 3 000 | 01/01/2026 |

Parc au 18/11 (commande lancée en prod) : Adjamé 12 panneaux, 0 éclairé ;
Port-Bouët 19 panneaux, 2 éclairés.

### À vérifier

- [ ] Migration jouée au déploiement (colonnes `odp_rate_eclaire`,
      `tm_rate_eclaire` sur `communes` et `commune_tax_rate_history`)
- [ ] Dev : Tarifs communes → Adjamé → Programmer, 01/01/2026, ODP 1 000 /
      💡 3 000, TM 1 000 / 💡 3 000, motif → tarif enregistré ; l'Historique
      montre l'ancien tarif jusqu'au 31/12/2025 puis le nouveau
- [ ] Sans motif sur une date passée : refusé, rien n'est écrit
- [ ] Page Taxes 2026 : Adjamé recalculé (ODP passe de 3 000 à 1 000 pour
      les 12 panneaux non éclairés) ; le taux 💡 s'affiche sous le taux
- [ ] Un panneau de Port-Bouët coché « Éclairé » : TM au tarif 💡 dans le
      détail par panneau ; un non éclairé reste à 1 000
- [ ] Facture depuis une campagne avec un panneau éclairé : ligne au tarif 💡 ;
      une facture déjà émise ne change pas
- [ ] Suivi des mairies 2026 : le dû (réclamé) ne bouge pas, seul le
      « Calcul Panora » de contrôle change ; 2027 suit les nouveaux tarifs

## § 37 — Taxes, détail par panneau : sélection multiple *(2026-11-18)*

Comme sur la page Poses : une case par ligne, une case pour toute la page,
« Toutes les lignes du filtre — pas seulement cette page ». Dès qu'une
ligne est cochée, une barre d'actions groupées affiche le nombre de lignes,
les totaux ODP / TM / total de la sélection, et deux téléchargements :
**Excel de la sélection** et **PDF de la sélection** (nom personnalisable ;
l'en-tête du PDF indique « Sélection : N ligne(s) choisie(s) sur M »).
La sélection tient d'une page à l'autre et se vide si on change de filtre.
Les boutons Excel / PDF du haut exportent toujours tout le filtre.

- [ ] Cocher 3 lignes sur 2 pages → la barre montre 3 lignes et leurs totaux
- [ ] « Toutes les lignes du filtre » puis décocher 2 lignes → N − 2 lignes ;
      le PDF de la sélection ne les contient pas, son total = barre
- [ ] Excel de la sélection : seulement les lignes choisies
- [ ] ✕ vide la sélection ; changer de filtre repart d'une sélection vide
- [ ] PDF avec « ODP seul » (ou une sélection d'ODP uniquement) : pas de colonnes
      Client, Campagne, Période (demande du 18/11) ; PDF TM ou mixte : inchangé
- [ ] Mise en page pour la DG (18/11), vérifiée sur le vrai rendu DomPDF :
      PDF du détail ODP (une page, marges, total et note avec le tableau) ;
      **Suivi des mairies** PDF : colonnes alignées entre Abidjan et
      Intérieur, TOTAL à la taille du tableau, jamais seul en haut de page ;
      **relevé d'une mairie** : passe proprement sur deux pages quand il y a
      beaucoup de règlements (rien n'a changé sur une page)

## § 38 — Suivi des mairies : cartes filtres, sélection, relevé en deux versions *(2026-11-18)*

Demandes du 18/11 : « le fichier PDF est trop basique, on ne voit pas où sont
les versements, les arriérés » · « je veux sélectionner juste Cocody et Abobo
pour sortir le PDF ou l'Excel » · « les cartes filtres n'affichent pas les
listes ». Choix validés : deux versions du relevé (mairie / interne), annulés
seulement dans l'interne, boutons du haut inchangés (tout l'exercice), pas de
pagination des versements.

### Liste (Suivi des mairies)
- [ ] Clic sur **Payé** → seulement les mairies qui ont reçu un versement ;
      **Reste à payer** → solde positif ; **Dû à ce jour** → montant dû
      aujourd'hui ; **À venir** (2027) → TM à venir. Second clic, « Total dû »
      ou « ✕ Toutes les mairies » : toutes. La page ne se recharge pas.
- [ ] Bandeau orange (2026) → « Voir ces mairies » : les mairies sans réclamé saisi
- [ ] Les onglets Abidjan / Intérieur / Toutes comptent la liste filtrée ;
      le TOTAL du bas suit le filtre (« · N mairies »)
- [ ] Cocher Cocody et Abobo → barre « 2 mairies sélectionnées » avec leurs
      noms, total dû, payé, solde ; la sélection tient en changeant d'onglet
      ou de carte
- [ ] **PDF de la sélection** : « Sélection : 2 mairies sur 30 » en tête,
      seules ces mairies ; zone sans mairie choisie non imprimée
- [ ] **Excel de la sélection** : mêmes mairies ; feuille de zone absente si
      aucune mairie de cette zone ; la feuille REGLEMENTS ne liste qu'elles
- [ ] Boutons Excel / PDF du haut : tout l'exercice, comme avant

### Relevé d'une mairie (fiche → deux boutons)
- [ ] **Relevé pour la mairie** : récapitulatif (arriérés, total dû, versé,
      solde, dû à ce jour), puis 1. Ce qui est dû · 2. Versements reçus
      (n°, date, objet, mode, référence, quittance) · 3. Solde posé en
      opération (total dû − versé = solde) · 4. Base de calcul. Ni calcul
      Panora, ni versement annulé
- [ ] **Relevé interne** : bandeau « Document interne », colonne « Saisi par »,
      5. Contrôle réclamé / calcul Panora (ODP, TM, écart) en 2026,
      6. Versements annulés avec date, auteur et motif
- [ ] Cocody 2026 en dev : versé 52 050 000 (3 versements), solde 53 000

## § 39 — Réservation : le BC reçu confirme, « Confirmer sans BC », accès du commercial *(2026-11-19)*

Règles validées par écrit le 19/11 :
1. **le BC reçu confirme la réservation** et bloque fermement ses panneaux ; le MP crée ensuite la campagne ;
2. l'admin garde **« Confirmer sans BC (exception) »**, motif obligatoire ; le suivi du BC continue ; à l'échéance, pas d'annulation : l'admin et le commercial sont prévenus une fois ;
3. un dossier concurrent annulé garde son motif (« Panneaux pris par RES-… »), son commercial est prévenu (alerte + mail) ;
4. le commercial ne voit et ne touche que ses réservations (fiche, édition, BC, facture, suppression).
Le client reçoit « réservation confirmée » au BC reçu ; « Annuler la réception » remet en option (les dossiers concurrents annulés ne reviennent pas).

### À jouer au déploiement
- [ ] Migration `2026_11_19_150000_add_confirmation_auteur_to_reservations` (3 colonnes nullables : `confirme_par`, `confirme_sans_bc_motif`, `bc_echeance_signalee_at`)

### À vérifier (dev, puis prod)
- [ ] Option en attente de BC : la carte Actions propose **« ⚠ Confirmer sans BC (exception) »** (plus « Confirmer la réservation ») ; sans motif → refusé
- [ ] Commercial : « 📥 BC reçu » → message « réservation confirmée, panneaux bloqués » ; fiche : « Enregistré le … **par [nom]** », bloc « Confirmation : … par [nom] · à la réception du bon de commande »
- [ ] Après BC reçu : panneau **Confirmé** (fiche panneau), **Occupé** dans Disponibilités ; le client reçoit le mail « réservation confirmée »
- [ ] Le MP reçoit l'alerte et le mail « BC reçu, campagne à créer » ; « 🚀 Créer la campagne » demande le nom, prérempli avec le nom prévu
- [ ] Autre option sur le même panneau : annulée, motif « Panneaux pris par RES-… », son commercial reçoit l'alerte et le mail
- [ ] Panneau déjà pris ferme par un autre dossier : « BC reçu » refusé avec le nom du panneau, rien n'est enregistré
- [ ] Admin : « ↺ Annuler la réception » → la réservation repasse en option, panneau en Option
- [ ] Confirmée sans BC : relances J-15/J-7/J-2 continuent ; le lendemain de l'échéance, alerte « BC toujours absent » (une seule fois), réservation toujours confirmée
- [ ] Commercial A ouvre l'adresse de la réservation du commercial B → 403 ; le comptable ouvre toujours toutes les réservations
- [ ] Confirmation groupée : une option en attente de BC est sautée (« BC attendu : confirmer depuis la fiche, avec un motif »)
- [ ] Fenêtre d'annulation d'une réservation avec campagne : « la campagne … sera annulée elle aussi »

### Laissé de côté (même logique, autre chantier — à trancher)
- [x] Devis accepté et réservation « ferme » : naissent confirmés sans BC → réglé au § 40
- [x] Client qui a déjà accepté : reçoit encore des rappels, la page publique propose encore Confirmer/Refuser → réglé au § 40
- [x] Panneau en option affiché sélectionnable dans Disponibilités, mais refusé à la création → réglé au § 40

## § 40 — Réservation : « Ferme » = BC reçu, devis accepté en option, options concurrentes, rappels *(2026-11-19)*

Suite du § 39 — choix validés par écrit le 19/11 (« 1- a 2- a 3- a ») :
1. **« Ferme » dans Disponibilités = le client a déjà envoyé son BC** : la réservation naît en option, le BC est enregistré tout de suite, c'est lui qui confirme (panneaux bloqués, options concurrentes annulées, MP prévenu, client prévenu) ;
2. **devis accepté → réservation en option**, BC attendu 30 jours (relances J-15/J-7/J-2), plus de confirmation directe ;
3. **un panneau déjà en option peut être retenu** par un autre dossier (avertissement à l'écran) : le premier BC reçu l'emporte ;
4. une proposition déjà acceptée **ne reçoit plus de rappels** « répondez », la page publique et l'espace client n'affichent plus Confirmer/Refuser.

Précision du 19/11 : **le BC est toujours reçu par le commercial du dossier** (assigné, à défaut le créateur). Si l'admin ou le MP le saisit à sa place, la fiche affiche « Reçu par [commercial] — saisi par [admin] ».

Correctif lié : un panneau à **démarrage différé** n'est plus vérifié avant sa date de début à la confirmation (sinon la campagne qui le libère juste avant faisait échouer le BC reçu ou l'acceptation du client).

### À jouer au déploiement
- [ ] Migration `2026_11_19_170000_add_bc_saisi_par_to_reservations` (1 colonne nullable : `bc_saisi_par`)

### À vérifier (dev, puis prod)
- [ ] Disponibilités → sélection → fenêtre : la carte s'appelle **« 🔒 Ferme — BC reçu »** ; en la choisissant, l'encadré vert et le champ **N° de bon de commande (facultatif)** apparaissent
- [ ] Ferme — BC reçu : message « BC enregistré, panneaux bloqués » ; fiche : statut **Confirmée**, bloc BC « Reçu par [commercial du dossier] » ; le MP reçoit « BC reçu, campagne à créer » ; aucune campagne créée automatiquement
- [ ] Admin qui crée une « Ferme » pour un commercial assigné : fiche « Reçu par [commercial] — saisi par [admin] »
- [ ] Option sur un panneau **déjà en option** ailleurs : créée, avec le message « ⚠ Déjà en option pour d'autres dossiers : P-… (RES-…). Le premier bon de commande reçu l'emporte. »
- [ ] Panneau **pris ferme** (réservation confirmée, campagne) : toujours refusé ou décalé comme avant
- [ ] Le premier des deux dossiers qui reçoit son BC : confirmé ; l'autre annulé « Panneaux pris par RES-… », son commercial prévenu
- [ ] Devis accepté par le client (lien public ou espace client) : la réservation créée est **En attente / option**, « BC attendu jusqu'au … » ; l'alerte « Devis accepté » le dit ; aucun panneau en Confirmé
- [ ] Proposition acceptée par le client : plus de mail de rappel J+2/J+5 ; en rouvrant le lien : « Proposition acceptée — bon de commande attendu », sans boutons ; idem dans l'espace client
- [ ] Panneau à démarrage différé (libéré en cours de période) : « BC reçu » passe sans refus

### Laissé de côté
- [ ] L'avertissement « déjà en option » reste à l'écran seulement (pas d'alerte au commercial de l'autre dossier) — choix par défaut gardé le 19/11

## § 41 — Devis : TTC avec TSP, TVA des services côté client, quantité des services *(2026-11-19)*

Règles validées par écrit le 19/11 :
1. **TTC du devis = net HT + TVA + TSP**, pour le DEVIS seulement. La facture FNE ne change pas. Le net à payer est identique entre le devis et la facture.
2. **Rien de rétroactif** : un devis prend le nouveau TTC à son prochain enregistrement. Les devis déjà enregistrés gardent leur TTC d'origine (HT + TVA).
3. **L'espace client et le lien public** utilisent le même calcul de TVA des services que la fiche admin et le PDF (`Quote::tvaServices()`).

Corrigé au passage : en modification, la quantité des services revenait à 1. Réenregistrer le devis faisait baisser le net.

### Liste des devis enregistrés avant la règle (sur dev, puis prod)
- [ ] Structure : `php artisan tinker --execute='echo DB::select("SHOW CREATE TABLE quotes")[0]->{"Create Table"}, PHP_EOL;'`
- [ ] Liste (TTC actuel ≠ HT + TVA + TSP ; `ecart_net` doit valoir 0) : requête donnée le 19/11, à rejouer. Ces devis gardent leur TTC : on ne recalcule rien sans décision.

### À vérifier (dev, puis prod)
- [ ] Formulaire, cas ILUX : ligne « TSP » puis « Total TTC » 5 808 000 ; total 6 236 400 (inchangé)
- [ ] Formulaire, service « 30 × 20 000 » avec TVA : la ligne affiche 600 000 HT et 708 000 TTC, renvoyés par le serveur ; supprimer la ligne relance le récapitulatif
- [ ] Modifier un devis qui a un service en quantité 30 : le champ Qté affiche 30, et le net reste le même après l'enregistrement
- [ ] Brouillon réenregistré : sur le PDF, HT + TVA + TSP = TTC ; net inchangé
- [ ] Fiche admin : TTC panneaux + TM + ODP + services HT + TVA services = total à payer
- [ ] Espace client et lien public, devis avec un service « HT » : sa TVA n'est plus comptée ; même TVA que le PDF
- [ ] Facture issue d'un devis : même net que le devis ; son TTC reste HT + TVA (la TSP est dans les autres taxes)

## § 42 — PDF du devis : mise en page sur 2 pages, ligne de remise *(2026-11-19)*

Arbitrages écrits du 19/11 (les choix déjà validés priment sur le cahier) :
- l'en-tête garde son fond jaune, le filet orange s'ajoute dessous ;
- aucun saut de page forcé avant les CG, chaque article reste d'un seul tenant ;
- le pied de page est fixe, sur une seule ligne ;
- polices de la charte en Regular et Bold seulement, jamais ExtraLight ;
- montant net : texte jaune sur barre bleue.

Option 1 retenue : les CG passent en 7 pt pour qu'un devis long tienne sur 2 pages.

Ligne de remise ajoutée : la barre des lignes donne le HT brut, puis « Montant HT − Remise = Montant Total HT ».

Les 4 chemins du PDF (bouton admin, espace client, lien public, pièce jointe du mail) passent par `App\Services\QuotePdf`.

### À vérifier (dev, puis prod)
- [ ] Télécharger le PDF d'un devis réel depuis la fiche admin : 2 pages, texte foncé (pas pâle), pied sur une ligne, sans chevauchement
- [ ] **Pied de page avec les coordonnées du serveur** : la ligne tient dans la largeur, sans déborder à droite. Les valeurs de prod peuvent être plus longues que celles de ce poste.
- [ ] Devis avec remise : lignes brutes → barre = HT brut → « Remise (x %) » → Montant Total HT = net ; le reste se recoupe jusqu'au net
- [ ] Devis long (8 à 10 emplacements) : la 2e signature tient en bas de la page 2
- [ ] Le même PDF depuis l'espace client, le lien public et la pièce jointe du mail d'envoi
- [ ] La facture FNE n'a pas bougé (polices, pied, mise en page)
- [ ] **Désignations en italique** : même police que le reste (Nunito), pas une police plus large qui fait passer chaque emplacement sur 3 lignes. Sinon, c'est le cache de polices DomPDF qui est périmé : il pointe vers des fichiers supprimés (constaté le 19/11 sur un poste de test). Dans ce cas, renommer `storage/fonts/installed-fonts.json` sur le serveur, puis régénérer le PDF : DomPDF reconstruit le cache.

## § 43 — Devis : cachet et signature de la Directrice Commerciale sur chaque devis *(2026-11-19)*

Demande écrite du 19/11 : le cachet signé apparaît automatiquement sur **chaque** devis généré, dans les deux cadres « Pour CIBLE SARL » (Bon pour accord de la page 1 et de la fin des CG), sans circuit de validation. La procédure longue (bouton « Signer », QR, page de vérification) est abandonnée.

L'image est **versionnée avec le code** depuis le 23/11 (`resources/signatures/directrice.png`, config `devis.cachet_signature`), à la demande de l'utilisateur : elle part avec chaque mise en ligne, **aucune action sur le serveur**. Dossier non public : aucune adresse web ne la sert. Sans le fichier, les cadres restent vides et le PDF se génère normalement.

### Au déploiement
- [x] ~~Déposer l'image sur le serveur~~ : plus nécessaire depuis le 23/11 (le terminal Coolify était de toute façon en panne : « procReady not received »).

### À vérifier
- [x] PDF d'un devis : cachet dans les deux cadres « Pour CIBLE SARL » — vu sur dev et prod le 23/11
- [ ] Devis long (8 à 10 emplacements) : toujours 2 pages
- [ ] Le même cachet sur le PDF de l'espace client, du lien public et de la pièce jointe du mail

## § 44 — Serveur : terminal Coolify bloqué, redémarrer Docker à une heure creuse *(2026-11-23)*

Constat du 23/11 : le terminal Coolify est « non disponible » sur panora-app et panora-staging. Depuis le terminal du serveur, `docker exec` répond « OCI runtime exec failed … procReady not received ». La santé des deux applications s'affiche « inconnu » dans Coolify.

Le serveur, lui, va bien :
- mémoire : 3,7 Go disponibles sur 7,7 Go ;
- disque : 47 Go libres (35 % utilisés) ;
- charge : 0,55.

Le blocage ne vient donc pas des ressources. C'est un défaut de Docker, fréquent après une très longue période sans redémarrage (213 jours au 23/11). Panora n'en souffre pas : l'application répond, les envois planifiés partent. **Pas d'urgence.**

`docker cp` fonctionne encore depuis le terminal du serveur, en dépannage.

### À faire, à une heure creuse (coupe toutes les applications quelques minutes)
- [x] Vérifier que la sauvegarde est passée (rattrapage de 20h08 chez Hetzner)
- [x] Prévenir l'équipe de la courte coupure
- [x] Redémarrer Docker : fait le 23/11 vers 21h (`systemctl --no-block restart docker`)
- [x] Panora en 404 après le redémarrage, l'application redémarrait en boucle : `DB_HOST=10.0.1.7`. Une **adresse IP** de conteneur change à chaque redémarrage de Docker. Corrigé dans Coolify : `DB_HOST` et `REDIS_HOST` désignent maintenant le **nom interne** des ressources (la suite après `/database/` dans l'adresse de la page). Panora répond de nouveau.
- [x] panora-staging : même correction, dev.panora-cible.com répond (23/11)
- [ ] Autres variables à vérifier, prod et staging : aucune valeur du type `10.0.x.x` ne doit rester (`DB_HOST`, `REDIS_HOST`, `DB_URL`, `REDIS_URL`…)
- [x] `sauvegardes:rattraper` : simulation (« sur backups seulement »), puis lancé pour de vrai le 23/11 au soir. `backup:list` : les deux disques sont « Healthy » (serveur : 25 copies ; Hetzner : 19)
- [ ] Scheduled Tasks : `schedule:run` en « Success » chaque minute
- [ ] Service sftpgo arrêté (rouge) après le redémarrage : le relancer s'il sert

### Sauvegardes Hetzner manquantes (constaté le 23/11 au soir) — rend le redémarrage PRIORITAIRE
La console Hetzner (bucket `panora-sauvegardes-cible/panora/`) s'arrête au **20/11 à 4h40** : aucune copie les 21, 22 et 23/11.
- 21/11 : panne Hetzner hel1 (504 GatewayTimeout), les 3 essais ont échoué (mails reçus).
- 22 et 23/11 : cause à établir. Soit nouvel échec d'envoi (chercher les mails « Échec de la sauvegarde » de ces jours-là), soit tâches de la nuit non lancées : le planificateur (`schedule:run`) est lancé par Coolify via `docker exec`, la commande en panne. Le baromètre envoyé le 23/11 après-midi montre que le planificateur tournait au moins à ce moment-là.
- [x] Cause établie le 23/11 au soir : `backup:list` donne une dernière copie **sur le serveur** au 21/11 à 5h45. La sauvegarde ne s'est donc pas lancée du tout les 22 et 23/11 (sinon la copie du serveur existerait). Le planificateur ne tournait plus : `docker exec` en panne sur l'ancien conteneur, et aucun mail d'alerte, puisque le contrôle de 8h30 passe par le même planificateur. Le redéploiement du 23/11 (nouveau conteneur) l'a remis en route : `schedule:run` en succès chaque minute, terminal de nouveau disponible.
- [x] Terminal de nouveau disponible le 23/11 au soir ; `backup:run --only-to-disk=backups-s3` réussi : `2026-11-23-20-08-47.zip` (286 Mo) chez Hetzner
- [ ] 24/11 matin : `php artisan backup:list` → les deux disques « Healthy », copie de la nuit du 24 sur le serveur ET chez Hetzner
- [ ] Rappels « fin de campagne dans 3 jours » à l'équipe : sautés pendant la panne (ils visent un jour précis). Rattrapage, en essai d'abord avec `--dry-run` : `php artisan campaigns:notify-commercial-ending --days=1`, puis `--days=2`, puis `--days=3` (sans doublon : chaque envoi est mémorisé par campagne et par délai)
- Les autres tâches se rattrapent seules au passage suivant : relances de fin de campagne (étape selon les jours restants), relances BC, activation des campagnes planifiées, alertes, nettoyage. Les rappels J+2 / J+5 des propositions envoyées du 19 au 21/11 sont perdus (jour précis), sans gravité.
- Le redémarrage de Docker reste à faire à une heure creuse, mais n'est plus urgent : le défaut de `docker exec` peut revenir sur le nouveau conteneur.
- Réglages du 23/11 (après redéploiement) : envoi vers Hetzner **un morceau à la fois**, et **rattrapage automatique à 14h** si la copie du jour manque sur un disque.
  - [ ] Coolify → variables de panora-app : `BACKUP_S3_CONCURRENCE` ne doit pas exister (ou valoir 1), sinon il impose son ancienne valeur
  - [ ] `php artisan sauvegardes:rattraper --simuler` → « Copie du jour présente partout » après une nuit normale
- Le redémarrage de Docker arrête puis relance les mêmes conteneurs, sans les recréer : rien n'est effacé. Il coupe seulement les applications quelques minutes.

## § 45 — Devis accepté, BC reçu sur une simple option, lien privé du devis, dates *(2026-11-23)*

Constaté le 23/11 en testant le circuit complet sur dev, avant la formation des commerciaux :
1. **L'acceptation d'un devis échouait toujours depuis le 21/07** (« Unknown column 'quantity' ») : aucun devis n'avait jamais pu devenir une réservation. Corrigé, avec un vrai test de conversion.
2. **Une simple option** (créée dans Disponibilités, sans proposition ni devis acceptés) **ne pouvait pas recevoir son BC** : le bouton « 📥 BC reçu » n'apparaissait pas. Seul l'admin pouvait la confirmer, sans motif et sans trace du BC. Désormais :
   - le bouton « 📥 BC reçu » apparaît sur toute option, et le BC confirme ;
   - toute confirmation sans BC reçu passe par « ⚠ Confirmer sans BC (exception) », admin seulement, avec motif obligatoire ; le suivi du BC s'ouvre ;
   - la confirmation groupée saute les options sans BC reçu.
3. **Mail du devis** : le bouton mène toujours au lien privé et sécurisé, sans connexion. Avant, un client avec un compte tombait sur la page de connexion. Le client sans compte lit qu'il peut demander l'ouverture d'un espace client ; celui qui en a un lit qu'il y retrouve ses devis. Le PDF reste joint.
4. **Dates** : dans tous les formulaires de l'admin, les jours antérieurs au début sont grisés dans le calendrier de la date de fin.

### À vérifier (dev)
- [ ] Devis envoyé à un client de test → « Accepter le devis » sur le lien privé → réservation **En attente (option)**, « BC attendu jusqu'au … », panneaux en Option
- [ ] Simple option créée dans Disponibilités → la fiche affiche le bloc « 📥 BC reçu » → clic → réservation **Confirmée**, panneaux Confirmés
- [ ] Admin, simple option : le bouton est « ⚠ Confirmer sans BC (exception) », motif demandé
- [ ] Mail du devis : le bouton ouvre le devis sans connexion ; la phrase sur l'espace client correspond au client (avec ou sans compte)
- [ ] Devis, réservation, campagne, Disponibilités : dans le calendrier de fin, les jours avant le début sont grisés

## § 46 — Devis : réponses du client, lien privé à la charte, espace client, nom du PDF joint *(2026-11-23)*

Suite des remarques du test de dev du 23/11 :
1. **Réponse du client** : acceptation, refus ou demande de modification, depuis l'espace client ou le lien privé. Chaque réponse crée l'alerte **et envoie un mail au commercial du devis**, avec copie commerciale comme pour les propositions. À défaut de commercial joignable, les admins reçoivent le mail. Avant, aucun mail ne partait, et la fiche montrait seulement « En négociation ».
2. **Le lien privé permet de demander une modification.** Avant, le bouton y était affiché, mais rien ne se passait. La demande s'affiche sur la fiche admin du devis, et le client la retrouve sur sa page.
3. **Page du lien privé à la charte CIBLE** : liseré, logo, polices, couleurs, coordonnées.
4. **Espace client** :
   - badge sur « Devis » ;
   - encart « devis attendent votre décision » en tête du tableau de bord ;
   - mention « Votre décision est attendue » dans la liste.
5. **Nom du PDF joint** modifiable dans la fenêtre d'envoi du devis (par défaut « Devis-DEV-… »).
6. **Réservation issue d'un devis** :
   - « Créée par » indique le client et le devis ;
   - le commercial assigné s'affiche toujours (avant : « Non assigné » quand il avait aussi « créé » le dossier) ;
   - la note dit par où le client a accepté.

### À vérifier (dev)
- [ ] Lien privé d'un devis envoyé : logo CIBLE, liseré, couleurs de la charte
- [ ] « Demander une modification » sur le lien privé → message envoyé → la page affiche la demande ; la fiche admin du devis aussi ; mail reçu par le commercial (et la copie commerciale)
- [ ] Refus sur le lien privé → mail « a refusé » au commercial, motif affiché sur la fiche
- [ ] Acceptation → mail « a accepté » avec la réservation créée et « BC attendu jusqu'au … »
- [ ] Réservation issue de l'acceptation : « Créée par : Le client, en acceptant le devis DEV-… », commercial du devis affiché
- [ ] Espace client d'un client avec un devis envoyé : badge sur « Devis », encart sur le tableau de bord
- [ ] Envoi d'un devis : champ « PDF joint » dans la fenêtre, nom modifié → la pièce jointe reçue porte ce nom
- [ ] Disponibilités → cocher des panneaux → « 💼 Créer un devis » : chaque ligne montre « RÉF — adresse » du panneau (avant : la ligne paraissait vide, le panneau était pourtant bien repris)

## § 47 — Montants d'une campagne issue d'un devis, HT / TTC, charte des pages publiques *(2026-11-23)*

Constaté le 23/11 sur DEV-2026-009 → RES-V9RYNDNT → « CAMPAGNE FORMATION TEST » :
- **montant faux** : la réservation recevait le total à payer du devis (317 565, TTC + taxes + services) ; la campagne le comparait à du HT (85 000) et affichait un faux « écart de facturation » ;
- **facture FNE sans la remise ni les services** : elle repartait des prix des panneaux.

### Règles validées par écrit le 23/11
1. Réservation et campagne issues d'un devis portent le **HT net des panneaux** (après remise), étiqueté HT. Le **total à payer du devis** (TTC, taxes et services compris) est rappelé en dessous.
2. La facture FNE générée depuis la campagne **copie le devis** : lignes, remise, services avec leur TVA.
3. Elle garde les **taux ODP / TM figés sur le devis**.
4. Si la campagne a changé de dates ou de panneaux : la facture suit la **campagne réelle**, aux prix nets du devis, avec les services du devis, et le bandeau d'écart le signale.

### Également livré
- **HT ou TTC précisé** sur les montants de réservation et de campagne :
  - mails ;
  - page publique de la réservation ;
  - espace client ;
  - propositions.
- **Mail du devis** : « FCFA TTC (taxes et services compris) ».
- **Création de campagne** depuis une réservation :
  - le commercial de la réservation est proposé (avant : l'utilisateur connecté) ;
  - le mail au client nomme le commercial de la campagne.
- **Mails de démarrage de campagne** :
  - « Votre espace client est déjà ouvert » ;
  - sinon : « nous vous en ouvrons un si vous le souhaitez, répondez à ce mail ».
- **Espace client** : une seule règle, `Client::hasAccount()` (aussi pour le mail du devis).
- **Pages publiques à la charte CIBLE** (logo, liseré, couleurs, coordonnées) : réservation confirmée, facture, pige, lot de piges, décapage, lien invalide.

### À vérifier (dev) — avec un NOUVEAU devis : RES-V9RYNDNT a été créée avec l'ancien montant
- [ ] Devis avec remise et services → accepté → réservation : « Montant total HT » = HT net du devis, et rappel « Total à payer du devis … TTC »
- [ ] BC reçu → « Créer la campagne » : le commercial proposé est celui de la réservation ; montant HT identique ; aucun bandeau d'écart
- [ ] « Générer la facture FNE » : message « Reprise à l'identique du devis DEV-… » ; la facture a la remise, la pose, l'impression, et le même total à payer que le devis
- [ ] Campagne prolongée d'un mois → bandeau d'écart (HT net × 2 mois) ; la facture FNE suit la campagne (prix nets, 2 mois, services du devis)
- [ ] Mail « démarrage de campagne » : « Montant total … FCFA HT », bonne phrase sur l'espace client, bon commercial
- [ ] Lien public d'une réservation confirmée (/p/…) : logo CIBLE, liseré, couleurs de la charte

## § 48 — Baromètre : renvoyer l'invitation au contact corrigé de la fiche *(2026-11-24)*

Une invitation est partie vers un interlocuteur qui a quitté son entreprise (réponse automatique « ne fait plus partie des effectifs », transmise par Josiane le 23/11). L'adresse de chaque client est figée à l'invitation : la relance serait repartie vers la même personne.

Nouveau bouton **« Renvoyer »** sur l'écran du baromètre, pour un client invité qui n'a pas répondu :
- il prend le contact principal **actuel** de la fiche du client ;
- il renvoie l'invitation, avec le **même lien personnel** ;
- la relance suivra la nouvelle adresse.

Refusé si la fiche donne toujours la même adresse, si elle n'en donne aucune, si plusieurs interlocuteurs sont sans principal, ou si l'adresse est déjà invitée pour un autre client.

### À faire (prod)
- [ ] Client concerné du 23/11 : sur sa fiche, retirer ou corriger l'ancien interlocuteur, ajouter le nouveau et le cocher **principal**. Puis écran Baromètre → ligne du client → « Renvoyer ». Le message vert donne l'adresse ; vérifier dans la minute que la ligne affiche l'invitation du jour
- [ ] Chaque nouvelle réponse automatique de ce type : même traitement (fiche, puis Renvoyer)

## § 49 — PDF du détail des taxes = relevé pour la mairie *(2026-11-24)*

Le PDF de l'écran Taxes → Détail par panneau est un **relevé par commune** (nouvelle page à chaque commune). Le **bloc « À l'attention de Monsieur le Maire… » et la signature sont retirés**, à sa demande.

**24/11 au soir : retour à l'ancien design**, à sa demande : A4 paysage, en-tête CIBLE des documents internes, tableau à filets fins, pied de page de la charte. Les informations du relevé sont toutes gardées, et ce qui était en gras reste en grand et en gras :
- **En-tête :** « Taxes communales », puis le titre selon la taxe, la phrase « … sur le territoire de la Commune de … » et « Abidjan, le … » ;
- **Titre selon la taxe :**
  - ODP : « Relevé des taxes d'occupation du domaine public » ;
  - TM : « Relevé de la taxe municipale » ;
  - les deux : « Relevé des taxes communales », avec deux tableaux et leurs sous-totaux ;
- **Bandeau en gras :**
  - Commune ;
  - Période « du … au … » ;
  - pour la TM, les trimestres en toutes lettres (« Trimestre 1 : de janvier à mars 2026 ») ; pour l'ODP, le mois ou la période ;
  - Opérateur ;
  - Nombre de panneaux ;
- **Tableau numéroté :**
  - colonnes Panneau, Surface, Tarif, Durée et Montant ;
  - en plus pour la TM : Annonceur, Campagne et dates d'affichage ;
- **Total à payer** et **« Arrêté le présent relevé à la somme de … francs CFA »**, en lettres ;
- **Base de calcul** en bas ;
- **Plus aucune mention des catégories exclues** (chevalets…), ni sur l'Excel.

Les montants en lettres viennent de `App\Support\MontantEnLettres`, partagé avec le devis. Au passage, « deux cent mille » et « quatre-vingt mille » sont corrigés (avant : « deux cents mille »).

### À vérifier (prod)
- [ ] Taxes → Détail par panneau, une commune, ODP seul, PDF : ancien design en paysage, bandeau Commune / Période / Opérateur / Nombre de panneaux en gras
- [ ] TM seul : trimestres en toutes lettres dans le bandeau ; annonceur, campagne et dates d'affichage dans le tableau
- [ ] Toutes communes : un relevé par commune, chacun sur sa page
- [ ] Excel : plus de « categories_exclues » dans l'en-tête

## § 50 — TM d'un panneau sorti d'une campagne, démantèlement daté *(2026-11-24)*

Constaté sur BNI 2026 : YOPCAIS-01A, démantelé le 27/07, a été retiré de la campagne le 24/11.
- **Sa TM de janvier à juillet avait disparu.** Retirer un panneau le détachait de la campagne, et la TM ne lisait que les rattachements actuels.
- **L'ODP du mât (YOPCAIS-01, 2 faces) courait encore en novembre.** Le 27/07 n'était qu'une note libre, et la suppression d'un panneau prenait la date du clic.

### Règles validées par écrit le 24/11
1. **Panneau retiré :** la TM compte du début de la campagne (ou de son ajout en cours de campagne) jusqu'à sa **date réelle de sortie**. Cette date est saisie au retrait ; par défaut aujourd'hui, jamais dans le futur ni avant le début.
2. **Motif « démantelé » :** case cochée par défaut, à décocher s'il est seulement indisponible. Le panneau **quitte le parc à cette date** : l'ODP et la TM s'arrêtent là.
3. **YOPCAIS-01 :** le **mât entier** (faces A et B) a été démantelé le 27/07/2026.

### Livré
- **Fiche campagne → Retirer :** champ « Date réelle de sortie » et case « Le panneau est démantelé à cette date ».
- **Liste des panneaux → 🗑 :** une fenêtre demande la « Date réelle du démantèlement », à la place de l'alerte du navigateur.
- **Calcul :** la TM lit l'historique des panneaux (depuis le 16/11), avec les dates d'entrée et de sortie, bornées par le démantèlement. Les retraits faits avant le 16/11 n'ont pas d'historique et ne sont pas reconstitués.
- **Fiche campagne → Ajouter un panneau :** champ « Entrée réelle ». Un panneau ajouté en cours de campagne (un remplaçant, par exemple) paie la TM à partir de là. Une campagne dupliquée compte ses panneaux dès son premier jour.

### À faire en production (une fois, après le déploiement)
Dans le terminal du conteneur de prod :
```
php artisan tinker --execute='$m = App\Models\CampaignPanelMouvement::where("reference", "YOPCAIS-01A")->where("sens", "retrait")->latest("id")->firstOrFail(); $m->update(["effet_le" => "2026-07-27"]); echo "Sortie de " . $m->reference . " datée du 27/07/2026", PHP_EOL;'
php artisan tinker --execute='foreach (App\Models\Panel::withTrashed()->whereIn("reference", ["YOPCAIS-01A", "YOPCAIS-01B"])->get() as $p) { $p->demantelerLe(Carbon\Carbon::parse("2026-07-27")); echo $p->reference . " démantelé le 27/07/2026", PHP_EOL; }'
```
- [x] Lancées en prod le 24/11 : sortie de YOPCAIS-01A datée du 27/07, faces YOPCAIS-01A et YOPCAIS-01B démantelées le 27/07/2026 (deux lignes affichées)
- [ ] **YOPCAIS-02A, le remplaçant :** il a été rattaché à BNI 2026 avant le début de l'historique. Panora le croit donc présent depuis le 01/01 et le montre en janvier. **Sa date d'affichage sera communiquée par le MP une fois l'affichage fait (24/11).** D'ici là, il compte 12 mois de TM pour BNI : ne pas envoyer à Yopougon de relevé de TM 2026 qui l'inclut. Pour dater son entrée réelle, remplacer AAAA-MM-JJ :
```
php artisan tinker --execute='$c = App\Models\Campaign::where("name", "BNI 2026")->firstOrFail(); $p = App\Models\Panel::where("reference", "YOPCAIS-02A")->firstOrFail(); App\Models\CampaignPanelMouvement::create(["campaign_id" => $c->id, "panel_id" => $p->id, "reference" => $p->reference, "sens" => "ajout", "motif" => "ajout_manuel", "source" => "manuel", "note" => "Remplaçant de YOPCAIS-01A, entrée datée après coup", "effet_le" => "AAAA-MM-JJ"]); echo "Entrée de YOPCAIS-02A datée", PHP_EOL;'
```
- [ ] Taxes → Détail, Yopougon, janvier à juillet, TM : YOPCAIS-01A apparaît pour BNI 2026 (7 mois)
- [ ] Novembre, ODP : YOPCAIS-01 n'apparaît plus
- [ ] Fiche campagne → Retirer un panneau : la date réelle et la case « démantelé » (avec ce motif) apparaissent

## § 51 — TM des campagnes tenues par la ligne d'un axe Lumipub *(2026-11-24)*

Constaté à Cocody : Taxes → Détail, TM, par trimestre, case « Lumipub (pieds des axes) » cochée. **Seul BEM sortait.** Les campagnes de VOODOO et de KOTON sur les Lumipub 3 m² n'apparaissaient pas.

**Cause :** les contrats signés avant le découpage des axes tiennent la **ligne de l'axe**, pas ses pieds. Le 13/11, `axes:decouper-campagnes` n'a passé sur les pieds que les campagnes en cours (BEM, MOOV, LADILI). Les campagnes terminées sont restées sur la ligne, et la ligne d'axe n'est jamais taxée : leur TM n'était comptée nulle part. Sonde en prod du 24/11 :

| Campagne | Période | Axes |
|---|---|---|
| #50 KOTON (Lup) | 01/04 → 30/06/2026 | CDYPUB-09, CDYLUP-10 |
| #19 VOODOO – MANSA BANK | 18/05 → 18/06/2026 | CDYLUP-002, -005, -006 |
| #72 KOTON – SOLDES & CHIC A PETIT PRIX | 01/08 → 31/10/2026 | CDYPUB-09, CDYLUP-10 |

**Règle validée le 24/11 :** une campagne qui tient la ligne d'un axe compte pour la TM de **chacun de ses pieds**, sur ses dates de campagne. Le calcul est le même que pour BEM : surface du pied × ses faces (9 m²) × tarif TM × mois de campagne.
- Aucune donnée modifiée : les campagnes, leurs poses et leurs piges restent telles quelles.
- Un pied rattaché lui-même à la campagne n'est compté qu'une fois.
- La ligne d'axe n'est toujours jamais taxée.
- Corrigé à la source (`TaxCalculationService::buildCampaignAssignmentsList`) : tous les écrans de taxes, PDF, Excel, suivi des mairies et synthèse mensuelle suivent.

### À vérifier (prod)
- [x] Chacun des 5 axes a bien ses pieds. Un axe sans pieds ne donnerait toujours rien. **Vu le 24/11 : CDYPUB-09 10 pieds, CDYLUP-10 2, CDYLUP-002 6, CDYLUP-005 4, CDYLUP-006 10, tous à 3 faces.** Commande qui ne fait que lire :
```sh
php artisan tinker --execute='foreach (["CDYPUB-09","CDYLUP-10","CDYLUP-002","CDYLUP-005","CDYLUP-006"] as $ref) { $a = App\Models\Panel::where("reference", $ref)->first(); echo $ref, " : ", $a ? App\Models\Panel::where("axe_id", $a->id)->count() . " pieds, " . ($a->faces_par_pied ?: "?") . " faces" : "introuvable", PHP_EOL; }'
```
- [x] Après déploiement, la TM des pieds de Cocody en T2, par campagne. **Vu le 24/11 : BEM 28 pieds, 756 000 ; KOTON (Lup) 12 pieds, 324 000 ; VOODOO – MANSA BANK 20 pieds, 180 000 FCFA** (1 mois : la campagne va du 18/05 au 18/06, et la TM compte les mois entamés de date à date) :
```sh
php artisan tinker --execute='app(App\Services\TaxCalculationService::class)->generateLines("trimestriel", 2, 2026, ["inclure_lumipub" => true, "type" => "tm", "commune_id" => App\Models\Commune::where("name", "COCODY")->value("id")])->where("est_pied", true)->groupBy("campaign_name")->each(fn ($l, $c) => print($c . " : " . $l->count() . " pieds, " . number_format($l->sum("amount"), 0, ",", " ") . " FCFA" . PHP_EOL));'
```
- [ ] Taxes → Détail, Cocody, TM, T2 2026, Lumipub coché : KOTON (Lup) et VOODOO – MANSA BANK apparaissent à côté de BEM
- [ ] T3 et T4 : KOTON – SOLDES, 12 pieds ; attendu 216 000 FCFA en T3 (août, septembre) et 108 000 en T4 (octobre), au tarif de 1 000 FCFA/m²/mois

## § 52 — Page d'arrivée des mails de promotion, qui compte les clics *(2026-11-25)*

Les liens « mailto: » d'un mail ne sont pas comptés par Brevo : 0 clic au Black Friday. Désormais, les liens d'un mail de promotion mènent à une page Panora : `https://panora-cible.com/offre/{code}`, et `/{format}` pour un format précis.
- **Brevo compte chaque clic**, client par client et lien par lien, donc par format.
- **Panora le compte aussi** (table `promo_clics`). Les robots et les filtres de sécurité des messageries sont comptés à part, et les vérifications de lien (HEAD) ne comptent pas. L'adresse IP n'est pas gardée : seule une empreinte courte sert à compter les visiteurs distincts.
- **La page** reprend l'affiche, le format choisi et les trois formats. Elle tente d'ouvrir toute seule le mail prérempli vers commercial@ (format déjà écrit), avec un bouton « Écrire au service commercial » si le navigateur l'empêche, et l'appel au 07 69 30 46 02.
- Une nouvelle promo = une entrée dans `config/promos.php` ; `'active' => false` affiche « Cette offre est terminée ».
- Premier usage : `docs/diffusion/promo-interieur-35-2026.html`, dont tous les liens sont passés sur la page.

- **Compteur fiabilisé (25/11, après l'envoi)** : les 14 ouvertures d'après l'envoi avaient toutes eu lieu entre 11h13 et 11h15, plusieurs liens à la même seconde. C'étaient les filtres de sécurité des messageries, pas des clients. Désormais un clic ne compte que si :
  - la page s'est affichée dans un navigateur (son script le confirme, avec un jeton signé valable 30 min) ;
  - le visiteur n'a pas ouvert plusieurs liens différents en moins de 2 secondes.

  `promos:clics` dit combien de clics sont écartés et pourquoi. Les clics d'avant ce contrôle n'ont pas la confirmation : seule la règle des rafales s'applique à eux.
- **Copies (25/11)** : `PROMO_COPIE` (Coolify, adresses séparées par des virgules) met des adresses en copie (Cc) du mail prérempli. Les deux adresses demandées le 25/11 sont nominatives : elles sont dans Coolify, pas dans le code.

### À vérifier (prod)
- [ ] La table est créée au déploiement (migration jouée au démarrage du conteneur)
- [x] Coolify (prod) : variable `PROMO_COPIE` posée avec les deux adresses, puis Redeploy. **Vu le 25/11 : la page en prod met bien les deux adresses en copie ; affiche et 3 photos servies ; offre inconnue → 404.** Cloudflare masque les adresses de la page et le navigateur les rétablit : le lien décodé garde la copie et le texte prérempli.
- [x] Ouvrir https://panora-cible.com/offre/interieur-35/50m2 sur un téléphone : la page s'affiche et la messagerie s'ouvre avec « Format souhaité : 50 m² », les deux adresses en copie
- [x] Recoller le mail dans Brevo, s'envoyer un test et cliquer sur une photo : la page du bon format s'ouvre. **Mail envoyé aux clients le 25/11.**
- [ ] Après l'envoi : `php artisan promos:clics interieur-35` (clics par format et par jour), et le rapport Brevo de la campagne (clics par lien)
- [ ] Après le déploiement : `promos:clics interieur-35` affiche « Non comptés : … en rafale » pour les filtres du 25/11 ; ouvrir la page depuis un téléphone, puis relancer la commande 1 minute plus tard : un clic de client en plus
- [ ] Rapport Brevo : désactiver « Ouvertures automatisées et clics inclus » avant la capture du lundi
