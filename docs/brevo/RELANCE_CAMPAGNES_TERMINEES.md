# Relance des clients dont la campagne est terminée (envoi depuis Brevo)

> Créé le 2026-10-21.
> Modèle : `modele-relance-campagne-terminee.html` · fichier d'exemple : `exemple-import-relance.csv` (même dossier).
>
> **Depuis le 2026-10-21, Panora prévient automatiquement chaque client 7 jours avant la fin de sa campagne**, avec le même texte. Voir `docs/DIFFUSION_DISPONIBILITES.md` § 8. Depuis le 2026-10-22, les campagnes de 12 mois ou plus ont une séquence de renouvellement complète (J-60 → J-7, boutons de réponse) : `docs/RENOUVELLEMENTS.md`. Ce guide manuel sert à relancer les campagnes **déjà terminées** avant la mise en service.

## Principe

Un mail **personnalisé par client** : son nom, sa société, le nom de sa dernière campagne et la date de fin. Il propose trois suites :
- une reconduction ;
- les emplacements libres ;
- une recommandation.

Le client répond simplement au mail, et sa réponse arrive sur commercial@cible-ci.com.

**Brevo seul suffit**, à une condition : chaque information personnalisée doit être enregistrée dans la fiche du contact, sous forme d'**attributs**. Brevo ne lit pas Panora. On importe donc ces informations une fois, avec un fichier CSV, avant chaque relance.

Le mail est volontairement **sans image ni bouton**, comme le modèle léger des disponibilités. Il ressemble à un message de commercial, ce qui l'aide à arriver dans la boîte de réception et à obtenir des réponses.

---

## Étape 1 — Créer les attributs (une seule fois)

Brevo → **Contacts** → **Paramètres** → **Attributs de contact** → **Ajouter un attribut**.

| Nom | Type | Rôle |
|---|---|---|
| `CONTACT` | Texte | Nom de l'interlocuteur. **Existe déjà** |
| `SOCIETE` | Texte | Nom du client. **Existe déjà** |
| `DERNIERE_CAMPAGNE` | **Texte** | Nom de la campagne terminée |
| `FIN_CAMPAGNE` | **Texte** | Date de fin, écrite en toutes lettres : `30 septembre 2026` |

`FIN_CAMPAGNE` est en **Texte**, et non en Date, pour que le mail affiche « 30 septembre 2026 » tel quel, sans conversion de format.

---

## Étape 2 — Préparer le fichier des clients

1. Dans Panora → **Campagnes**, filtre le statut **Terminée** et la période voulue, puis **Exporter en Excel**. Le fichier contient le nom de la campagne, le client et la date de fin.
2. Ajoute l'**e-mail** et le **nom de l'interlocuteur** de chaque client. Tu les trouves sur sa fiche dans Panora, ou dans l'export de la liste des clients.
3. Ne garde qu'**une ligne par client**, avec sa campagne la plus récente.
4. Mets les colonnes dans cet ordre, avec exactement ces titres. C'est le modèle de `exemple-import-relance.csv` :

```
EMAIL;CONTACT;SOCIETE;DERNIERE_CAMPAGNE;FIN_CAMPAGNE
```

5. Enregistre le fichier en **CSV UTF-8**. Dans Excel : *Fichier → Enregistrer sous → CSV UTF-8*. Sans cela, les accents s'afficheraient mal.

⚠ Chaque ligne doit avoir **toutes les colonnes remplies**. Une campagne ou une date vide donnerait « La campagne « » s'est achevée le . ».

---

## Étape 3 — Importer dans une liste dédiée

Brevo → **Contacts** → **Importer des contacts** → **Importer un fichier** :
1. Choisis le fichier CSV.
2. Vérifie l'association des colonnes : chaque colonne doit être reliée à l'attribut de même nom ; `EMAIL` correspond à l'adresse e-mail.
3. Liste : **crée une nouvelle liste**, par exemple `Relance — campagnes terminées — oct. 2026`.
4. Coche **« Mettre à jour les attributs des contacts existants »**.

⚠ **N'importe jamais dans la liste « Clients — Disponibilités » (#3).** Panora la synchronise automatiquement pour l'envoi des disponibilités, et retirerait les contacts ajoutés à la main.

Les contacts qui se sont déjà désinscrits ne recevront pas le mail : Brevo les exclut automatiquement.

---

## Étape 4 — Créer le modèle (une seule fois)

Brevo → **Campagnes** → **Modèles** → **Créer un modèle** → **Code HTML personnalisé** :
1. Colle tout le contenu de `modele-relance-campagne-terminee.html`.
2. Renseigne :
   - expéditeur : `commercial@cible-ci.com` ;
   - nom : `CIBLE CI — Service commercial`, ou le prénom d'un commercial ;
   - objet : celui de l'étape 5.
3. **Laisse vide** « Aperçu du texte » : le modèle a déjà le sien.
4. Enregistre, puis **active** le modèle.

---

## Étape 5 — Créer et envoyer la campagne

Brevo → **Campagnes** → **Créer une campagne** → **E-mail** :

| Réglage | Valeur |
|---|---|
| Nom (interne) | `Relance campagnes terminées — oct. 2026` |
| Destinataires | la liste créée à l'étape 3 |
| Expéditeur | `commercial@cible-ci.com` |
| Répondre à | `commercial@cible-ci.com` (les réponses des clients y arrivent) |
| Objet | `La suite de votre campagne {{ contact.DERNIERE_CAMPAGNE }}` |
| Contenu | **Modèles enregistrés** → choisis le modèle de relance |

**Objet** : il est personnalisé lui aussi. Garde-le court, sans majuscules ni emoji, pour qu'il fasse « message » plutôt que « promotion ».

**Avant d'envoyer :**
1. **Aperçu et test** → choisis un vrai contact de la liste : le nom, la société, la campagne et la date doivent apparaître.
2. **Envoyer un test** vers ta propre adresse.
3. Choisis la date d'envoi. Je conseille un **mardi, mercredi ou jeudi, entre 9 h et 10 h**.

---

## Après l'envoi

- Les **réponses** arrivent sur commercial@cible-ci.com : il faut les traiter dans les 24 h ouvrées, comme le promet le mail.
- Les **statistiques** (ouvertures, réponses, désinscriptions) sont dans Brevo → Campagnes → le rapport de la campagne.
- Pour la relance suivante : nouveau fichier, **nouvelle liste** (ou vider l'ancienne), même modèle.

## Relance de paiement : un autre sujet

Ce mail est une **relance commerciale**, pour donner suite à une campagne terminée. Les relances de **factures impayées** se gèrent dans Panora (Facturation → Relances, alertes d'échéance), pas dans Brevo.
