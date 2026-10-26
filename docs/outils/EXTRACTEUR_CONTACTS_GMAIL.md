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
- **CCI** : seulement dans vos envois, car Gmail ne montre pas les copies cachées des autres ;
- **le texte des mails reçus**, seulement pour y relever les numéros de téléphone de la signature de l'expéditeur. L'historique cité (« Le … a écrit : », « De : … Envoyé : … », transferts) est ignoré, car il contient la signature des autres. Rien d'autre du texte n'est gardé.

**Ce qui est exclu automatiquement** (modifiable dans la feuille « Réglages ») :
- vos propres adresses et les adresses `@cible-ci.com` ;
- les adresses automatiques (`noreply`, `mailer-daemon`, notifications…) ;
- les plateformes (Google, LinkedIn, Brevo…) ;
- les numéros de CIBLE : ceux de la colonne « Numéros internes (exclus) », et ceux que le script repère dans vos propres signatures quand vous écrivez à l'extérieur. Un numéro reconnu plus tard comme celui de CIBLE est aussi retiré des lignes déjà écrites.

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
| Objets récents | Les 5 derniers objets de mails, du plus récent au plus ancien, sans « RE: / TR: » ni doublon. Ils servent à rattacher l'adresse au bon client (« Devis campagne ORANGE ») |
| **Statut CRM, Notes** | **À vous** : jamais écrasés par le script |
| Téléphones (signature) | Jusqu'à 3 numéros trouvés dans la signature du contact, du plus récent au plus ancien. Format `+225 07 07 12 34 56`, ou `+33…` pour l'étranger. Placée en dernier pour ne rien décaler dans un fichier déjà rempli |

Le **Statut CRM** vaut « Client » si le domaine figure dans « Réglages → Domaines clients ». Sinon, il vaut « À qualifier ».

## Installation (10 minutes, une seule fois)

1. Créez un Google Sheets « Contacts CIBLE » dans le Drive partagé de la direction.
2. Ouvrez **Extensions → Apps Script**. Effacez le contenu, collez tout `extracteur-contacts-gmail.gs`, puis enregistrez (💾).
3. Revenez au Sheets et rechargez la page. Le menu **📇 Contacts Gmail** apparaît.
4. Lancez **Analyser ma boîte**. Google demande l'autorisation de lire Gmail et de modifier le fichier.
   - Si l'écran indique « Google n'a pas validé cette application » : c'est normal pour un script interne. Cliquez sur **Paramètres avancés** → **Accéder à … (non sécurisé)**.
5. Les feuilles « Contacts », « Réglages » et « Journal » se créent. Une grosse boîte se traite en **plusieurs fois** : la suite repart toute seule chaque minute, vous pouvez fermer le fichier. Le **Journal** montre l'avancement.
6. Une fois l'historique terminé : **Activer la mise à jour quotidienne**. Les nouveaux mails sont alors ajoutés chaque matin.
   - Si elle a été activée avant l'analyse, ce n'est pas grave : elle reprend elle-même l'historique là où il en est.

### Projet créé depuis script.google.com (hors du Sheets)

Le menu **📇 Contacts Gmail** n'apparaît pas, car le script n'est rattaché à aucun Sheets. Il marche quand même :
1. Dans l'éditeur, choisissez `analyserMaBoite` dans la liste des fonctions (à côté de **Exécuter**), puis cliquez sur **Exécuter**.
2. Au premier lancement, le script crée lui-même un Google Sheets **« Contacts CIBLE »** dans « Mon Drive ». Son lien s'affiche dans le **journal d'exécution**, en bas de l'éditeur.
3. Les lancements suivants réutilisent ce même fichier.

Pour faire analyser les boîtes des collègues, mieux vaut un script rattaché au Sheets (étapes 1 à 3 ci-dessus) : chacun n'a alors qu'à ouvrir le fichier partagé.

### Mettre à jour le script

Si une nouvelle version du script est fournie : ouvrez **Extensions → Apps Script**, remplacez **tout** le code, puis enregistrez. Les contacts, les réglages et l'avancement de l'analyse sont conservés. Les nouvelles colonnes s'ajoutent toutes seules à droite au lancement suivant.

## Pour toutes les boîtes de CIBLE

Partagez le Sheets (en modification) avec chaque collaborateur concerné : direction, commercial, marketing, boîtes génériques. Chacun ouvre le fichier et fait les étapes 4 à 6 **avec son propre compte**. Les contacts sont fusionnés, et la colonne « Boîtes CIBLE » garde leur origine.

Pour une boîte générique comme commercial@, quelqu'un qui y a accès se connecte avec ce compte et fait les mêmes étapes.

Le script ne lit que la boîte de la personne qui le lance : personne ne peut lire la boîte d'un autre.

## Trier l'export

1. Dans le Sheets : **Fichier → Télécharger → Microsoft Excel (.xlsx)**, puis rangez le fichier dans `docs/emails clients/`. Ce dossier est ignoré par Git, car il contient des données personnelles de clients.
2. Lancez `php docs/outils/trier-contacts-gmail.php "docs/emails clients/CONTACTS GMAIL.xlsx"`.
3. Le fichier **« Contacts triés.xlsx »** est créé dans le même dossier, avec ces onglets :
   - **Clients probables** : les objets montrent une affaire conclue (facture, bon de commande, BAT, pose, pige, règlement…) ;
   - **Prospects** : demandes de disponibilités, devis, propositions ;
   - **À vérifier** ;
   - **Écartés**, avec la raison.

   Les colonnes « Indices » et « Classement » expliquent chaque décision. Relancer le script sur un export plus récent refait tout le tri.

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
- **Double clic sans risque** : un seul lot tourne à la fois par boîte. Un second clic, ou la mise à jour de 6 h pendant l'historique, est ignoré.
- **Téléphones** : ce sont des numéros lus dans des signatures, donc à vérifier avant usage (ancien numéro, signature d'une assistante…). Seuls les numéros ivoiriens à 10 chiffres et les numéros étrangers écrits avec leur indicatif sont relevés. Une boîte analysée avant l'ajout des téléphones (23/10/2026) ne les relève que dans ses nouveaux mails.
- **Les compteurs sont approximatifs, pas les adresses** : si quelqu'un répond à une très vieille conversation pendant l'analyse de l'historique, Gmail la remonte en tête de liste. Un mail peut alors être compté une fois de trop, ou pas du tout. C'est rare, et l'adresse est quand même relevée.
