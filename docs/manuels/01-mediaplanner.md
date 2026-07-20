# Manuel Media Planner — Panora

> Vous êtes **Media Planner** chez CIBLE. Ce manuel décrit vos écrans, vos actions et le pilotage de la production que vous portez au quotidien.

---

## 1. Votre mission

Le Media Planner est **la colonne vertébrale de la production** :
- Vous **construisez les propositions** que les commerciaux envoient aux clients
- Vous **gérez le parc de panneaux** (fiche, disponibilités, maintenance)
- Vous **planifiez les poses terrain** et assignez les techniciens
- Vous **validez ou rejetez les piges photos** envoyées par les techniciens
- Vous **traitez les signalements** remontés du terrain
- Vous êtes autorisé à **annuler une réservation** même après signature client

Vous avez accès à toutes les réservations et campagnes (pas juste les vôtres), pour assurer la consolidation production.

---

## 2. Votre écran d'ouverture

À la connexion, vous arrivez sur le **Tableau de bord** avec les mêmes KPI que l'admin, mais **certaines actions sont masquées** (suppression d'utilisateur, configuration technique).

**Ce qui vous concerne le plus dans le dashboard** :
- **Poses en retard** — panneaux qui devaient être posés mais qui ne le sont pas
- **4 poses réalisées sans pige** — situations à corriger d'urgence (pas facturable)
- **Signalements à traiter** — remontées terrain qui attendent votre arbitrage
- **Alertes danger** — maintenance prolongée, poses en retard

---

## 3. Vos écrans clés

### Principal
- **Disponibilités** — recherche multi-critères de panneaux libres (votre outil principal en amont de proposition)
- **Inventaire** — la fiche complète de chaque panneau
- **Campagnes** — la vie des campagnes actives et terminées
- **Régies externes** — les panneaux des partenaires

### Opérations
- **Réservations** — propositions et réservations (toutes, pas seulement les vôtres)
- **Gestion Pose OOH** — votre outil central de pilotage terrain
- **Pilotage terrain** — vision temps réel de l'activité des techniciens
- **Piges Photos** — validation des preuves d'affichage

### Analyse
- **Rapports** — vue macro sur la production
- **Performance techniciens** — le classement de vos équipes terrain

---

## 4. Vos workflows clés

### 4.1 Construire une proposition (le flux commercial part de vous)

Souvent, un commercial vous fait un brief oral ou par message : « Le client X veut 15 panneaux à Cocody + Plateau sur avril, budget 5M FCFA ». À vous de traduire ça en proposition solide.

1. Menu → **Disponibilités**
2. Renseigner :
   - **Période** — du 01/04/2026 au 30/04/2026 (par exemple)
   - **Commune** — Cocody + Plateau
   - **Format** — filtrer si le brief est précis (4×3m, panoramique, lumipub…)
   - **Catégorie** — chevalet, unipole, tri-vision…
   - **Éclairage** — oui/non
   - **Source** — internes + externes ou juste internes
3. La liste se met à jour avec les panneaux disponibles
4. Cocher les panneaux qui vous intéressent (badge vert `DISPONIBLE`)
5. Cliquer **Créer une proposition à partir de la sélection**
6. Choisir le client et le commercial responsable
7. Ajuster les prix négociés si nécessaire (colonne PU HT/mois)
8. Vérifier la période et le montant total
9. **Enregistrer et envoyer** au client

Le client reçoit un email avec un lien vers sa proposition en ligne. Il peut signer, refuser ou demander un décalage de dates.

**💡 Astuce** : la vue Disponibilités a un export PDF **avec photos** (idéal pour envoi client) et un export **liste comptable** (pour le dossier interne).

### 4.2 Modifier une réservation existante

Vous pouvez modifier n'importe quelle réservation (contrairement au commercial qui ne peut modifier que les siennes).

1. Menu → **Réservations** → clic sur la réservation
2. Cliquer **Modifier** (si le statut le permet — `en_attente` ou `confirme`)
3. Actions possibles :
   - Ajouter / retirer des panneaux
   - Changer la période (date début/fin)
   - Ajuster les prix négociés ligne par ligne
   - Ajouter une remise globale
4. **Enregistrer les modifications** → le client est notifié automatiquement des changements structurants

**⚠️ Attention** : ajouter un panneau à une réservation confirmée déclenche un nouveau check de disponibilité. Si un panneau est déjà réservé sur la période chevauchante, le système refuse et vous propose un panneau alternatif.

### 4.3 Annuler une réservation

