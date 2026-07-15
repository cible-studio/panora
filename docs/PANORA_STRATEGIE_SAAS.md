# Panora — Devenir un SaaS multi-régies

> **Document de travail — à discuter en réunion**
> Rédigé le 08/07/2026 · Version 1.0
> Objet : présenter la vision globale pour transformer Panora d'outil interne CIBLE
> en produit commercialisé auprès d'autres régies OOH.

---

## 1. Résumé en 5 lignes

Aujourd'hui, Panora est un outil interne utilisé par CIBLE CI pour gérer ses 337 panneaux.
Nous voulons le proposer à d'autres régies d'affichage OOH d'Afrique de l'Ouest,
en mode **SaaS** (Software as a Service — logiciel loué en ligne, comme Gmail ou WhatsApp Business).
Chaque régie cliente aura son propre espace Panora, isolé et sécurisé.
Nous (CIBLE) devenons **éditeur du logiciel** en plus de l'utiliser nous-mêmes.

**Décisions à prendre en réunion :**
1. On y va ou pas ?
2. Combien on fait payer ?
3. Qui pilote côté CIBLE (commercial, support, dev) ?

---

## 2. Ce qui existe aujourd'hui vs ce qu'on veut demain

### Situation actuelle

- **1 seule régie utilisatrice** : CIBLE CI
- **1 seule adresse web** : dev.panora-cible.com
- **1 seule base de données** : celle de CIBLE
- Personne d'autre ne peut utiliser Panora sans une installation dédiée coûteuse

### Situation cible (SaaS)

- **N régies utilisatrices** (2, 5, 20, 50…)
- **Chaque régie a sa propre adresse** :
  - Simple : `cible.panora.io`, `regie-nom.panora.io`, `autre.panora.io`
  - Ou personnalisée : `panora.regie-abc.com` si la régie a acheté son domaine
- **Chaque régie a sa propre base de données** — impossible qu'une régie voie
  les données d'une autre
- **1 seul code source à maintenir** — quand on améliore Panora, tout le monde
  en profite au prochain matin

---

## 3. Analogie simple : Panora = Netflix pour régies OOH

Imaginez Netflix :

- Il y a **UN seul site** : netflix.com
- **Chaque abonné** a son propre compte, sa propre liste, ses propres films en cours
- Netflix ne mélange jamais votre compte avec celui du voisin
- Netflix fait payer un abonnement mensuel selon le forfait choisi
- Netflix améliore le site chaque semaine sans que vous ayez à installer quoi que ce soit

**Panora, ce sera pareil** :
- **UN seul système** : Panora
- **Chaque régie** a son propre espace, ses panneaux, ses factures, ses clients
- Aucun mélange entre régies
- Chaque régie paie un abonnement mensuel selon sa taille
- On améliore Panora en continu — toutes les régies clientes en profitent

---

## 4. Comment ça fonctionne dans la vraie vie

### Vu par une nouvelle régie cliente ("Régie X")

**Jour 1** — Le directeur de Régie X visite `panora.io`, voit la vitrine,
remplit le formulaire "Demander une démo".

**Jour 3** — On fait une démo visio de 30 minutes. Il aime, il signe.

**Jour 5** — On lui crée son espace Panora. Il reçoit un email :
> Bonjour, votre espace Panora est prêt.
> Adresse : regie-x.panora.io
> Identifiant : direction@regie-x.com
> Mot de passe temporaire : XYZ (à changer à la première connexion)

**Jour 7-15** — Import de ses données (panneaux, clients, campagnes en cours)
depuis ses fichiers Excel. Formation de son équipe : les admins d'un côté,
les techniciens terrain de l'autre.

**Jour 20** — Régie X est en production. Chaque mois, elle reçoit une facture
Panora automatique. On voit son usage en temps réel de notre côté.

**Après 6 mois** — Elle a 80 panneaux. Elle est passée du forfait Starter au Pro.
Elle nous a recommandé à 2 autres régies.

### Vu par nous (CIBLE éditeur de Panora)

On a maintenant **deux casquettes** :

**Casquette 1 : Utilisateur de Panora** (comme avant)
- On utilise Panora pour gérer NOS 337 panneaux
- Rien ne change dans notre quotidien de régie

**Casquette 2 : Éditeur de Panora** (nouveau)
- On a un tableau de bord dédié où on voit :
  - La liste de nos régies clientes
  - Combien on facture chaque mois
  - Qui utilise beaucoup, qui utilise peu
  - Les demandes de support
  - Les factures Panora à envoyer

---

