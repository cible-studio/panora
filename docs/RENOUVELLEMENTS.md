# Renouvellement des campagnes annuelles

> Mis en place le 2026-10-22, à partir du dispositif validé par la direction
> (séquence de mails, boutons de réponse, suivi des appels, confirmation par le MP).
> Écran : *Panora → Renouvellements*. Textes : *Renouvellements → Textes des mails*.

## En une phrase

Pour chaque campagne de **12 mois ou plus**, Panora envoie jusqu'à 5 mails avant
l'échéance, avec des **boutons de réponse**. Dès que le client répond, les relances
s'arrêtent, il reçoit un accusé de réception et son commercial est prévenu. Tout part
par Brevo et se suit dans Brevo.

## Quelles campagnes

- **Campagnes actives de 12 mois ou plus.** La durée est mesurée avec la règle de durée déjà
  utilisée pour la facturation et l'affichage (`Campaign::resolvePeriod`).
  Exemple : du 1er janvier au 31 décembre = 12 mois. Méthode : `Campaign::estAnnuelle()`.
- Les campagnes plus courtes gardent le **mail J-7 simple** (`docs/DIFFUSION_DISPONIBILITES.md` § 8).
  Une campagne ne reçoit jamais les deux.

## La séquence (chaque jour à 10 h, `campaigns:notify-ending`)

| Étape | Envoyée à | Boutons | Tag Brevo |
|---|---|---|---|
| J-60 | Toutes les campagnes concernées | Renouveler · Ajuster · Ne pas renouveler | `renouvellement-j60` |
| J-45 | Celles qui n'ont pas répondu | Les mêmes | `renouvellement-j45` |
| J-30 (date limite) | Celles qui n'ont pas répondu | Les mêmes | `renouvellement-j30` |
| J-15 | Celles qui n'ont pas répondu | Renouveler · Échanger avec un conseiller | `renouvellement-j15` |
| J-7 | Celles qui n'ont pas répondu | Renouveler · Échanger avec un conseiller | `renouvellement-j7` |
| Après une réponse | Le client | Accusé de réception | `renouvellement-accuse` |

- **Une étape ne part qu'une fois.** Le journal `relances_fin_campagne` le garantit.
- **Un jour manqué est rattrapé** tant que l'étape suivante n'est pas due. Exemple : si le
  serveur était arrêté à J-45, le mail part à J-44.
- **La séquence s'arrête** dès que le dossier n'est plus « En attente » : le client a
  répondu, ou l'équipe a changé le statut après un appel.
- **Pas de mail aux adresses exclues** : désinscrites, invalides, spam.
- **Destinataire, réponse et copie** : mêmes règles que le mail J-7. Le mail va à
  l'interlocuteur principal du client. Les réponses vont au commercial de la campagne.
  commercial@ reçoit une copie.
- Il n'y a **pas** de règle « autre campagne du client » ici : le mail porte sur les
  emplacements d'une seule campagne. Si le client a une autre campagne planifiée, la liste
  l'indique, pour vérification.

## Quand le client clique sur un bouton

Le bouton ouvre une page personnelle, sans connexion : `/renouvellement/{jeton}/{choix}`.
- Le choix du bouton est déjà coché. Un champ libre facultatif permet de laisser un message
  au commercial.
- **Ouvrir la page n'enregistre rien**, parce que les antivirus de messagerie ouvrent les
  liens. C'est le bouton **Confirmer** qui enregistre la réponse.
- La page précise qu'une réponse indique une intention et ne vaut **pas engagement**.
- Après confirmation :
  - le client reçoit l'accusé de réception ;
  - le commercial reçoit la réponse, avec commercial@ en copie ;
  - les relances s'arrêtent.
- Le client peut changer d'avis : sa dernière réponse compte, sauf si le renouvellement est
  déjà confirmé.
- Le lien reste valable jusqu'à 30 jours après la fin de la campagne.

