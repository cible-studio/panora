# Manuel Technicien — Panora Mobile (PWA)

> Vous êtes **technicien terrain** chez CIBLE. Ce manuel décrit votre outil mobile — un site installable comme une app sur votre téléphone — et comment vous rendre compte de votre journée.

---

## 1. Votre mission

Le technicien est **le bras du service** :
- Vous **posez les visuels** sur les panneaux selon les campagnes planifiées
- Vous **photographiez** chaque affichage terminé (pige photo obligatoire)
- Vous **signalez** tout problème rencontré (accès bloqué, panneau vandalisé, campagne concurrente…)
- Vous êtes le premier maillon de la **preuve d'affichage** que le client recevra dans son espace

Sans vous, pas de campagne. Sans vos piges photos, pas de facturation.

---

## 2. Installer Panora sur votre téléphone

Panora est une **PWA** (Progressive Web App) — un site qui s'installe comme une app, sans passer par Play Store ou App Store.

### Sur Android (Chrome)
1. Ouvrir Chrome
2. Aller sur : `https://panora-cible.com/tech`
3. Se connecter avec vos identifiants (donnés par le Media Planner)
4. Chrome propose **« Installer l'application »** → cliquer **Installer**
5. L'icône **Panora Tech** apparaît sur votre écran d'accueil

### Sur iPhone (Safari)
1. Ouvrir Safari
2. Aller sur : `https://panora-cible.com/tech`
3. Se connecter
4. Bouton **Partager** (carré + flèche) → **Sur l'écran d'accueil**
5. Confirmer → l'icône Panora apparaît

**💡 Astuce** : une fois installée, Panora fonctionne comme une vraie app — plus besoin d'ouvrir le navigateur.

---

## 3. Votre écran d'ouverture

À la connexion, vous arrivez sur **votre journée** :

```
👋 Bonjour [Prénom]
[X] panneaux à poser · [Y] zones

██████░░░░░░░ 7/15
Continue, tu avances bien !

Tes zones du jour : Cocody · Yopougon · Bassam · +2

🔥 COCODY (3/5 faites)
   ├─ CDY-047A · Voie Expresse           [Faite ✓]
   ├─ CDY-046  · Voie Expresse           [Faite ✓]
   └─ CDY-041B · Bd Latrille              [MAINTENANT →]

🔥 YOPOUGON (2/4 faites)
   ├─ YOP-005  · Carrefour Marché         [Faite ✓]
   └─ YOP-012  · Bd des Martyrs           [En retard ⚠]
```

Ce que vous voyez :
- Votre nom et votre progression globale
- Les zones où vous êtes attendu aujourd'hui
- La liste de vos poses, groupées par commune
- **MAINTENANT** — la pose sur laquelle vous devez travailler en priorité
- **En retard** — poses dépassées de leur date planifiée

---

## 4. Vos actions clés

### 4.1 Voir le détail d'une pose

Clic sur une ligne pose → écran détail :

- **Référence panneau** : CDY-047A
- **Nom** : Voie Expresse Cocody Riviéra
- **Commune** : Cocody
- **Format** : 4×3m (12 m²)
- **Client** : Eurolait
- **Campagne** : Yoplait Été 2026
- **Date planifiée** : 10/07/2026 à 08:00
- **Statut actuel** : Planifiée
- **Photo du panneau** (référence)
- Bouton **📍 Ouvrir Google Maps** — itinéraire jusqu'au panneau
- Bouton **📞 Appeler la campagne** — coordonnées client si besoin

### 4.2 Marquer votre progression sur une pose

Chaque pose a **4 statuts** que vous mettez à jour au fur et à mesure :

1. **Planifiée** — c'est votre point de départ, le Media Planner vous a assigné cette pose
2. **En route** — vous partez sur le lieu de pose
3. **Sur place** — vous êtes arrivé, vous commencez le travail
4. **Réalisée** — la pose est terminée, avec pige photo obligatoire

**💡 Règle importante — exclusivité** : vous ne pouvez avoir qu'**UNE seule pose "en route"** ou "sur place" à la fois. Si vous marquez la pose B "en route", la pose A précédente redevient automatiquement "planifiée".

### 4.3 Poser un visuel + envoyer la pige photo

Workflow bout-en-bout d'une pose classique :

**Étape 1 — Se déplacer**
- Ouvrir la pose sur votre app
- Cliquer **📍 Ouvrir Google Maps** → itinéraire GPS
- Marquer la pose **En route**
- Partir sur le lieu

**Étape 2 — Sur place**
- Arrivé au panneau, cliquer **Sur place** (bouton bleu)
- Vérifier que c'est bien le bon panneau (référence collée dessus)
- Commencer la pose du visuel

