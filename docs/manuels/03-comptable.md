# Manuel Comptable — Panora

> Vous êtes **comptable** chez CIBLE. Ce manuel décrit vos écrans, vos actions et le cycle financier que vous pilotez : émission, encaissements, taxes communales, exports comptables.

---

## 1. Votre mission

Le comptable est **le gardien de la santé financière** de CIBLE :
- Vous **contrôlez la conformité FNE** de chaque facture émise
- Vous **enregistrez les versements** (espèces, chèque, virement, mobile money)
- Vous **suivez le recouvrement** — factures échues, relances, contentieux
- Vous **gérez les taxes communales** (ODP + TM) : suivi mensuel des dettes par mairie
- Vous **exportez** les données comptables (Excel, PDF) pour votre expert-comptable
- Vous **auditez** l'historique des modifications sensibles

Vous avez accès en **lecture large** sur toute l'application, en **écriture limitée** aux zones financières.

---

## 2. Votre écran d'ouverture

À la connexion, vous arrivez sur le **Tableau de bord** avec les KPI financiers en priorité :
- **CA facturé du mois**
- **CA encaissé du mois**
- **Factures en retard** (chiffrées en FCFA)
- **Reste à recouvrer** (toutes factures ouvertes)
- **Prévision 30j** (échéances à venir)
- **Taxes communales dues**

---

## 3. Vos écrans clés