**Mails de test** : les boutons pointent vers des liens « essai » signés. Ils n'enregistrent
rien. L'accusé et le mail à l'équipe partent alors vers les adresses de test.

**Jours annoncés** (« se termine dans 7 jours ») : l'aperçu et les mails de test montrent le
mail tel qu'il partira à son étape, jamais plus que l'étape (J-7 → 7 jours), même si la
campagne finit bien plus tard. Un vrai client lit toujours les jours réels, y compris pour
un envoi forcé à la main. Règle unique : `RelanceFinCampagneService::joursAffiches()`.

## L'écran « Renouvellements » (refait le 2026-10-23)

Menu **Renouvellements**. Accès : admin, MP et commercial. Un commercial ne voit que ses clients
(`Campaign::forCommercialUser`). Les explications sont dans le bouton **Guide** et les info-bulles,
pas affichées en bloc.

**Trois onglets :**
- **Campagnes annuelles** : toutes les campagnes de 12 mois ou plus en cours, par échéance.
  Rien n'est caché : « ≤ 30 j », « ≤ 60 j » et « ≤ 90 j » sont de simples filtres.
- **Autres campagnes · mail J-7** : les campagnes plus courtes et l'état de leur mail J-7
  (prévu, envoyé, ne partira pas, avec la raison). Cela remplace la carte de l'écran Diffusion dispos,
  qui n'affiche plus qu'un résumé.
- **Journal des envois** : tous les mails clients avant échéance, avec leur statut.

**Compteurs** (cliquables, ils filtrent la liste) :

| Compteur | Contenu |
|---|---|
| Échéances à venir | Campagnes annuelles pas encore terminées, avec leur CA |
| Sans réponse | En attente, échéance dans 60 jours ou moins. « À risque » : leur CA à 30 jours ou moins |
| En discussion | Veut renouveler, veut ajuster, demande un rappel |
| Confirmés | CA sécurisé |
| Ne renouvellent pas | CA perdu |

Le **taux de réponse** est le nombre de clients qui ont répondu, rapporté au nombre de clients qui ont reçu au moins un mail.

**Liste** : client, campagne, échéance (J-x), frise des 5 mails, statut, prochaine action en
couleur, commercial. Recherche par client, campagne ou interlocuteur ; filtre par commercial.

**Fiche dossier** : un clic sur une ligne l'ouvre en panneau latéral.
`Renouvellements?campagne=ID` l'ouvre directement : c'est ce lien qu'utilisent la fiche campagne et les mails internes.
- Réponse du client, campagne et contact (téléphone et mail cliquables).
- Frise des mails (envoyé le…, prévu le…, arrêté), avec l'aperçu de chacun.
- **Mettre à jour** : statut, **date de rappel**, note d'appel. Si le statut n'est plus
  « En attente », les relances s'arrêtent.
- **Confirmer le renouvellement** (MP et admin) : les panneaux sortent du **catalogue des
  disponibilités** jusqu'à 30 jours après la fin de la campagne (`RENOUVELLEMENT_RETENUE_JOURS`).
- **Renouveler la campagne** : ouvre la fiche campagne sur la fenêtre de duplication existante.
  Quand la campagne suivante est créée, le dossier passe automatiquement en « Renouvellement
  confirmé » et les relances s'arrêtent (`RenouvellementService::campagneRenouvelee`).
- Historique complet.

**Fiche campagne** : un encart « Renouvellement » (statut, prochaine action, frise) s'affiche
sur les campagnes de 12 mois ou plus, avec le lien vers le dossier.

## Le mail du matin (jours ouvrés, 8 h, `renouvellements:digest`)

- **À chaque commercial** :
  - les rappels prévus ce jour-là (date de rappel notée dans le dossier) ;
  - les clients sans réponse à appeler, dès J-25, avec leur téléphone ;
  - les demandes de rappel ;
  - les réponses reçues depuis la veille (depuis vendredi le lundi).
