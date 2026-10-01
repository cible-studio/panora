# Diffusion des disponibilités aux clients — Brevo

> Spécification validée le 2026-09-29. Ce document sert à la fois de
> référence fonctionnelle et de guide de configuration pas à pas.

---

## 1. Ce que fait la fonctionnalité

Deux fois par mois, Panora envoie à tous les clients un mail présentant les
emplacements disponibles, avec un lien vers le catalogue PDF (une page par
panneau, avec photo).

| Sujet | Règle |
|---|---|
| **Calendrier** | Le 1er et le 15 à 10h. Samedi, dimanche ou jour férié → premier jour ouvrable suivant, 10h |
| **Période** | Envoi du 1er : tout le mois. Envoi du 15 : du 15 à la fin du mois. La période suit le créneau, pas le jour d'envoi |
| **Panneaux** | Parc CIBLE uniquement : libres sur toute la période + ceux qui se libèrent en cours de période (avec leur date). Exclus : maintenance, options, occupés jusqu'à la fin |
| **Prix** | Aucun |
| **Expéditeur** | `commercial@cible-ci.com` |
| **Destinataires** | Tous les clients d'office (contact principal, à défaut l'adresse de la fiche client). Désinscription possible à chaque mail |
| **Confirmation interne** | `commercial@cible-ci.com` + tous les Media Planners actifs, après chaque envoi, et alerte en cas d'échec |
| **Envoi manuel** | Admin seul. N'annule pas l'automatique. Rappelle le dernier envoi ; si le dernier date de moins de 24 h, il faut taper ENVOYER. Le bouton enregistre une demande, traitée par le planificateur dans la minute (le PDF est trop long à générer pendant une requête web) |

Exemples :

| Créneau | Jour | Envoi réel |
|---|---|---|
| 1er octobre 2026 | jeudi | jeudi 1er octobre, 10h |
| 1er novembre 2026 | dimanche + Toussaint | lundi 2 novembre, 10h |
| 15 novembre 2026 | dimanche + Journée de la paix | lundi 16 novembre, 10h (période : 15 → 30 nov.) |
| 1er janvier 2027 | vendredi, férié | lundi 4 janvier, 10h |

---

## 2. Qui fait quoi

| Brique | Rôle |
|---|---|
| **Panora** | Sait quoi envoyer (disponibilités), à qui (clients), quand (calendrier + fériés). Génère le PDF. |
| **Brevo** | Envoie, gère les désinscriptions et les rebonds, fournit les statistiques. |

On utilise les **campagnes marketing** de Brevo (et non les e-mails
transactionnels) : elles imposent le lien de désinscription et donnent un
rapport de suivi par envoi. Les factures, relances et notifications de
Panora continuent de passer par l'envoi habituel — un client désinscrit des
disponibilités reçoit toujours ses factures.

---

## 3. Configurer Brevo — pas à pas

### Étape 1 — Créer le compte
Au nom de l'entreprise, avec une adresse de l'entreprise (pas personnelle).
L'offre gratuite suffit pour quelques dizaines de clients deux fois par mois.

### Étape 2 — Authentifier le domaine *(la plus importante)*
*Paramètres → Expéditeurs, domaines et IP dédiées → Domaines → Ajouter un domaine* :
`cible-ci.com`.

Brevo affiche des enregistrements DNS à créer chez l'hébergeur du domaine :

| Enregistrement | À quoi il sert |
|---|---|
| **Code Brevo** (TXT) | Prouver que le domaine vous appartient |
| **DKIM** | Signature qui prouve à Gmail et Outlook que le mail vient bien de vous |
| **DMARC** | Consigne donnée aux messageries pour les mails qui prétendent venir de vous sans signature |