**Étape 3 — Poser le visuel**
- Nettoyer le panneau si besoin (poussière, résidus ancien affichage)
- Coller / afficher le nouveau visuel selon la technique du panneau
- Vérifier la tenue, les angles, la lisibilité

**Étape 4 — Pige photo (OBLIGATOIRE)**
- Reculer de 3-5 mètres pour cadrer le panneau entier
- Prendre la photo **en plein jour**, panneau bien éclairé, sans obstacle devant
- Photo nette, non floue, non tremblée
- Bouton **📸 Ajouter la pige** → sélectionner la photo depuis la galerie
- L'app horodate automatiquement + géolocalise

**Étape 5 — Marquer réalisée**
- Bouton **✅ Marquer réalisée**
- La pose bascule en statut `réalisée`, la barre de progression avance
- Le Media Planner reçoit la pige pour validation

**⚠️ RÈGLE D'OR : pas de pige = pas de facturation**. Sans photo de preuve, la pose est considérée comme non terminée côté client. Ne jamais oublier la photo, même si vous êtes pressé.

### 4.4 Signaler un problème terrain

Cas où vous ne pouvez pas poser (ou pas dans les conditions prévues) :

Clic sur la pose → bouton **⚠️ Signaler un problème**

Motifs disponibles :
- **Accès bloqué / impossible** — un mur, une porte fermée, la zone est en travaux
- **Panneau vandalisé** — le support est cassé, tagué, arraché
- **Panneau introuvable** — vous ne le trouvez pas à l'adresse indiquée
- **Campagne concurrente déjà posée** — un autre visuel est déjà sur le panneau, vous ne savez pas quoi faire
- **Autre** — précisez dans le textarea

Ajouter obligatoirement :
- **Une photo** du problème (mur, panneau abîmé, campagne concurrente…)
- **Une description** (2-3 phrases suffisent)

**Envoyer** → le signalement remonte immédiatement au Media Planner qui décidera :
- Vous rappeler avec une consigne
- Reporter la pose à une autre date
- Passer le panneau en maintenance
- Assigner un autre technicien

**💡 Astuce** : plus votre signalement est précis, plus la décision est rapide. « Panneau vandalisé » vs « Le support en bois est cassé en deux, la partie basse pend, dangereux pour les passants » — le deuxième permet de décider en 30 secondes.

### 4.5 Gérer votre journée

En haut de votre écran, un chiffre indique votre progression : `7/15` = vous avez fait 7 poses sur les 15 prévues.

**Chaque commune** est groupée pour vous éviter les allers-retours. Faites toutes les poses d'une commune avant de passer à la suivante.

**Priorités** :
- 🔴 **En retard** — à faire absolument aujourd'hui
- 🟠 **Maintenant** — la pose du moment (celle marquée "en route" ou "sur place")
- 🟢 **Planifiée** — à faire dans la journée

**Astuce logistique** : consulter votre journée la veille au soir pour préparer l'itinéraire, le matériel, les visuels à charger dans le camion.

### 4.6 Consulter vos piges envoyées

Menu → **Mes photos**

Vous voyez toutes vos piges de la période :
- 📸 **Total** — combien vous avez envoyé
- ⏱️ **En attente** — pas encore validées par le MP
- ✅ **Vérifiées** — validées, comptabilisées dans votre performance
- ❌ **Rejetées** — refusées par le MP (motif visible → à refaire)

**💡 Astuce** : si une pige est rejetée, l'app vous notifie. Retourner sur le panneau, refaire une meilleure photo, la renvoyer sur la même pose.

---

## 5. Bonnes pratiques terrain

### Préparation de la journée
- Vérifier votre planning la veille dans l'app (le soir suffit)
- Préparer le matériel : visuels imprimés, échelle, colle, cutter, chiffon, marqueur pour repères
- Charger votre téléphone à 100% et prévoir un chargeur nomade
- Habits repérables CIBLE si vous en avez (pour être identifié comme intervenant autorisé)

### Sur chaque site
- **Sécurité d'abord** — si le panneau est en hauteur, échelle stable, jamais sans harnais si > 3m
- **Vérifier la référence** avant de commencer (parfois 2 panneaux voisins se ressemblent)
- **Photographier avant/après** si le panneau était très abîmé (traçabilité)
- **Ranger vos déchets** — anciens visuels, colle, emballages → sac poubelle, jamais laissé au pied du panneau

### Qualité de la pige photo
- **Angle frontal** — pas de biais, panneau bien perpendiculaire à la caméra
- **Cadrage large** — panneau complet + contexte (route, arbres) pour prouver le lieu
- **Lumière** — jour, pas contre-jour, pas de reflets soleil sur le visuel
- **Netteté** — bouger le téléphone lentement, laisser l'autofocus faire
- Éviter les selfies et les mains dans le cadre