- **Aux MP** : les renouvellements à confirmer.
- C'est un mail interne : il respecte `MAIL_STAFF_ALERTS_ENABLED`. En mode test, il part
  vers `DIFFUSION_CONFIRMATION_EMAIL`.

## Les textes

*Renouvellements → Textes des mails* (admin) :
- un onglet par mail : J-60, J-45, J-30, J-15, J-7, accusé de réception, et fin de campagne
  pour les campagnes plus courtes ;
- l'aperçu se met à jour pendant la saisie, avec une vraie campagne ou un exemple fictif.
  C'est le serveur qui le produit ;
- **Rétablir le texte d'origine** annule les modifications ;
- **Envoyer un test** utilise le texte enregistré et part vers les adresses de test.

**Syntaxe du texte :**
- une ligne vide sépare deux paragraphes ;
- `**gras**` met en gras ;
- des lignes qui commencent par `- ` forment une liste ;
- `[Champ]` insère une information du client ;
- `[Récapitulatif]` et `[Boutons]`, chacun seul sur sa ligne, insèrent l'encadré et les boutons.

Textes d'origine (`app/Services/Diffusion/ModelesMail.php`) :
- J-60, J-45 et J-15 sont ceux validés par la direction ;
- J-30, J-7 et l'accusé ont été rédigés dans le même ton.

## Mise en service

Rien de nouveau à configurer : on reprend les réglages Brevo des disponibilités et
l'interrupteur du mail J-7.

```
RELANCE_FIN_MODE=test          # défaut : tous les mails vont aux adresses de test
RELANCE_FIN_MODE=production    # les clients reçoivent (mail J-7 ET séquence)
RENOUVELLEMENT_RETENUE_JOURS=30   # facultatif
```

1. En mode test, ouvre *Textes des mails*, relis chaque étape dans l'aperçu, puis fais un
   **Envoyer un test** sur une vraie campagne annuelle.
2. Clique sur un bouton du mail de test, puis confirme : l'accusé et le mail à l'équipe
   arrivent sur les adresses de test.
3. Passe ensuite en production : `RELANCE_FIN_MODE=production` dans Coolify, en prod
   seulement.

Commandes utiles :

```bash
php artisan campaigns:notify-ending --dry-run                 # ce qui partirait aujourd'hui
php artisan campaigns:notify-ending --campagne=42 --etape=j30 # une étape précise pour une campagne
php artisan renouvellements:digest --dry-run                  # le mail du matin, sans l'envoyer
```

## Suivi dans Brevo

Ce ne sont pas des campagnes Brevo : chaque mail part seul, au moment voulu pour chaque
client. Pour les suivre :
- **Transactionnel → Journal** : chaque mail, client par client (délivré, ouvert, lien
  cliqué, rebond…). Filtre sur le tag de l'étape, par exemple `renouvellement-j60`.
- **Transactionnel → Statistiques**, filtré sur un tag : le bilan d'une étape.

## Code

| Fichier | Rôle |
|---|---|
| `app/Services/Diffusion/RenouvellementService.php` | Règles : séquence, dossiers, réponses, confirmation, catalogue, liste, mail du matin |
| `app/Services/Diffusion/ModelesMail.php` | Textes d'origine et mise en forme (testée) |
| `app/Services/Diffusion/RelanceFinCampagneService.php` | Socle commun : destinataire, mode test, envoi Brevo, journal |
| `app/Mail/MailClientCampagne.php` | Le mail client (séquence, accusé, J-7 simple) |
| `app/Http/Controllers/RenouvellementPublicController.php` | Page des boutons |
| `app/Http/Controllers/Admin/RenouvellementController.php` | Écran Renouvellements et éditeur des textes |
| `app/Console/Commands/NotifyEndingCampaigns.php` | 10 h : séquence et mail J-7 |
| `app/Console/Commands/RenouvellementsDigest.php` | 8 h : mail du matin |
| `app/Models/Renouvellement.php`, `ModeleMail.php` | Dossiers et textes modifiés |