### Principal
- **Tableau de bord** — vue macro financière
- **Clients** — annuaire (lecture)
- **Campagnes** — vue lecture (pour vérifier le contexte d'une facture)

### Opérations
- **Facturation** — votre outil central : cycle brouillon → envoyée → soldée
- **Taxes Communes** — suivi mensuel par commune
- **Alertes** — événements financiers (facture échue, taxe à payer)

### Analyse
- **Finance** — synthèse recouvrement, ancienneté des créances
- **Rapports** — exports comptables (Excel, PDF)

---

## 4. Vos workflows clés

### 4.1 Contrôler une facture avant émission

Quand un commercial ou un Media Planner crée une facture, elle est en statut `brouillon`. Avant l'envoi client, vous devez la contrôler.

1. Menu → **Facturation** → filtrer sur **Brouillon**
2. Pour chaque facture, vérifier :
   - **Client** : nom exact, IFU renseigné si société
   - **Période** : cohérente avec la campagne
   - **Panneaux** : bien tous ceux prévus, pas de doublons
   - **PU HT/mois** : correspond au prix négocié
   - **Services annexes** : impression, frais de pose bien facturés si applicable
   - **TVA (18%)** : appliquée correctement
   - **TSP (3%)** : calculée sur le net HT panneaux uniquement (pas sur services)
   - **TM et ODP** : appliqués selon la commune de chaque panneau
   - **Total à payer** : cohérent
3. Deux options :
   - ✅ Facture OK → l'admin ou le commercial peut cliquer **Envoyer**
   - ⚠️ Facture à corriger → laisser un message au créateur ou modifier directement si vous avez le droit

**⚠️ Attention** : une fois **envoyée**, la facture est verrouillée. Vérifiez avant.

### 4.2 Enregistrer un versement client

Le client vous paie (espèces, chèque, virement, mobile money…). Vous devez enregistrer le paiement.

1. Menu → **Facturation** → clic sur la facture concernée
2. Section **Versements enregistrés** → bouton **+ Nouveau versement**
3. Renseigner :
   - **Montant** reçu (en FCFA)
   - **Mode de paiement** : Espèces, Chèque, Virement, Mobile Money (Orange, MTN, Moov), Autre
   - **Date du versement** (jour où vous avez reçu l'argent, pas le jour de saisie)
   - **Référence** : n° chèque, référence bancaire, ID transaction mobile money…
   - **Banque** (si chèque ou virement)
   - **Type** : `acompte` ou `paiement complet`
4. **Enregistrer**

La facture bascule automatiquement :
- `partiellement soldée` si versements < total
- `soldée` si versements ≥ total

**💡 Astuce** : pour un paiement en plusieurs fois, enregistrez chaque versement au fur et à mesure. L'échéancier prévisionnel se met à jour automatiquement.

### 4.3 Suivre le recouvrement

Menu → **Finance** → onglet **Recouvrement**

Vue par ancienneté des créances :
- **0-30 jours** — factures récentes, pas encore en retard
- **31-60 jours** — factures en retard, relance à faire
- **61-90 jours** — situation critique, mise en demeure
- **90+ jours** — contentieux potentiel, escalade direction

Chaque ligne : client, référence facture, montant dû, jours de retard, dernière relance envoyée.

Actions : envoyer une relance, changer le statut, escalader.

### 4.4 Émettre une relance

1. Menu → **Facturation** → filtrer **En retard**
2. Clic sur la facture → bouton **Créer une relance**
3. Choisir le niveau :
   - **1ère relance** — ton courtois, rappel amiable
   - **2ème relance** — ton ferme, mention pénalités
   - **Mise en demeure** — dernier avis avant action
4. Le mail est pré-rempli avec le contexte, ajustez le texte si besoin
5. **Envoyer** → tracé dans l'historique de la facture

**💡 Astuce** : la vue **Finance → Relances** liste toutes vos relances envoyées avec leur suivi (date, niveau, statut réponse client).

### 4.5 Solder une facture manuellement

Cas rare : le client a payé hors plateforme (chèque à la main sans preuve numérique, virement bancaire complet sans référence traçable, arrangement à l'amiable), et vous voulez la solder sans enregistrer de versement détaillé.

1. Fiche facture → bouton **Solder manuellement**
2. Confirmer avec un motif (optionnel mais recommandé pour l'audit)
3. La facture passe en `soldée` immédiatement

**⚠️ Attention** : cette action est **auditée** (qui, quand, pourquoi). Utiliser uniquement si vous ne pouvez pas enregistrer un versement classique.

### 4.6 Suivre les taxes communales

C'est **votre responsabilité mensuelle** — payer les taxes ODP + TM aux 31 mairies concernées.

1. Menu → **Taxes Communes**
2. Sélectionner la période : mensuel (défaut), trimestriel, annuel, personnalisé
3. KPI en tête :
   - **ODP théorique** — ce qui devrait être payé pour la période
   - **TM théorique** — idem
   - **Grand total** — ODP + TM cumulés
   - **Déjà payé** — versements enregistrés
   - **Solde restant** — reste à payer
   - **Taux de couverture** — % payé sur théorique
   - **Communes soldées** — combien de mairies entièrement à jour
   - **Communes partielles** — combien en paiement partiel
4. Répartition Abidjan / Intérieur (13 communes du District / 18 villes intérieures)
5. Top 5 communes les plus taxées (Plateau, Cocody, Adjamé, San-Pédro, Bouaké typiquement)
6. Tableau détaillé par commune : ODP, TM, total, statut (Non payé / Partiel / Payé), action **Payer** ou **Historique**

### 4.7 Enregistrer un paiement de taxe communale

1. Menu → **Taxes Communes** → clic sur la ligne d'une commune → **Payer**
2. Modal :
   - **Montant** payé à la mairie
   - **Mode** de paiement (chèque, virement, espèces)
   - **Référence** (numéro reçu mairie)
   - **Date** du paiement
3. **Enregistrer** → la commune passe à `Partiel` ou `Payé` selon montant

**💡 Astuce** : gardez toujours le **reçu de la mairie** (papier ou scan). En cas de contrôle fiscal, c'est votre preuve.

### 4.8 Exporter les données comptables

Pour votre expert-comptable ou pour un audit interne :

1. Menu → **Rapports**
2. Choisir le rapport souhaité :
   - **Export Excel factures** — liste complète avec TVA, taxes, versements
   - **Export PDF factures FNE** — bordereau conforme pour l'administration fiscale
   - **Journal des versements** — tous les paiements clients de la période
   - **Journal des taxes communales** — tous les versements aux mairies
   - **Annulations** — factures annulées avec motif
3. Filtres : période, statut, commercial, client, commune…
4. **Générer le rapport** → téléchargement immédiat

**💡 Astuce** : pour un rapport mensuel type, sauvegardez vos filtres favoris (bouton étoile).

---

## 5. Ce que vous pouvez faire

| Action | Autorisée ? |
|---|:-:|
| Consulter toutes les factures | ✅ |
| Consulter toutes les campagnes (lecture) | ✅ |
| Enregistrer un versement | ✅ |
| Solder une facture manuellement | ✅ |
| Envoyer une relance | ✅ |
| Payer une taxe communale | ✅ |
| Exporter les rapports (Excel, PDF) | ✅ |
| Créer une facture | ✅ (co-partagé avec commercial et MP) |
| Envoyer une facture (verrouillage) | ✅ |
| Déverrouiller une facture envoyée | ❌ 🔒 (admin only) |
| Annuler une facture émise | ❌ 🔒 (admin only) |
| Modifier les tarifs des communes | ❌ 🔒 (admin only) |
| Créer / modifier / supprimer un utilisateur | ❌ (admin only) |
| Modifier une réservation | ❌ (commercial/MP/admin) |

---

## 6. Contrôles financiers à effectuer

### Quotidien (5 minutes)
- **Dashboard → Factures en retard** — priorité aux > 30 jours
- **Versements en attente de saisie** — si un client a payé la veille, saisir aujourd'hui

### Hebdomadaire (30 minutes)
- **Finance → Recouvrement** — vue par ancienneté, décider des relances
- **Rapports → Annulations** — comprendre pourquoi certaines factures ont été annulées

### Mensuel (2h)
- **Taxes Communes** — payer les taxes dues aux 31 communes
- **Export Excel factures** — envoyer à l'expert-comptable
- **Vérifier les remises appliquées** — dashboard remises, cohérence commerciales/négociations

### Trimestriel
- **Journal complet des versements** — export pour reddition de comptes
- **Audit des factures annulées** — vérifier motifs, éviter les abus

---

## 7. Erreurs classiques à éviter

- **Ne pas enregistrer un versement à la mauvaise date** — utilisez la date de réception réelle, pas la date de saisie
- **Ne pas oublier la référence** sur un versement chèque / virement — indispensable en cas de contentieux
- **Ne pas solder manuellement une facture sans motif** — c'est audité et un contrôle interne posera la question
- **Ne pas laisser les taxes communales s'accumuler** — l'app vous alerte, mais la relance mairie est manuelle
- **Ne pas exporter des données sensibles vers un cloud personnel** — utiliser les canaux internes CIBLE
- **Ne pas modifier une facture verrouillée** — demander à l'admin de déverrouiller, avec justification

---

## 8. FAQ Comptable

**Q : Un client conteste le montant TVA sur sa facture.**
R : Vérifiez la ventilation :
- TVA (18%) sur le HT panneaux + services
- TSP (3%) sur le HT panneaux uniquement (pas services)
- TM et ODP calculés par commune sur la surface × durée
Sur la fiche facture, chaque taxe est détaillée. Si écart, vérifier si les taux étaient différents à la date d'émission (snapshot historique).

**Q : Un versement enregistré est erroné (mauvais montant, mauvaise facture).**
R : Depuis la fiche facture → section Versements → bouton **Modifier** ou **Supprimer** le versement erroné. **Action tracée dans l'audit** — soyez précis dans le motif.

**Q : Comment gérer un paiement partagé sur plusieurs factures ?**
R : Enregistrer un versement par facture concernée, avec la référence commune (ex: "Virement Danone du 15/03 - part 1/3"). Ça permet le rapprochement plus tard.

**Q : Comment savoir si une facture a bien été envoyée au client ?**
R : Fiche facture → historique en bas de page. Vous voyez la date d'envoi + adresse mail utilisée. Si absent, la facture n'a jamais été envoyée (elle est peut-être encore en brouillon).

**Q : Une facture est marquée "annulée" mais je ne vois pas pourquoi.**
R : Fiche facture → section audit trail → l'historique montre qui a annulé et quand. Si le motif est vide, c'est probablement une annulation admin sans commentaire — demander à l'admin.

**Q : Comment reprendre une facture annulée pour la ré-émettre ?**
R : Créer une nouvelle facture (bouton **+ Nouvelle facture**) plutôt que de "réactiver" l'ancienne. Vous pouvez copier les lignes de l'ancienne facture en la sélectionnant comme modèle si l'option est disponible.

**Q : Le fisc me demande un justificatif de facture ancienne.**
R : Menu Facturation → recherche par référence → clic → **Export PDF FNE**. Le PDF est conforme au format exigé par la Direction Générale des Impôts (numérotation continue, IFU émetteur, ventilation TVA + taxes additionnelles).

---

## 9. Points de conformité à connaître

CIBLE émet des factures **FNE (Facture Normalisée Électronique)** conformes au Code Général des Impôts de Côte d'Ivoire :

- **Numérotation continue** — jamais de trou dans la séquence
- **IFU émetteur** — présent sur chaque facture
- **Ventilation TVA** au taux légal en vigueur à la date d'émission
- **Taxes additionnelles** (TSP, TM, ODP) ventilées séparément
- **Conservation légale** — les factures sont archivées de manière immuable
- **Audit trail** — chaque modification est tracée avec auteur + date

En cas d'audit fiscal, l'app fournit toutes les pièces justificatives.

---

## 10. Ressources complémentaires

- Manuel Admin : [00-admin.md](00-admin.md) — pour connaître les actions qui vous sont interdites
- Manuel Commercial : [02-commercial.md](02-commercial.md) — pour comprendre comment vos factures sont créées

---

**Fin du manuel Comptable.** Version 1.0 — 2026-07-20.