### Communication
- **Répondre au WhatsApp** du MP dans l'heure — c'est votre canal de coordination
- **Signaler tout de suite** un problème, pas en fin de journée
- **Ne pas décider seul** de reporter une pose sans en parler — toujours signaler
- **Politesse** avec les riverains, agents de sécurité, gérants de commerce à côté du panneau

---

## 6. Ce que vous pouvez faire

| Action | Autorisée ? |
|---|:-:|
| Voir vos poses assignées | ✅ |
| Voir vos zones et progression | ✅ |
| Marquer une pose en route / sur place / réalisée | ✅ |
| Ajouter une pige photo | ✅ |
| Signaler un problème terrain | ✅ |
| Consulter vos piges (statut validé / rejeté) | ✅ |
| Voir les poses des autres techniciens | ❌ (chacun sa journée) |
| Modifier une pose (date, panneau, technicien) | ❌ (MP only) |
| Voir les prix, factures, montants clients | ❌ (confidentialité) |
| Créer / modifier un client | ❌ |
| Accéder au tableau de bord admin | ❌ |

---

## 7. Erreurs classiques à éviter

- **Poser sans photographier** — la pose n'est pas facturable, on vous rappellera pour refaire la photo (temps perdu)
- **Photographier de nuit** — la photo est illisible, elle sera rejetée
- **Ne pas signaler un problème** — le MP découvre le problème à la fin de la journée, la campagne est cassée
- **Oublier de marquer "Réalisée"** — la pose reste en "sur place" toute la journée, votre progression bloque
- **Rester connecté sur plusieurs téléphones simultanément** — risque de conflit de statut, préférer un seul appareil de travail
- **Ne pas répondre au WhatsApp du MP** — vous êtes déconnecté du reste de l'équipe

---

## 8. FAQ Technicien

**Q : L'app plante ou ne répond plus.**
R : Fermer l'app, la rouvrir. Si le problème persiste, désinstaller (long clic sur l'icône → Supprimer) et réinstaller depuis `panora-cible.com/tech`.

**Q : Je n'ai pas de connexion internet sur le lieu de pose.**
R : L'app fonctionne partiellement hors ligne. Vous pouvez :
- Consulter votre journée (déjà chargée)
- Prendre la photo pige (stockée sur téléphone)
- Marquer la pose "réalisée" — elle sera synchronisée dès que vous retrouvez du réseau

**Q : J'ai marqué une pose "réalisée" par erreur.**
R : Appeler ou WhatsApper le Media Planner immédiatement — lui seul peut remettre la pose en "planifiée". Ne pas retenter d'actions dans l'app pour éviter d'aggraver.

**Q : Une pose est marquée "en retard" mais je l'ai faite hier.**
R : Vous avez oublié de marquer "Réalisée" hier. Ouvrir la pose maintenant, marquer réalisée avec date d'hier (option "date rétroactive" si dispo, sinon appeler le MP).

**Q : Je vois une pose qui n'est pas à moi.**
R : Erreur d'assignation. Prévenir le MP — il réassigne au bon technicien.

**Q : Le client sur place me demande de changer le visuel prévu.**
R : **Ne jamais changer un visuel sans validation**. Appeler le Media Planner ou le Commercial de la campagne (numéro sur la fiche pose). C'est eux qui décident.

**Q : Je veux signaler un problème sur un panneau qui n'est pas dans ma journée.**
R : L'app ne vous permet pas de signaler un panneau hors de vos poses. Prévenir le MP par WhatsApp avec la référence du panneau + la photo du problème.

**Q : Comment savoir combien de piges j'ai faites ce mois ?**
R : Menu **Mes photos** → filtre par période. Vous voyez le total et le taux de validation (vérifiées / rejetées).

---

## 9. Ce que vous rapportez au métier

Chaque pige que vous validez est utilisée :
- **Le client** la reçoit dans son espace comme preuve d'affichage → il est rassuré, il paie
- **Le commercial** l'utilise pour justifier la facturation
- **Le Media Planner** l'archive comme trace de qualité
- **La direction** la comptabilise dans votre performance individuelle (nombre de piges validées, taux de rejet)

Vous êtes évalué sur :
- **Nombre de poses réalisées** par période
- **Taux de piges vérifiées** (vs rejetées)
- **Taux de poses à temps** (vs en retard)
- **Qualité du signalement** (précision, réactivité)

---

## 10. Support

- **Media Planner** — pour toute question opérationnelle (WhatsApp / téléphone)
- **Bureau Riviera M'badon** — pour matériel, visuels, échelle, EPI

---

**Fin du manuel Technicien.** Version 1.0 — 2026-07-20.
