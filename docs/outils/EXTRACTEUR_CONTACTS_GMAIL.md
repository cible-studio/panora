# Extracteur de contacts Gmail — CIBLE

> Créé le 2026-10-23. Script : `extracteur-contacts-gmail.gs` (même dossier).
> Outil interne CIBLE, sans lien avec le code de Panora.

## Ce que ça fait

Un **Google Sheets partagé** contient le script. Chaque collaborateur qui l'ouvre et clique sur
**« 📇 Contacts Gmail → Analyser ma boîte »** fait lire **sa** boîte Gmail. Ses contacts
s'ajoutent à une base commune, sans doublon.

**Ce qui est lu :**
- **De** : tous ceux qui vous ont écrit, même sans réponse ;
- **À** : tous ceux à qui vous avez écrit ;
- **CC** : tous ceux qui étaient en copie, que vous ayez envoyé ou reçu le mail ;
- **CCI** : seulement dans vos envois, car Gmail ne montre pas les copies cachées des autres.

**Ce qui est exclu automatiquement** (modifiable dans la feuille « Réglages ») :
- vos propres adresses et les adresses `@cible-ci.com` ;
- les adresses automatiques (`noreply`, `mailer-daemon`, notifications…) ;
- les plateformes (Google, LinkedIn, Brevo…).

**Ce qui reste dans votre compte :** tout. Il n'y a aucun service tiers et aucun abonnement.

## Le fichier obtenu (feuille « Contacts »)

| Colonne | Contenu |
|---|---|
| E-mail, Nom | Le nom le plus récent connu |
| Entreprise (déduite), Domaine | Déduite du domaine : `orange.ci` donne « Orange » |
| Type d'adresse | Professionnelle, ou Personnelle (Gmail, Yahoo, Hotmail…) |
| Premier / dernier échange | Les dates de début et de fin de la relation |
| Échanges | Le nombre de mails où le contact apparaît |
| Envoyés par CIBLE / Reçus de ce contact | Qui sollicite qui |
| Rôles | De, À, CC, CCI |
| Boîtes CIBLE | Les collaborateurs qui ont échangé avec lui |
| Dernier objet | L'objet du dernier mail |
| **Statut CRM, Notes** | **À vous** : jamais écrasés par le script |

Le **Statut CRM** vaut « Client » si le domaine figure dans « Réglages → Domaines clients ». Sinon, il vaut « À qualifier ».

## Installation (10 minutes, une seule fois)

1. Créez un Google Sheets « Contacts CIBLE » dans le Drive partagé de la direction.
2. Ouvrez **Extensions → Apps Script**. Effacez le contenu, collez tout `extracteur-contacts-gmail.gs`, puis enregistrez (💾).
3. Revenez au Sheets et rechargez la page. Le menu **📇 Contacts Gmail** apparaît.
4. Lancez **Analyser ma boîte**. Google demande l'autorisation de lire Gmail et de modifier le fichier.
   - Si l'écran indique « Google n'a pas validé cette application » : c'est normal pour un script interne. Cliquez sur **Paramètres avancés** → **Accéder à … (non sécurisé)**.
5. Les feuilles « Contacts », « Réglages » et « Journal » se créent. Une grosse boîte se traite en **plusieurs fois** : la suite repart toute seule chaque minute, vous pouvez fermer le fichier. Le **Journal** montre l'avancement.
6. Une fois l'historique terminé : **Activer la mise à jour quotidienne**. Les nouveaux mails sont alors ajoutés chaque matin.

## Pour toutes les boîtes de CIBLE

Partagez le Sheets (en modification) avec chaque collaborateur concerné : direction, commercial, marketing, boîtes génériques. Chacun ouvre le fichier et fait les étapes 4 à 6 **avec son propre compte**. Les contacts sont fusionnés, et la colonne « Boîtes CIBLE » garde leur origine.

Pour une boîte générique comme commercial@, quelqu'un qui y a accès se connecte avec ce compte et fait les mêmes étapes.

Le script ne lit que la boîte de la personne qui le lance : personne ne peut lire la boîte d'un autre.

## Avant d'utiliser ces contacts dans Brevo ⚠

Une adresse présente dans Gmail **n'a pas consenti** à recevoir des campagnes, surtout si elle n'était qu'en copie.
- **Brevo** interdit les envois à des contacts non consentants, et peut suspendre le compte en cas de rebonds ou de plaintes. Cela toucherait aussi la diffusion des disponibilités.
- **En Côte d'Ivoire**, la prospection électronique est encadrée (loi n° 2013-450 sur les données personnelles, ARTCI).

**Méthode conseillée :**
1. Qualifiez avec la colonne **Statut CRM** : Client, Ancien client, Prospect, Agence, Fournisseur ou Exclure. Commencez par les contacts qui ont le plus d'échanges.
2. Écartez les adresses personnelles, les fournisseurs et les contacts vus une seule fois en copie.
3. Importez les **clients et prospects qualifiés** dans une liste Brevo **séparée** (« Prospects »), jamais dans la liste #3 synchronisée par Panora. Notez la source de chaque contact.
4. Le premier envoi doit être un mail de présentation qui laisse le choix de recevoir la suite, avec un lien de désinscription.

## Limites

- **Quotas Google** : une très grosse boîte (plusieurs dizaines de milliers de conversations) peut demander plusieurs jours. Le script fait une pause d'une heure quand le quota est atteint, puis reprend tout seul.
- **Entreprise déduite** : elle est approximative. Corrigez-la à la main au besoin.
- **Ne pas relancer l'historique** d'une boîte déjà analysée : les compteurs seraient doublés. La mise à jour quotidienne suffit.
