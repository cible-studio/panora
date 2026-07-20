# Manuel Administrateur — Panora

> Vous êtes **administrateur** de Panora. Ce manuel décrit tout ce que vous pouvez faire, dans quel ordre, et les points de vigilance de votre rôle.

---

## 1. Votre mission

En tant qu'administrateur, vous êtes **la source d'autorité** de la plateforme :
- Vous validez ou débloquez les décisions structurantes (annulations sensibles, ajustements financiers, gestion des accès)
- Vous suivez l'ensemble de l'activité (KPI globaux, alertes, performance des équipes)
- Vous êtes le seul à pouvoir **supprimer** des données ou déverrouiller une facture émise
- Vous gérez la configuration technique (utilisateurs, tarifs communes, catégories de panneaux, régies externes)

Aucun rôle n'a plus de droits que vous.

---

## 2. Votre écran d'ouverture

À la connexion, vous arrivez sur le **Tableau de bord** (`/admin/dashboard`).

### Ce que vous y voyez

- **8 KPI en tête de page** — panneaux actifs, disponibles, CA mensuel, encaissements, factures en retard, reste à recouvrer, prévision 30j, alertes en cours
- **Confirmations en attente** — propositions signées par le client qui attendent votre validation finale
- **Top 10 clients** — les annonceurs qui contribuent le plus au CA
- **Top 10 communes** — la répartition géographique de votre revenu
- **Alertes critiques** — poses en retard, panneaux en maintenance prolongée, factures échues
- **Campagnes actives** — la liste des campagnes en cours avec leur avancement
- **Maintenance** — les panneaux qui nécessitent une intervention

**💡 Astuce** : chaque chiffre du dashboard est **cliquable** — un clic vous emmène directement à la liste concernée (ex: cliquer sur "8 factures en retard" ouvre la liste filtrée sur ces 8 factures).

---

## 3. Le menu latéral

Voici l'ensemble des sections accessibles à un administrateur, dans l'ordre du menu :

### Principal
- **Tableau de bord** — vos KPI d'ouverture (décrit ci-dessus)
- **Disponibilités** — recherche de panneaux libres à une date donnée
- **Inventaire** — la fiche de chaque panneau (photos, GPS, prix, historique)
- **Campagnes** — la vie de chaque campagne (statuts, poses, piges, factures liées)
- **Clients** — l'annuaire de vos annonceurs
- **Régies externes** — les partenaires qui vous louent leurs panneaux

### Opérations
- **Réservations** — propositions envoyées aux clients + engagements confirmés
- **Gestion Pose OOH** — la vue macro de toutes les poses (en retard, planifiées, terminées)
- **Pilotage terrain** — vision temps réel de l'activité de vos techniciens
- **Piges Photos** — validation / rejet des preuves d'affichage envoyées par les techniciens
- **Taxes Communes** — suivi des taxes ODP + TM à payer par commune
- **Facturation** — cycle complet FNE : brouillon → envoyée → soldée
- **Messages** — communication interne avec vos clients depuis Panora
- **Alertes** — flux hiérarchisé des événements de la plateforme
- **Conflits** — signalements de collision entre deux réservations
- **Signalements** — remontées terrain des techniciens (panneau dégradé, accès bloqué…)

### Analyse
- **Carte & Heatmap** — visualisation géographique de votre parc
- **Rapports** — exports comptables, opérationnels, disponibilités
- **Performance commerciale** — classement + KPI drill-down par commercial
- **Performance techniciens** — même chose côté terrain
- **Performance équipes** — vision agrégée par équipe
- **Finance** — encaissements, relances, recouvrement

### Terrain
- **Sécurité** — journal d'audit des actions sensibles

---

## 4. Vos workflows clés

### 4.1 Créer un nouveau client / annonceur

1. Menu → **Clients** → bouton **+ Nouveau client**
2. Remplir le formulaire : nom, email, téléphone, IFU (si société), adresse
3. Cliquer **Créer**
4. Le client est immédiatement disponible pour créer une réservation ou une campagne

**⚠️ Attention** : l'email est utilisé pour lui envoyer ses identifiants d'accès à son espace client. Vérifiez qu'il est correct avant de valider.

### 4.2 Valider une proposition signée par un client

Quand un client signe sa proposition sur son espace, elle apparaît dans **Confirmations en attente** sur votre dashboard.