## 5. Le tableau de bord "éditeur Panora" (le nouveau super-admin)

C'est notre outil de pilotage du business. Ce qu'on y verra :

### Vue d'ensemble

```
Panora — Tableau de bord éditeur                    Juillet 2026

┌────────────────────────────────────────────────────────────┐
│  📊 CHIFFRES CLÉS DU MOIS                                  │
├────────────────────────────────────────────────────────────┤
│  Régies actives          : 12                              │
│  En période d'essai      : 3                               │
│  Nouvelles ce mois       : 2                               │
│  Départs ce mois         : 0                               │
│                                                            │
│  Panneaux gérés total    : 2 148                           │
│  Utilisateurs actifs     : 87                              │
│  Techniciens sur le      : 34                              │
│  terrain aujourd'hui                                       │
│                                                            │
│  💰 Chiffre d'affaires Panora                              │
│    Ce mois          : 780 000 FCFA                        │
│    Mois précédent   : 645 000 FCFA (+21%)                 │
│    Impayés          : 45 000 FCFA (1 régie)               │
└────────────────────────────────────────────────────────────┘
```

### Liste des régies clientes

Pour chaque régie, on voit :
- Nom, ville, contact principal
- Forfait actuel (Starter, Pro, Business)
- Nombre de panneaux, nombre d'utilisateurs
- Dernière connexion (aujourd'hui, hier, il y a 15 jours…)
- Statut : Actif / Impayé / En essai / Suspendu
- Bouton pour : voir les détails, contacter, suspendre, changer forfait

### Alertes automatiques qu'on reçoit

- 🟢 "Régie X a créé 15 nouvelles campagnes cette semaine" (client actif, tout va bien)
- 🟡 "Régie Y ne s'est pas connectée depuis 20 jours" (risque de départ, l'appeler)
- 🔴 "Régie Z n'a pas payé sa facture depuis 30 jours" (relance urgente)
- 🟠 "Régie W approche sa limite de 50 panneaux (48/50)" (opportunité de vente en amont)

---

## 6. Options d'hébergement pour les régies clientes

Toutes les régies n'ont pas les mêmes besoins ni les mêmes moyens. On propose 3 formules.

### Option 1 — Sous-domaine Panora (par défaut, gratuit)

La régie a une adresse comme `regie-abc.panora.io`. C'est le plus simple.

- ✅ Rien à faire techniquement pour la régie
- ✅ Marche immédiatement, sécurité (HTTPS) automatique
- ✅ Compris dans tous les forfaits
- ⚠️ Le nom "panora.io" est visible dans l'adresse

### Option 2 — Domaine personnalisé (payant)

La régie a acheté son domaine (par exemple `app.regie-abc.com`) et veut l'utiliser
pour se présenter sous sa propre marque.

- ✅ La régie garde son identité visuelle
- ✅ Ses employés voient le nom de leur entreprise dans l'adresse
- ⚠️ Nécessite une petite configuration technique (5 minutes de notre côté)
- 💰 Facturé en supplément (ex : +10 000 FCFA/mois ou frais setup one-shot)

### Option 3 — Installation dédiée (sur mesure, cas exceptionnels)

Cas rare : une très grosse régie ou une administration qui exige d'héberger
Panora sur ses propres serveurs pour raisons de sécurité.

- ✅ Contrôle total pour le client
- ⚠️ Coûte cher (installation + maintenance mensuelle)
- ⚠️ Ne bénéficie pas des mises à jour automatiques (on doit les faire manuellement)
- 💰 Contrat sur mesure (500 000 FCFA setup + 100 000/mois par exemple)

**Notre recommandation** : commencer avec l'Option 1 (sous-domaine) pour toutes
les régies. Proposer l'Option 2 après 3-6 mois d'usage. Ne faire l'Option 3
que pour des contrats importants (5+ millions par an).

---

## 7. Combien on peut faire payer ?

**On n'a PAS encore décidé** — c'est LA question à trancher en réunion.
Voici les pistes.

### Piste A — Prix par taille de régie (recommandée)

| Forfait | Nombre de panneaux | Prix mensuel |
|---|---|---|
| **Découverte** | 1 à 20 panneaux | 15 000 FCFA/mois |
| **Starter** | 21 à 80 panneaux | 35 000 FCFA/mois |
| **Pro** | 81 à 250 panneaux | 65 000 FCFA/mois |
| **Business** | 251 à 500 panneaux | 100 000 FCFA/mois |
| **Enterprise** | +500 panneaux | Sur devis |

Chaque forfait comprend :
- Utilisateurs illimités (admins, commerciaux, techniciens)
- Tous les modules (facturation FNE, taxes, terrain mobile, rapports)
- Sauvegardes quotidiennes
- Support par email/WhatsApp
- Mises à jour automatiques

**Options en supplément** :
- Domaine personnalisé : +10 000 FCFA/mois
- Formation supplémentaire : 50 000 FCFA/session
- Import de données historiques : 100 000 FCFA one-shot
- Support prioritaire (réponse < 4h) : +20 000 FCFA/mois

### Piste B — Forfait unique tout inclus

Un seul prix pour toutes les régies : 50 000 FCFA/mois par exemple.

- ✅ Très simple à comprendre et à vendre
- ⚠️ Une petite régie de 15 panneaux paierait autant qu'une grosse de 300
- ⚠️ Rentabilité limitée sur les gros clients

### Piste C — Sur devis systématique

Chaque régie négocie son prix. Pas de tarif public.

- ✅ Maximisation par client
- ⚠️ Cycle de vente plus long
- ⚠️ Difficile à mettre en avant sur la landing

**Notre reco : Piste A** (par taille), simple à vendre, aligné sur la valeur perçue.

### Simulation de revenus potentiels

Avec la Piste A :

| Scénario | Nb régies | Répartition | CA mensuel Panora |
|---|---|---|---|
| **6 mois** | 5 régies | 2 Starter + 2 Pro + 1 Découverte | 195 000 FCFA/mois |
| **1 an** | 12 régies | 3 Découverte + 5 Starter + 3 Pro + 1 Business | 515 000 FCFA/mois |
| **2 ans** | 25 régies | Mix avec 2 Business + 8 Pro + 12 Starter + 3 Découverte | 1 155 000 FCFA/mois |

Soit environ **14 M FCFA/an** à 2 ans, si on atteint 25 régies clientes.

---

## 8. Ce qu'il faut mettre en place

### Côté technique (dev)

**Phase 1 : Préparation (2-3 semaines)**
- Adapter Panora pour supporter plusieurs régies isolées
- Créer le tableau de bord éditeur (super-admin)
- Système de création automatique d'un nouvel espace régie

**Phase 2 : Migration CIBLE (1 semaine)**
- CIBLE CI devient officiellement la "première régie cliente" (interne)
- Aucun impact sur l'usage actuel, tout continue de fonctionner

**Phase 3 : Onboarding automatisé (2 semaines)**
- Outil d'import Excel → Panora
- Emails automatiques d'accueil / activation

**Phase 4 : Facturation automatique (2 semaines)**
- Générer les factures Panora aux régies clientes chaque mois
- Suivi des paiements (virement, mobile money, Wave…)
- Suspension automatique en cas d'impayé prolongé

**Total dev : 6 à 8 semaines** avec une personne dédiée à temps plein.

### Côté business (équipe)

À minima :
- **1 commercial** pour prospecter et faire les démos (peut être un rôle partiel)
- **1 personne support** pour répondre aux clients (peut être partagée au début)
- **1 personne technique** pour l'onboarding et le développement continu

Au fur et à mesure de la croissance, ces rôles peuvent devenir des postes à temps plein.

### Côté opérationnel

- Décider **un nom commercial définitif** pour le produit
  (`Panora` ? autre chose ? domaine à réserver)
- Créer les documents commerciaux :
  contrat type, CGV, politique de confidentialité, RGPD
- Ouvrir un compte de paiement dédié (mobile money, virement, éventuellement carte)
- Numéro WhatsApp Business dédié au support Panora

---

## 9. Ce qu'on doit faire attention

### Risques identifiés

- **Support client chronophage** : chaque nouvelle régie = plus de questions,
  plus de bugs à corriger. Prévoir un temps dédié dès le début.
- **Impayés** : certaines régies peuvent avoir des difficultés de trésorerie.
  Prévoir des procédures claires (relance à 15 jours, suspension à 30 jours).
- **Concurrence** : au moins un concurrent identifié (iOOH) sur le marché.
  Il faut communiquer sur nos avantages (produit mature, FNE conforme, terrain PWA).
- **Charge sur les serveurs** : au fur et à mesure des régies, il faut monter
  en puissance. Prévoir un budget infrastructure.

### Sécurité et responsabilité

- Chaque régie nous confie ses données commerciales sensibles (clients, tarifs, CA).
  Notre responsabilité en cas de fuite est engagée.
- Prévoir : sauvegardes quotidiennes, chiffrement, audit trail complet (déjà en place),
  contrat clair sur les responsabilités mutuelles.

---

## 10. Planning proposé

```
Mois 1        Préparation technique + finalisation landing publique
Mois 2        Migration CIBLE en tenant #1 + super-admin de base
Mois 3        Onboarding automatisé + facturation Panora
Mois 4        Lancement — 1er client externe (pilote)
Mois 5-6      Ajustements selon retour pilote, 2-3 clients de plus
Mois 7-12     Croissance organique, viser 10 clients à 1 an
Année 2       Expansion Afrique de l'Ouest, viser 25 clients
```

---

## 11. Questions à trancher en réunion

Chaque question a besoin d'une décision explicite avant qu'on aille plus loin.

**Sur le principe :**
1. On y va ou pas ? Est-ce que la direction valide de faire de Panora un produit commercial ?
2. Quel budget on alloue au développement des 3-4 prochains mois ?
3. Qui pilote côté CIBLE : commercial, support, dev, direction produit ?

**Sur le produit :**
4. Nom définitif : on garde "Panora" ou on cherche autre chose ?
5. Domaine à acheter : `panora.io`, `panora.africa`, `tryPanora.com`, autre ?
6. Tarification : Piste A (par taille), B (forfait unique), ou C (devis) ?
7. Ciblage géographique : Côte d'Ivoire d'abord ? Toute la CEDEAO ?

**Sur l'opérationnel :**
8. Combien de démos par semaine on est prêt à faire ?
9. Comment on prend les paiements (virement seul ? Wave ? Orange Money ? carte ?) ?
10. Quel niveau de support on garantit (heures d'ouverture, délai de réponse) ?
11. Prêt à recruter une personne dédiée support si on dépasse 10 clients ?

**Sur la stratégie de vente :**
12. Comment on trouve nos premiers prospects (bouche à oreille, publicité,
    prospection directe, salons professionnels) ?
13. Quel argumentaire commercial mettre en avant (produit mature ? conformité fiscale ?
    espace tech mobile ? prix compétitif ?) ?
14. Comment on gère le lien avec CIBLE (le fait que Panora sort de chez nous
    est-il un plus ou un frein pour convaincre nos "concurrents" locaux) ?

---

## 12. Ce qui est déjà fait

- ✅ Panora est en production, éprouvé quotidiennement sur 337 panneaux
- ✅ Landing publique v1 disponible en aperçu sur `dev.panora-cible.com/decouvrir`
- ✅ Formulaire de demande de démo fonctionnel (envoie vers `studio@cible-ci.com`)
- ✅ Toute la partie fonctionnelle de Panora fonctionne (dashboard, campagnes,
  facturation FNE, taxes communales, espace tech mobile PWA, rapports)

## 13. Prochaine étape immédiate proposée

**Attendre la validation de ce document en réunion**, puis :

1. Si validé sur le principe → on commence la Phase 1 (préparation technique)
2. Si non validé → on garde Panora en interne uniquement, on ferme la landing
3. Si à approfondir → on organise un atelier stratégique dédié

---

## Annexe — Glossaire pour non-tech

- **SaaS** (Software as a Service) : logiciel qu'on ne télécharge pas, qu'on utilise
  directement dans son navigateur web. On paie un abonnement mensuel au lieu d'acheter
  le logiciel une fois. Exemples : Gmail, Slack, WhatsApp Business, Netflix.

- **Tenant** : mot technique qui désigne "un client dans un SaaS". Chaque régie
  cliente = un tenant. Chaque tenant a son propre espace isolé.

- **Multi-tenant** : un logiciel capable de gérer plusieurs clients en même temps
  sans qu'ils se voient les uns les autres. C'est ce vers quoi on veut aller.

- **Sous-domaine** : partie d'une adresse web avant le nom principal.
  Dans `cible.panora.io`, "cible" est le sous-domaine, "panora.io" est le domaine.

- **CNAME** : configuration technique pour faire pointer un domaine
  vers un autre (utilisé pour l'Option 2 domaine personnalisé).

- **Onboarding** : ensemble des étapes d'accueil d'un nouveau client
  (création de compte, formation, import de données, mise en route).

- **Churn** : quand un client arrête d'utiliser le service. Un taux de churn faible
  est un signe de bonne santé du business.

- **MRR** (Monthly Recurring Revenue) : chiffre d'affaires mensuel récurrent.
  C'est LE KPI clé d'un SaaS : la somme que le business génère de manière prévisible
  chaque mois grâce aux abonnements.

---

**Fin du document. En attente de discussion.**