⚠️ **On ajoute, on ne remplace jamais.** Ne modifier ni supprimer aucun
enregistrement MX ou TXT existant : ils font fonctionner la messagerie actuelle.
Il ne doit exister **qu'un seul** enregistrement SPF : s'il y en a déjà un et
que Brevo en demande un, on **fusionne** (ajout de `include:spf.brevo.com`
dans l'existant), on n'en crée pas un second — deux SPF invalident les deux.

Délai de prise en compte : jusqu'à 48 h. Brevo passe les enregistrements au vert.

### Étape 3 — Ajouter l'expéditeur
*Expéditeurs → Ajouter* : `commercial@cible-ci.com`, nom affiché
« CIBLE CI — Service commercial ». Saisir le code reçu dans cette boîte.
Cette boîte doit être lue : les clients y répondront.

### Étape 4 — Créer les attributs de contact
*Contacts → Paramètres → Attributs de contact → Ajouter* :

| Nom (exact) | Type | Contenu |
|---|---|---|
| `CONTACT` | Texte | Nom du contact chez le client |
| `SOCIETE` | Texte | Nom du client |
| `PANORA_ID` | Nombre | Identifiant du client dans Panora |

Les noms doivent être **exactement** ceux-ci : Panora les remplit à chaque
envoi. Un attribut manquant fait échouer la synchronisation (le message
d'erreur apparaît dans le journal des envois).

### Étape 5 — Créer les listes
*Contacts → Listes → Créer une liste* :
- **Clients — Disponibilités** : laissez-la vide, Panora la remplit.
- **Tests internes** : ajoutez-y vos propres adresses à la main.

Notez l'**identifiant numérique** de chaque liste (colonne ID).

### Étape 6 — Créer la clé API
*SMTP et API → Clés API → Générer une nouvelle clé API*.

⚠️ **Ne jamais transmettre la clé par mail, WhatsApp ou messagerie**, ni la
committer. Elle va directement dans le `.env` du serveur (étape 8). Si Brevo
le propose, limitez son usage à l'adresse IP du serveur de production.

### Étape 7 — Le modèle de mail *(facultatif au démarrage)*
Sans modèle, Panora utilise son gabarit par défaut
(`resources/views/diffusion/campagne-defaut.blade.php`).

**Modèle CIBLE retenu (2026-10-01)** : `docs/diffusion/modele-brevo-disponibilites.html`
— design choisi par la direction, aux couleurs et polices de la charte
CIBLE. Il se colle dans *Modèles → Créer un modèle → Code HTML
personnalisé*. Les photos sources sont dans `docs/diffusion/`. Dans le code,
on utilise l'adresse « original » de la bibliothèque d'images Brevo, jamais
celle en `img-thumb`, qui renvoie une vignette de 400 px.

Pour un autre design sur mesure : *Campagnes → Modèles → Créer un modèle*.
Placez dans le modèle :

| À écrire dans le modèle | Remplacé par |
|---|---|
| `{{ contact.CONTACT \| default : "Madame, Monsieur" }}` | Nom du contact |
| `{{ params.PERIODE }}` | « du 1er au 30 novembre 2026 » |
| `{{ params.MOIS }}` | « novembre 2026 » |
| `{{ params.NB_PANNEAUX }}` | Nombre d'emplacements présentés |
| `{{ params.LIEN_PDF }}` | Lien du catalogue PDF — à mettre sur le bouton |
| `{{ unsubscribe }}` | Lien de désinscription — **obligatoire** |
| `{{ mirror }}` | Lien « Consulter la version en ligne » |

Notez l'identifiant du modèle, et **activez-le** : Brevo ne copie dans
une campagne que le contenu d'un modèle au statut « Actif ».

L'objet du mail ne vient pas du modèle : il est fixé par Panora
(`DIFFUSION_OBJET`, défaut « Nos disponibilités {periode} »).

### Étape 7 bis — La page de désinscription *(facultatif)*
Brevo propose une page de désinscription par défaut, en français mais
sans l'identité visuelle de la régie. Pour la personnaliser (logo,
couleurs, textes, questionnaire « pourquoi partez-vous ») : créer un
formulaire de désinscription dans Brevo, puis renseigner son identifiant
(24 caractères, visible dans l'adresse de la page en modification) dans
`BREVO_UNSUBSCRIBE_PAGE_ID`. Panora le transmet à chaque campagne.

### Étape 8 — Renseigner le `.env` du serveur

```dotenv
BREVO_API_KEY=                        # étape 6 — à coller directement, jamais à transmettre
BREVO_SENDER_EMAIL=commercial@cible-ci.com
BREVO_SENDER_NAME="CIBLE CI — Service commercial"
BREVO_LIST_CLIENTS=                   # étape 5 — identifiant de « Clients — Disponibilités »
BREVO_LIST_TESTS=                     # étape 5 — identifiant de « Tests internes »
BREVO_TEMPLATE_DISPOS=                # étape 7 — vide = gabarit Panora
BREVO_UNSUBSCRIBE_PAGE_ID=            # étape 7 bis — vide = page Brevo par défaut
BREVO_WEBHOOK_TOKEN=                  # longue chaîne aléatoire (48 caractères)

DIFFUSION_MODE=test                   # test = tout part vers « Tests internes »
DIFFUSION_AUTO=false                  # true = envois automatiques du 1er et du 15
DIFFUSION_CONFIRMATION_EMAIL=commercial@cible-ci.com
```

Puis `php artisan config:clear` (le conteneur le fait au redémarrage).

### Étape 9 — Le webhook de retour
*Paramètres → Webhooks → Ajouter un webhook* (section **Marketing**) :
- URL : `https://<domaine-panora>/webhooks/brevo/<BREVO_WEBHOOK_TOKEN>`
- Événements : **Désinscrit**, **Hard bounce**, **Spam**.

Sans ce webhook, Panora ne saurait pas qu'un client s'est désinscrit.

---

## 4. Mise en service — dans cet ordre

1. **Staging (develop)**, `DIFFUSION_MODE=test`, `DIFFUSION_AUTO=false`.
   Écran *Administration → Diffusion dispos* :
   - « Tester la connexion » → doit afficher le compte Brevo ;
   - « Envoyer un test » → le vrai mail arrive dans les boîtes de la liste
     « Tests internes », la confirmation arrive chez vous.
2. Vérifier le mail reçu : design, nom du contact, lien PDF, désinscription.
3. **Production (main)**, `DIFFUSION_MODE=production`, `DIFFUSION_AUTO=false` :
   premier envoi réel déclenché **à la main** par l'admin.
4. Si tout est bon : `DIFFUSION_AUTO=true`.

⚠️ **Prérequis** : le planificateur Laravel doit tourner **chaque minute**
(`* * * * * php artisan schedule:run`), sinon ni l'envoi automatique ni
le bouton manuel ne partent. Les 20 autres automatisations de Panora en
dépendent déjà.

**Durée** : le catalogue PDF de tout le parc prend plusieurs minutes à
générer (~7 min mesurées sur un poste de dev, 364 panneaux). D'où la
préparation dès 9h pour un envoi à 10h.

**Erreur « HTTP 402 — Your account is under validation »** (constatée le
2026-09-30) : Brevo bloque les campagnes créées par l'API tant que le
compte n'est pas entièrement validé. Dans ce cas, celles créées depuis
l'interface Brevo partent normalement. Le blocage a été levé en
**confirmant le numéro de téléphone** du compte Brevo. Ce n'est pas un
problème de Panora.

Commande utile pour vérifier une date sans rien envoyer :

```bash
php artisan dispos:diffuser --simuler=2026-11-02
```

---

## 5. Lire le suivi dans Brevo

*Campagnes → (la campagne) → Rapport* :

| Indicateur | Sens | Que faire |
|---|---|---|
| Délivrés | Arrivés dans la boîte | Doit être proche de 100 % |
| Ouvertures | Mail ouvert | Indicatif : Apple et Gmail faussent la mesure |
| **Clics** | Le client a ouvert le catalogue | **Indicateur fiable** — à transmettre aux commerciaux pour relance |
| Désinscriptions | Ne veut plus recevoir | Remonte automatiquement dans Panora |
| Rebonds définitifs | Adresse morte | Corriger l'adresse dans la fiche client |

---

## 6. Jours fériés

Les fêtes fixes (Jour de l'an, Travail, Indépendance, Assomption, Toussaint,
Paix, Noël) et chrétiennes mobiles (lundi de Pâques, Ascension, lundi de
Pentecôte) sont ajoutées automatiquement.

Les **fêtes musulmanes** (Aïd el-Fitr, Tabaski, Maouloud, lendemain de la Nuit
du Destin) sont fixées chaque année par décret : l'admin les saisit dans
l'écran de diffusion. Un bandeau le rappelle tant qu'elles manquent.

---

## 7. Code

| Fichier | Rôle |
|---|---|
| `app/Services/Diffusion/DiffusionCalendrier.php` | Source unique des dates (pure, testée) |
| `app/Services/Diffusion/JoursFeriesService.php` | Fériés calculés + saisis |
| `app/Services/Diffusion/DiffusionDisponibilitesService.php` | Panneaux, destinataires, envoi, confirmation |
| `app/Services/DisponibilitesPdfBuilder.php` | PDF images — partagé avec l'export manuel |
| `app/Services/Brevo/BrevoClient.php` | Seul point de contact avec l'API Brevo |
| `app/Console/Commands/DiffuserDisponibilites.php` | `dispos:diffuser` : 9h préparation du PDF, 10h envoi, chaque minute les demandes manuelles |
| `app/Http/Controllers/Admin/DiffusionDisponibilitesController.php` | Écran admin |
| `app/Http/Controllers/Webhooks/BrevoWebhookController.php` | Désinscriptions, rebonds, spam |
| `app/Http/Controllers/PublicDiffusionController.php` | Lien public du PDF |
| `config/brevo.php`, `config/diffusion.php` | Configuration |