1. Clic sur la ligne de la proposition
2. Vérifier la période, les panneaux, le montant total
3. Deux options :
   - **✅ Confirmer** : la proposition devient une réservation ferme, les panneaux sont bloqués, la campagne peut être planifiée
   - **❌ Refuser** : la proposition est rejetée avec motif obligatoire (envoyé au client par email)

**💡 Astuce** : vous pouvez aussi **contre-proposer** un décalage de dates si les panneaux ne sont plus disponibles à la période demandée.

### 4.3 Créer et suivre une campagne

Une campagne se crée automatiquement quand une réservation est confirmée. Pour la piloter :

1. Menu → **Campagnes** → clic sur la campagne concernée
2. Vue **Fiche campagne** — vous voyez :
   - Informations (client, période, montant total, réservation source, commercial)
   - Progression (`X% écoulé, Y jours restants`)
   - Écart de facturation détecté si le montant facturé diverge du montant attendu
   - Facturation liée (toutes les factures émises)
3. Actions disponibles selon le statut :
   - **En cours** : Mettre en pause · Terminer · Annuler · Prolonger
   - **Planifiée** : Démarrer · Modifier · Supprimer
   - **Terminée** : Renommer (modal dédié) · Consulter uniquement

### 4.4 Émettre une facture FNE

1. Menu → **Facturation** → **+ Nouvelle facture**
2. Sélectionner le **client** puis (optionnel) la **campagne** rattachée
3. Vérifier la date d'émission (auto = aujourd'hui, modifiable)
4. **Lignes de facturation** :
   - Cliquer sur chaque ligne → sélectionner le panneau dans le Select2 (le panneau disparaît des lignes suivantes pour éviter les doublons)
   - Le prix mensuel unitaire (PU) s'auto-remplit depuis le catalogue, modifiable si vous avez négocié
   - La quantité reste à 1 (un panneau physique = une ligne)
   - Renseigner la durée en mois (0.5, 1, 1.5, 2…)
5. **Services annexes** (optionnel) : ajouter Impression, Frais de pose, etc.
6. **Remise globale** (optionnel) : pourcentage sur le HT panneaux
7. Vérifier le récapitulatif à droite : HT, TVA, TSP, TM, ODP, Services TTC, **Total à payer**
8. **Créer la facture** → statut initial : `brouillon`
9. Sur la fiche facture :
   - **Envoyer** → statut `envoyée`, verrouillage automatique, mail au client
   - **Solder manuellement** si paiement hors plateforme
   - **Enregistrer un versement** (espèces, chèque, virement…) pour paiements en plusieurs fois

**⚠️ Attention** :
- Une facture **envoyée** est verrouillée. Pour la modifier, il faut la **déverrouiller** — action tracée dans les logs.
- Les tarifs communaux (ODP, TM) sont figés à la date d'émission (snapshot). Si vous émettez le 05/03/2026, la facture utilisera les taux en vigueur ce jour, même si vous les modifiez plus tard.

### 4.5 Annuler une réservation

1. Menu → **Réservations** → clic sur la réservation à annuler
2. Sur la fiche, bouton **🚫 Annuler la réservation** (si le statut le permet — `en_attente` ou `confirme`)
3. Modal : choisir la **catégorie d'annulation** (obligatoire pour audit)
4. Précisions optionnelles dans le textarea
5. **Confirmer l'annulation** → les panneaux sont libérés, un mail est envoyé au client, la réservation passe en `annulée`

**⚠️ Attention** : action irréversible. Si vous avez une campagne active liée à cette réservation, sa vie continue mais elle n'a plus d'ancrage engagement — traiter en concertation avec le commercial du dossier.

### 4.6 Consulter les alertes

Le bouton **🔔** en haut à droite affiche un compteur des alertes non lues. Cliquer dessus vous emmène sur `/admin/alerts`.

Niveaux d'alerte :
- **🔴 Danger** — action immédiate requise (pose en retard, panneau vandalisé signalé)
- **🟠 Avertissement** — à surveiller (échéance proche, maintenance prolongée)
- **🔵 Information** — pour information (utilisateur modifié, campagne prolongée)

Actions par lot : marquer lues · archiver · supprimer.

### 4.7 Gérer les utilisateurs

1. Menu → **Utilisateurs** (dans la section admin technique)
2. **+ Nouvel utilisateur** : email, mot de passe temporaire, rôle
3. Rôles disponibles :
   - **Administrateur** — accès total (comme vous)
   - **Media Planner** — création/modification résa, gestion terrain
   - **Commercial** — ses propres dossiers uniquement
   - **Comptable** — vue lecture large + saisie versements
   - **Technicien** — PWA mobile uniquement (poses + piges)

