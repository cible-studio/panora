# Manuel Commercial — Panora

> Vous êtes **commercial** chez CIBLE. Ce manuel décrit vos écrans, vos actions, et le cycle de vente que vous pilotez de A à Z pour vos annonceurs.

---

## 1. Votre mission

Le commercial est **le visage de CIBLE** auprès des annonceurs :
- Vous **prospectez et gérez** vos comptes clients
- Vous **construisez les propositions** commerciales (avec l'aide du Media Planner si besoin)
- Vous **suivez vos campagnes** de la signature à la fin de diffusion
- Vous **relancez les factures impayées** de vos dossiers
- Vous êtes évalué sur des KPI objectifs : CA, nouvelles campagnes, taux de recouvrement, panier moyen

**⚠️ Règle importante** : vous voyez uniquement **vos propres dossiers** (ownership). Les autres commerciaux ne voient pas vos réservations, vous ne voyez pas les leurs. Seul l'admin et le Media Planner voient tout.

---

## 2. Votre écran d'ouverture

À la connexion, vous arrivez sur le **Tableau de bord commercial** (`/admin/dashboard`) qui affiche **vos** chiffres :
- **Votre CA HT du mois**
- **Vos nouvelles campagnes créées ce mois**
- **Vos campagnes actives**
- **Vos factures en retard** (à relancer)
- **Votre panier moyen**

Vous voyez aussi votre **classement** parmi les commerciaux (Performance commerciale).

---

## 3. Vos écrans clés

### Principal
- **Tableau de bord** — vos KPI personnels
- **Disponibilités** — pour préparer une proposition (lecture seule des panneaux)
- **Campagnes** — vos campagnes uniquement (filtre automatique)
- **Clients** — vos clients uniquement

### Opérations
- **Réservations** — vos propositions et réservations
- **Piges Photos** — les piges de VOS campagnes (pour transfert au client)
- **Facturation** — vos factures uniquement
- **Messages** — vos échanges clients depuis Panora
- **Alertes** — les événements de vos dossiers

### Analyse
- **Performance commerciale** — votre fiche perso avec votre classement, drill-down par métrique

---

## 4. Vos workflows clés

### 4.1 Ouvrir un dossier avec un nouveau client

**Étape 1 : créer le client**
1. Menu → **Clients** → **+ Nouveau client**
2. Renseigner : nom, IFU (si société), email, téléphone, adresse, secteur
3. **Créer** → le client reçoit ses identifiants pour son espace personnel

**Étape 2 : préparer sa proposition**
1. Menu → **Disponibilités**
2. Filtrer : période visée, commune, format, éclairage, budget indicatif
3. Sélectionner les panneaux qui correspondent au brief
4. Cliquer **Créer une proposition à partir de la sélection**
5. Choisir votre nouveau client
6. Ajuster les prix négociés ligne par ligne (vous pouvez faire des remises tarif catalogue)
7. **Enregistrer et envoyer** → le client reçoit un mail avec un lien unique vers sa proposition

**💡 Astuce** : si vous ne trouvez pas le bon panneau, demandez au **Media Planner** — il a une meilleure vue macro du parc et peut suggérer des alternatives.

### 4.2 Suivre l'état d'une proposition

Une proposition passe par plusieurs états :
- **📤 Envoyée** : le client a reçu le lien, il ne l'a pas encore ouverte
- **👁️ Consultée** : le client a ouvert le lien (vous voyez la date d'ouverture)
- **✅ Signée** : le client a validé — la proposition attend la confirmation admin pour devenir une réservation ferme
- **❌ Refusée** : le client a refusé avec motif
- **🔁 Contre-proposée** : le client a demandé un décalage de dates → à vous d'accepter, refuser ou re-négocier

**💡 Astuce** : si un client tarde à ouvrir votre proposition, cliquer sur **Renvoyer le lien** pour lui rappeler.

### 4.3 Suivre l'exécution d'une campagne

Quand la proposition devient réservation ferme (statut `confirme`), une **campagne** se crée automatiquement.

1. Menu → **Campagnes** → clic sur la campagne
2. Vous voyez :
   - **Progression** — % écoulé, jours restants
   - **Panneaux** — chacun avec son statut de pose (planifié, en route, réalisé)
   - **Piges** — les preuves d'affichage validées par le MP
   - **Facturation** — les factures émises et leur statut de paiement

**💡 Astuce** : partagez régulièrement les piges validées au client. Ça rassure et prépare le renouvellement.

### 4.4 Notifier un client des changements

Si la campagne évolue (panneau ajouté, période prolongée, prix ajusté), il faut informer le client.

1. Fiche campagne → bouton **Notifier le client des changements**
2. Un mail récapitulatif est envoyé automatiquement avec :
   - Les panneaux ajoutés / retirés
   - La nouvelle période (si changée)
   - Le nouveau montant total
3. Le client peut consulter le détail dans son espace

### 4.5 Émettre une facture

**Cas classique** : la campagne démarre → vous facturez le mois 1 (ou la totalité selon négociation).

1. Menu → **Facturation** → **+ Nouvelle facture**
2. Sélectionner le client puis la campagne rattachée (le formulaire pré-remplit les panneaux)
3. Vérifier :
   - Période de facturation
   - Prix par panneau (auto = catalogue, modifiable si vous avez négocié)
   - Ajouter des **services annexes** si applicable (impression, frais de pose)
   - Remise globale éventuelle
4. Vérifier le récapitulatif à droite : TVA (18%), TSP (3%), TM, ODP, Services TTC, **Total à payer**
5. **Créer la facture** → statut initial `brouillon`
6. Sur la fiche : bouton **Envoyer** → la facture passe en `envoyée`, elle est **verrouillée**, et le client reçoit un mail avec la facture PDF FNE

**⚠️ Attention** : une fois envoyée, la facture est verrouillée. Pour la modifier, il faut la déverrouiller — action réservée à l'admin, tracée dans les logs. **Vérifier avant d'envoyer.**

### 4.6 Enregistrer un paiement

Quand le client paie (espèces, chèque, virement…), vous devez enregistrer le versement pour tenir votre recouvrement à jour.

1. Menu → **Facturation** → clic sur la facture concernée
2. Section **Versements enregistrés** → bouton **+ Nouveau versement**
3. Renseigner :
   - Montant reçu
   - Mode de paiement (espèces, chèque, virement, mobile money…)
   - Date du versement
   - Référence (optionnel : n° chèque, référence virement)
   - Type : **acompte** ou **paiement complet**
4. **Enregistrer** → la facture passe automatiquement en :
   - `partiellement soldée` si le total encaissé < total dû
   - `soldée` si le total encaissé ≥ total dû

**💡 Astuce** : si vous acceptez un paiement en 3 fois, vous pouvez enregistrer les 3 versements au fur et à mesure — la facture reste en `partiellement soldée` jusqu'au dernier.

### 4.7 Relancer une facture impayée

1. Menu → **Facturation** → filtrer sur **En retard**
2. Clic sur la facture → bouton **Créer une relance**
3. Choisir le niveau (1re relance, 2e relance, mise en demeure)
4. Le mail est pré-rempli avec le contexte de la facture, vous pouvez ajuster le texte
5. **Envoyer** → l'action est tracée dans l'historique de la facture

**💡 Astuce** : la vue **Finance → Relances** liste toutes vos relances envoyées avec leur suivi.

### 4.8 Consulter votre performance

1. Menu → **Performance commerciale** → sélectionner votre nom
2. Vous voyez :
   - **Votre CA HT et TTC** sur la période choisie
   - **Vos nouvelles campagnes créées**
   - **Vos campagnes actives**
   - **Votre encaissé du mois**
   - **Votre taux de recouvrement**
   - **Votre panier moyen**
   - Votre **classement** parmi l'équipe commerciale
3. Chaque carte KPI est **cliquable** → drill-down vers la liste précise (ex: cliquer sur "8 nouvelles campagnes" ouvre la liste)

**💡 Astuce** : cette vue est aussi vue par votre direction. C'est votre baromètre à surveiller.

---

## 5. Ce que vous pouvez faire

| Action | Autorisée ? |
|---|:-:|
| Créer un client | ✅ |
| Créer une réservation / proposition | ✅ |
| Modifier VOS réservations | ✅ |
| Modifier une réservation d'un autre commercial | ❌ |
| Annuler une réservation | ❌ (à demander au MP ou à l'admin) |
| Confirmer / Refuser une proposition signée | ❌ (admin only) |
| Émettre une facture | ✅ |
| Enregistrer un versement | ✅ |
| Envoyer une relance | ✅ |
| Déverrouiller une facture envoyée | ❌ 🔒 (admin only) |
| Créer / modifier un panneau | ❌ (voir avec MP ou admin) |
| Assigner un technicien à une pose | ❌ (MP only) |
| Valider / rejeter une pige photo | ❌ (MP only) |
| Créer / clôturer une maintenance | ❌ (MP only) |
| Voir les dossiers des autres commerciaux | ❌ (confidentialité) |

---

## 6. Signaux à surveiller

Chaque matin, en 5 minutes :

1. **Dashboard → Factures en retard** — relancer sans attendre 30 jours
2. **Alertes** — nouveauté sur vos dossiers (poses en retard, pige rejetée, signalement…)
3. **Confirmations en attente** — vos propositions signées attendent le feu vert admin (relancer si > 24h)
4. **Piges Photos** — vérifier que les piges de vos campagnes sont validées et envoyées aux clients

Chaque semaine :
5. **Performance commerciale** — regarder votre classement et vos KPI

---

## 7. Erreurs classiques à éviter

- **Ne pas envoyer une facture sans avoir vérifié le total** — une fois envoyée, il faut demander à l'admin de la déverrouiller
- **Ne pas oublier d'enregistrer un versement reçu** — sinon la facture reste "en retard" alors que le client a payé
- **Ne pas modifier une réservation confirmée sans notifier le client** — cliquer **Notifier le client des changements** après chaque modif significative
- **Ne pas créer un doublon de client** — si le client existe déjà, retrouver sa fiche
- **Ne jamais transmettre vos identifiants Panora à qui que ce soit** — vos actions engagent votre responsabilité

---

## 8. FAQ Commercial

**Q : Un client me demande de voir sa campagne en direct, comment faire ?**
R : Deux options :
1. Il a déjà ses identifiants — il peut se connecter sur `/client/login` avec son email
2. Sinon, depuis la fiche client, bouton **Réenvoyer l'accès** → il reçoit un lien pour définir son mot de passe

**Q : J'ai fait une remise commerciale, comment la documenter ?**
R : Sur la ligne de facturation, ajustez le prix négocié (PU HT/mois) — le calculator affiche le vrai montant. Vous pouvez aussi ajouter une **remise globale** en % qui s'applique à l'ensemble.

**Q : Mon client refuse de payer parce qu'un panneau était vandalisé pendant sa campagne.**
R : C'est un cas commercial délicat. Voir avec l'admin :
1. Vérifier dans **Signalements** que le vandalisme a bien été reporté par le technicien
2. Décider avec la direction s'il y a une remise commerciale (ex: -10% sur ce panneau pour la période concernée)
3. Créer un avoir ou modifier la facture (nécessite déverrouillage admin)

**Q : Comment savoir si une facture est effectivement envoyée au client ?**
R : Fiche facture → historique en bas de page. Vous voyez la date d'envoi et le mail utilisé.

**Q : Un client demande un devis avec plusieurs options (pack A, pack B).**
R : Créer plusieurs propositions distinctes pour le même client, avec des noms clairs (ex: "Renault - Lancement Duster - Pack Abidjan", "Renault - Lancement Duster - Pack National"). Le client peut signer celle qui lui convient.

**Q : Je ne vois pas une réservation d'un client que je gère.**
R : Vérifier que vous êtes bien assigné comme `commercial_user_id` sur cette réservation. Sinon demander à l'admin ou au MP de vous assigner (peut arriver si la réservation a été créée par quelqu'un d'autre).

---

## 9. Bonus : votre discours commercial

Argumentaire à utiliser face à un annonceur qui hésite :

- **« Vous voyez ce qu'on affiche »** — nos piges photos horodatées vous prouvent que votre affichage est en place. Pas d'excuse "ah j'ai pas vu le panneau" — on vous envoie la photo prise sur place.
- **« Vous suivez en temps réel »** — votre espace client dédié montre l'avancement de la campagne jour par jour.
- **« On vous facture ce qu'on affiche »** — si un panneau n'est pas posé, vous ne le payez pas. Notre plateforme le trace strictement.
- **« Vous avez notre historique »** — 30 ans à porter les grandes marques ivoiriennes (Danone, SIPRA, Moov, banques…).

---

## 10. Ressources complémentaires

- Manuel Media Planner : [01-mediaplanner.md](01-mediaplanner.md) — pour comprendre à qui déléguer certaines demandes
- Manuel Admin : [00-admin.md](00-admin.md) — pour connaître les limites de ce que vous pouvez / ne pouvez pas faire
- Manuel Client : [05-client.md](05-client.md) — pour savoir ce que voit votre client dans son espace

---

**Fin du manuel Commercial.** Version 1.0 — 2026-07-20.