Depuis juillet 2026, vous êtes autorisé à annuler **toute réservation cancellable**, même signée par le client.

1. Menu → **Réservations** → clic sur la réservation
2. Bouton **🚫 Annuler la réservation** (visible si statut = `en_attente` ou `confirme`)
3. Modal : catégorie d'annulation obligatoire (client_demande, erreur_saisie, remplacement…)
4. Précisions dans le textarea (recommandé pour l'audit)
5. **Confirmer** → les panneaux sont libérés, le client est notifié

**💡 Astuce** : pour annuler plusieurs réservations d'un coup, utilisez l'action en masse sur `/admin/reservations` (cocher les lignes → **Annuler** dans la barre d'action).

### 4.4 Planifier les poses d'une campagne

Quand une réservation est confirmée, la campagne se crée automatiquement avec ses **tâches de pose** (une par panneau).

1. Menu → **Campagnes** → clic sur la campagne
2. Section **Panneaux** → chaque ligne a une action **Assigner technicien**
3. Modal : sélectionner un technicien parmi la liste, choisir la date planifiée de pose
4. Confirmer → le technicien reçoit automatiquement un WhatsApp avec le lien de la pose

**💡 Astuce** : vous pouvez assigner **toutes les poses d'une campagne** en un clic depuis le bouton **Gérer les poses & piges terrain**. Utile quand vous préparez une grosse campagne.

### 4.5 Piloter les poses via Gestion Pose OOH

C'est votre **écran de contrôle** quotidien.

1. Menu → **Gestion Pose OOH**
2. En tête :
   - **Bandeau rouge** — X poses en retard (dépassées de leur date planifiée)
   - **Bandeau orange** — X poses réalisées SANS pige photo (à corriger — pas facturable en l'état)
3. Filtres : par statut, par technicien, par campagne, par période
4. Vue liste : chaque pose avec son statut (planifiée, en route, sur place, réalisée, annulée), le technicien assigné, la date planifiée, la date réalisée
5. Actions rapides : reassigner à un autre technicien, marquer réalisée manuellement, ajouter une pige rétroactive

**⚠️ Attention** : une pose sans pige = **pas facturable**. C'est une règle métier stricte. Si un technicien oublie de photographier, il faut soit qu'il retourne sur place, soit que vous ajoutiez manuellement une preuve.

### 4.6 Valider les piges photos

Chaque photo envoyée par un technicien atterrit dans la file **Piges Photos** pour validation.

1. Menu → **Piges Photos**
2. Filtres : **En attente** (le plus important), Vérifiées, Rejetées
3. Pour chaque pige en attente :
   - Voir la photo en grand (zoom disponible)
   - Vérifier que le panneau correspond bien à la campagne
   - Vérifier que l'affichage est net, non abîmé, correctement posé
4. Deux actions :
   - **✅ Vérifier** — la pige devient preuve d'affichage, disponible côté client
   - **❌ Rejeter** — motif obligatoire (photo floue, mauvais panneau, affichage abîmé) — le technicien reçoit une notification et doit refaire

**💡 Astuce** : traiter les piges chaque jour, pas laisser s'accumuler. Un client qui demande à voir ses piges 2 jours après la pose s'attend à les avoir immédiatement.

### 4.7 Traiter un signalement terrain

Quand un technicien voit un problème (panneau vandalisé, accès bloqué, campagne concurrente déjà posée), il ouvre un signalement dans son PWA. Vous les recevez ici.

1. Menu → **Signalements** → onglet **À traiter**
2. Chaque signalement affiche : le panneau, le motif, la photo du technicien, la date, l'équipe
3. Trois actions :
   - **Modifier le motif** — si le technicien a mal qualifié (ex: il a mis "accès bloqué" mais c'est en fait "panneau vandalisé")
   - **Marquer traité** — vous avez pris l'info en compte sans action lourde nécessaire
   - **Mettre en maintenance** — le panneau sort automatiquement des disponibilités jusqu'à ce que la maintenance soit clôturée

**💡 Astuce** : l'onglet **Analyse** montre les zones à problèmes récurrents (ex: panneau X vandalisé 4 fois cette année). Utile pour proposer un déplacement à la direction.

### 4.8 Gérer la maintenance d'un panneau

1. Menu → **Inventaire** → clic sur un panneau
2. Bouton **Créer une maintenance** (ou depuis un signalement)
3. Renseigner : motif, date de début, date estimée de fin
4. Le panneau passe en statut `maintenance` → invisible dans les disponibilités
5. Quand la maintenance est terminée, cliquer **Clôturer** → le panneau redevient disponible

**⚠️ Attention** : si le panneau est dans une campagne active, la maintenance n'annule pas la facturation en cours. À gérer avec l'admin si besoin de remise commerciale.

---

## 5. Ce que vous pouvez faire

| Action | Autorisée ? |
|---|:-:|
| Créer une réservation / proposition | ✅ |
| Modifier toute réservation (même pas la vôtre) | ✅ |
| Annuler une réservation (en_attente ou confirmée) | ✅ |
| Confirmer / Refuser une proposition côté admin | ❌ (admin only) |
| Émettre / envoyer une facture | ✅ (co-partagé avec commercial) |
| Déverrouiller une facture envoyée | ❌ 🔒 (admin only) |
| Créer / modifier un panneau | ✅ |
| Supprimer un panneau | ❌ 🔒 (admin only) |
| Assigner un technicien à une pose | ✅ |
| Valider / rejeter une pige photo | ✅ |
| Créer / clôturer une maintenance | ✅ |
| Traiter un signalement | ✅ |
| Créer un client | ✅ |
| Supprimer un client | ❌ 🔒 (admin only) |
| Modifier les tarifs communaux | ❌ 🔒 (admin only) |

---

## 6. Signaux à surveiller quotidiennement

Chaque matin, en 10 minutes :

1. **Gestion Pose OOH** — combien de poses en retard ? Contacter les techniciens concernés
2. **Piges Photos → En attente** — vider la file, ne pas laisser plus de 24h
3. **Signalements → À traiter** — traiter les nouveaux, décider maintenance ou pas
4. **Alertes** — filtrer sur les niveaux danger + avertissement
5. **Dashboard → Confirmations en attente** — informer l'admin des propositions signées à valider

---

## 7. Erreurs classiques à éviter

- **Ne pas oublier d'ajouter une pige** quand vous marquez une pose réalisée manuellement — sinon la campagne n'est pas facturable
- **Ne pas modifier une réservation en_attente sans prévenir le commercial** — c'est son dossier, il doit être au courant
- **Ne pas rejeter une pige sans motif clair** — le technicien doit savoir quoi corriger
- **Ne pas laisser un signalement en "à traiter" plusieurs jours** — un panneau vandalisé qui reste facturé au client est un problème commercial

---

## 8. FAQ Media Planner

**Q : Je vois la pose comme "en retard" mais le technicien dit qu'il l'a faite hier.**
R : Il a oublié de valider dans son PWA. Deux options :
1. Lui demander d'ouvrir l'app et de marquer la pose réalisée avec la date d'hier
2. Le faire pour lui depuis la fiche pose (nécessite d'ajouter aussi la pige photo qu'il a normalement prise)

**Q : Un panneau apparaît disponible mais je sais qu'il est déjà loué.**
R : Il y a probablement une réservation `en_attente` non enregistrée dans le système. Vérifier avec le commercial concerné. Si vous confirmez qu'il est bien loué, créer la réservation immédiatement pour bloquer les dates.

**Q : Comment déplacer une pose de dernière minute ?**
R : Fiche pose → **Modifier** → changer la date planifiée. Le technicien assigné reçoit une notification WhatsApp automatiquement.

**Q : Je veux facturer une campagne mais les prix négociés sont différents de la réservation.**
R : Sur la fiche campagne, l'app détecte automatiquement l'écart et affiche une bannière orange. Deux options : ajuster la période/panneaux pour retomber sur le bon montant, ou générer la facture avec le vrai montant (l'écart est simplement documenté).

**Q : Un technicien envoie 3 piges pour la même pose.**
R : Vérifier chacune — souvent la 1ère est une vue globale et les suivantes sont des angles complémentaires. Valider les 3 si elles sont pertinentes, rejeter les doublons.

**Q : Comment prolonger une campagne qui doit continuer au-delà de sa date de fin ?**
R : Fiche campagne → bouton **Prolonger la campagne** → nouvelle date de fin + nouveau montant additionnel calculé au prorata. Une nouvelle facture est générée pour l'extension.

---

## 9. Ressources complémentaires

- Manuel Admin : [00-admin.md](00-admin.md) — pour comprendre ce que fait votre patron
- Manuel Technicien : [04-technicien.md](04-technicien.md) — pour comprendre ce qui remonte du terrain
- Workflow des poses : `docs/POSE_WORKFLOW.md`

---

**Fin du manuel Media Planner.** Version 1.0 — 2026-07-20.