**💡 Astuce** : à la création, l'utilisateur reçoit un mail avec un lien de définition de mot de passe.

### 4.8 Suivre les taxes communales

1. Menu → **Taxes Communes**
2. Vue mensuelle par défaut (paramétrable : trimestriel, annuel, personnalisé)
3. KPI en tête : ODP théorique · TM théorique · Grand total · Déjà payé · Solde restant · Taux de couverture · Communes soldées · Communes partielles
4. Répartition géographique : Abidjan (11 communes) vs Intérieur (20 villes)
5. Top 5 communes les plus taxées
6. Tableau détaillé par commune avec bouton **Payer** ou **Historique**

**⚠️ À faire mensuellement** : payer les taxes dues aux mairies avant échéance légale (varie selon commune).

---

## 5. Ce que vous seul pouvez faire

Actions verrouillées aux autres rôles, réservées à l'administrateur :

| Action | Effet |
|---|---|
| **Déverrouiller une facture envoyée** | Permet de modifier une facture après émission (tracé dans l'audit) |
| **Supprimer un utilisateur** | Retire l'accès à Panora, préserve l'historique de ses actions |
| **Modifier les tarifs communaux** | Bascule les taux ODP/TM d'une commune (impact snapshots futurs) |
| **Créer / supprimer une catégorie de panneau** | Change la classification du parc |
| **Annuler une facture émise** | Passe la facture en `annulée` (les panneaux redeviennent facturables) |
| **Fusionner deux clients doublons** | Consolide leur historique en un seul dossier |
| **Accéder au journal d'audit complet** | Voir toutes les modifications sensibles avec auteur + date |

---

## 6. Signaux à surveiller quotidiennement

Chaque matin, en 5 minutes, faire le tour de :

1. **🔔 Alertes non lues** — traiter les danger en priorité
2. **Dashboard → Factures en retard** — relancer si > 30 jours
3. **Dashboard → Poses en retard** — vérifier avec le MP pourquoi (technicien absent, matos manquant…)
4. **Dashboard → Confirmations en attente** — traiter les propositions signées par les clients
5. **Signalements → À traiter** — décider si maintenance ou dismiss

---

## 7. Erreurs classiques à éviter

- **Ne pas supprimer un client** si vous voulez garder son historique — préférez **désactiver** (menu action sur la fiche)
- **Ne pas modifier une facture verrouillée à la légère** — chaque modification est tracée nominativement
- **Ne pas ajouter un panneau en base** sans renseigner son format, sa commune et son prix mensuel — sinon il est inutilisable en facturation
- **Ne pas oublier de payer les taxes communales** avant l'échéance mensuelle — l'app vous alerte mais la relance mairie est manuelle
- **Ne jamais partager votre mot de passe** — chaque utilisateur doit avoir le sien pour la traçabilité

---

## 8. FAQ Admin

**Q : Un commercial n'a plus accès à un dossier qu'il avait avant.**
R : Vérifier qu'il est bien `commercial_user_id` sur la réservation. Le commercial voit uniquement ses propres dossiers (ownership).

**Q : Une facture affiche un total différent de la réservation.**
R : C'est probablement le message "Écart de facturation détecté" sur la fiche campagne. Vérifier la période, les panneaux ajoutés/retirés, les remises appliquées. La bannière orange donne le détail.

**Q : Un client dit qu'il n'a pas reçu son espace.**
R : Menu Clients → sa fiche → bouton **Réenvoyer l'accès**. Vérifier aussi son adresse email.

**Q : Une pose apparaît en retard mais elle est faite.**
R : Le technicien a oublié de valider dans l'app mobile. Ouvrir la pose → l'assigner manuellement à `réalisée` avec date rétroactive. **Ne pas oublier d'ajouter la pige photo** sinon la campagne ne peut pas être facturée proprement.

**Q : Comment donner accès admin à un collaborateur ?**
R : Utilisateurs → sa fiche → changer son rôle en `Administrateur`. À faire avec parcimonie — c'est un accès total.

---

## 9. Ressources complémentaires

- Manuels des autres rôles : voir [README.md](README.md)
- Documentation technique : `docs/POSE_WORKFLOW.md`, `docs/KIT_HARMONISATION_PANORA.md`
- Support éditeur : `studio@cible-ci.com`

---

**Fin du manuel Administrateur.** Version 1.0 — 2026-07-20.
