# Plan des vidéos de formation Panora

Rédigé le 26/11/2026, à partir de l'analyse du code de chaque parcours (lecture seule).
Première vidéo livrée le 25/11 : **Commerciaux — Du devis à la réservation ferme** (7 min 39).

---

## 1. Méthode, la même pour chaque vidéo (étape par étape)

1. **Cartographie du parcours.** Ce que la personne voit et clique : écrans, boutons, statuts, mails, tâches automatiques. Faite le 26/11 pour la finance, le média planner, les techniciens et la direction.
2. **Incohérences à corriger d'abord.** La liste t'est présentée avec une recommandation pour chaque point, et tu valides. Je corrige ensuite (tests, commit develop + main), pour que la vidéo montre un Panora juste.
3. **Texte de la voix off**, chapitre par chapitre, à valider.
4. **Tournage** sur la copie d'essai `panora_essai`, avec des dossiers fictifs (aucun vrai client), et sur les vrais écrans.
5. **Montage** : voix d'Henri, zooms sur l'élément dont on parle, curseur, sous-titres, titres de chapitres. Contrôle image par image, puis livraison dans `Documents/Formation Panora`.

Durée visée : 6 à 9 minutes par vidéo. Au-delà, on coupe en deux parties, pour qu'une vidéo se regarde d'une traite.

## 2. Ordre proposé

| # | Vidéo | Public | Format | État |
|---|---|---|---|---|
| 0 | Du devis à la réservation ferme | Commerciaux | Écran 16:9 | ✅ Livrée le 25/11 |
| 1 | Facturer et encaisser | Comptables, finance | Écran 16:9 | ✅ Livrée le 27/11 (7 min 20) |
| 2 | Taxes communales et suivi des mairies | Comptables, finance | Écran 16:9 | Ensuite |
| 3 | Le métier du média planner (A : de la campagne au terrain ; B : le parc et le pilotage) | MP | Écran 16:9 | Ensuite |
| 4 | Ma journée de technicien | Techniciens | **Vertical 9:16**, pour le téléphone | Ensuite |
| 5 | Piloter Panora | Direction, admins | Écran 16:9 | Ensuite |
| 6+ | Modules à part (voir § 8) | Selon le module | Courtes (3-5 min) | À choisir |

La finance est coupée en deux vidéos : facturation et taxes sont deux métiers. Ensemble, cela dépasserait 15 minutes.

---

## 3. Vidéo 1 — Facturer et encaisser (comptables)

**Chapitres prévus :**
1. **Ce que voit le comptable** : son menu (Facturation, Finance, Taxes, Suivi des mairies, Tarifs communes, Rapports), son tableau de bord, ce qu'il ne voit pas (les devis).
2. **Créer une facture depuis une campagne** : bloc « 💰 Facturation », « ⚡ Générer la facture FNE », reprise à l'identique du devis accepté, facture complémentaire.
3. **Créer une facture à la main** : « ＋ Nouvelle facture », lignes, services (pose, impression), remise, récapitulatif.
4. **Ce que veut dire « FNE » dans Panora** : le format de la DGI et le QR code. Il n'y a pas de certification automatique auprès de la DGI.
5. **La vie d'une facture** : Brouillon → Générée → Validée (figée) → Envoyée → Partiellement soldée / Soldée. Litige, verrou, PDF, envoi au client.
6. **Enregistrer un versement** : modes, acompte, pièce justificative, reste à payer, « Solder manuellement ».
7. **L'échéancier** : trimestriel, mensuel, acompte 30/70, 50/50.
8. **Suivre les impayés** : page Finance (encaissements, créances, balance âgée), onglet Recouvrement, « Enregistrer une relance », historique des relances, relances automatiques.
9. **Rapports et exports** : PDF et Excel des factures, récap Finance, audit d'une facture.

**Décor fictif à préparer** : 3 campagnes facturables (dont une issue d'un devis accepté), une facture soldée, une partielle, une en retard, une en litige, des relances enregistrées.

### Incohérences à corriger avant le tournage (finance)

> **État au 26/11 :** recommandations validées (« ok pour tout ce qui est recommandé » ; point 6 : 1 000 F partout ; point 5 : date d'émission côté facture, mois par mois côté mairie). Points 1 à 24 **corrigés** ; le 25 (manuel) viendra après la vidéo. Détail et vérifications : `docs/A_TESTER.md` § 55.

Chaque point a une **recommandation**. Les points marqués ⚠ changent une règle de calcul ou de droits : ils demandent ton accord écrit, comme toujours.

**Montants faux ou différents selon l'écran — prioritaires**
1. **Modifier une facture remet la quantité des services à 1** : le total baisse sans prévenir à l'enregistrement.
   → *Recommandé :* recharger la vraie quantité dans le formulaire.
2. **Page de la facture ≠ PDF** : la page calcule la TVA et le TTC sans les services, le PDF avec. Le « Sous-total services TTC » de la page ignore la quantité.
   → *Recommandé :* la page suit le PDF (modèle DGI), avec une seule source de calcul.
3. **Mail au client et page publique de la facture** :
   - le montant annoncé est le TTC, pas le **total à payer** ;
   - la ligne TVA affiche « 18 FCFA » ;
   - les statuts sont incomplets (« Payée » au lieu de « Soldée »).

   → *Recommandé :* afficher le total à payer, le vrai montant de TVA et les 9 statuts.
4. **Modifier une facture générée depuis une campagne recalcule l'ODP et la TM autrement** (nombre de mois au lieu des mois calendaires).
   → *Recommandé :* garder les dates de la campagne à la modification, et vérifier d'abord sur une facture réelle.
5. ⚠ **Date du tarif communal : quatre règles différentes.**
   - Facture manuelle : la date d'émission.
   - Facture générée : le 31/12.
   - Page Taxes : le début de la période.
   - Suivi des mairies : mois par mois.

   → *Recommandé :* **mois par mois**, partout. Chaque mois facturé prend le tarif en vigueur ce mois-là, comme le suivi des mairies.
6. ⚠ **TM quand la commune n'a pas de tarif TM** : 1 000 F/m²/mois sur la facture, 0 côté mairie.
   → *Recommandé :* 0 partout, avec un avertissement visible (« tarif TM manquant pour X : renseigne-le dans Tarifs communes ») sur la facture et la page des tarifs.
7. ⚠ **ODP facturée au client ≠ ODP payée à la mairie.** Le client paie ses mois de campagne, la mairie est payée pour tous les mois où le panneau est debout.
   → *Recommandé :* garder cette règle, la confirmer par écrit et retirer la mention « hypothèse ».
8. **Exports** :
   - l'Excel des factures n'a ni total à payer, ni payé, ni reste ;
   - les totaux du PDF de la liste comptent les brouillons et les factures annulées ;
   - dans le récap Finance, la colonne échéance est toujours vide et les statuts sont en codes bruts.

   → *Recommandé :* compléter et corriger.
9. **Liste des factures** : le filtre « Statut paiement » ne filtre que la page affichée, et fait perdre les autres filtres.
   → *Recommandé :* filtrer en base, en gardant les autres filtres.
10. ⚠ **« En retard » a quatre définitions**, et « À recouvrer » / « Prévision 30 j » sont faussés.
    → *Recommandé :* une seule définition. Une facture est en retard quand une échéance est dépassée et qu'il reste à payer. L'échéance est celle de l'échéancier, ou à défaut la date d'émission + 30 jours (conditions par défaut). Tous les indicateurs se calculent sur toutes les factures.

**Relances et notifications**

11. **Des relances « échéance dépassée » partent même après paiement**, et même en litige.
    → *Recommandé :* aucune relance pour une facture soldée, annulée ou en litige.
12. ⚠ **Trois systèmes de relance automatique se chevauchent** (double J-7, arrêt au premier paiement partiel).
    → *Recommandé :* un seul système, calé sur l'échéance (définition du point 10).
    - Client : un rappel à J-7, puis des relances à J+7, J+15 et J+30 tant qu'il reste à payer.
    - Comptable et commercial du dossier : une alerte à chaque étape.
13. **Le comptable ne reçoit aucune alerte d'échéance ni aucun mail** (tout part aux admins).
    → *Recommandé :* les alertes et mails de finance vont **au comptable**, avec les admins en copie.
14. **Une relance manuelle n'est jamais rattachée à une facture** (colonne toujours vide), et WhatsApp manque dans les filtres.
    → *Recommandé :* ajouter un champ « Facture » (facultatif) au formulaire de relance, et WhatsApp aux filtres.

**Boutons qui mènent à « accès refusé »**

15. Le comptable voit « Retour brouillon », « Annuler », « Réactiver », « Modifier » (facture verrouillée), le bouton Rapport des taxes et le fil d'Ariane des tarifs.
    → *Recommandé :* masquer ce qu'il n'a pas le droit de faire. Ses droits ne changent pas.
16. ⚠ **Rapports pour le comptable** : il voit tous les onglets, mais tous ses exports donnent « accès refusé ».
    → *Recommandé :*
    - lui ouvrir les onglets et les exports **financiers** (CA & Revenus, Clients, Zones) ;
    - lui masquer « Insights », réservé à la direction ;
    - lui masquer les exports d'occupation, qui relèvent du MP.
17. ⚠ **Le commercial et la facturation** : il voit « Nouvelle facture », « Créer la facture » et « Envoyer », mais n'a pas le droit de s'en servir.
    → *Recommandé :* la facturation reste au comptable et à l'admin. Masquer ces boutons au commercial, et corriger les droits et le commentaire du code qui disent le contraire.

**Textes et fonctions**

18. ⚠ **L'avoir est cité partout mais n'existe pas.**
    → *Recommandé :* corriger les textes maintenant (« contactez l'admin pour corriger une facture envoyée »). La **création de l'avoir** devient un chantier à part, à chiffrer, car une facture FNE envoyée ne devrait se corriger que par un avoir.
19. « Facture envoyée au client par email » s'affiche même sans adresse.
    → *Recommandé :* dire la vérité (« aucun mail : le client n'a pas d'adresse »).
20. Conseils de statut faux, frise à 3 étapes, transitions officielles contredites par les boutons.
    → *Recommandé :*
    - aligner la table des transitions sur les boutons actuels ;
    - corriger les astuces ;
    - montrer les vraies étapes dans la frise.
21. ⚠ **Numérotation** : le numéro est calculé en comptant les factures (risque de doublon), et le champ est modifiable.
    → *Recommandé :* Panora attribue le numéro à l'enregistrement (suite sans trou, sous verrou), et le champ n'est plus modifiable, comme pour le devis.
22. **Un PDF de brouillon ressemble à une vraie facture FNE.**
    → *Recommandé :* filigrane « BROUILLON » sur le PDF tant que la facture n'est pas validée.
23. `/admin/taxes/{id}` provoque une erreur 500. L'ancienne page « Taxes — historique » marche encore.
    → *Recommandé :* renvoyer `/admin/taxes/{id}` vers la page Taxes, et renvoyer l'ancienne page vers le Suivi des mairies.
24. **Page Taxes ≠ Suivi des mairies en 2026** (« ✓ Payé » d'un côté, « ✍ Réclamé à saisir » de l'autre).
    → *Recommandé :* la page Taxes reprend le statut du Suivi des mairies, qui est la source.
25. Le manuel `docs/manuels/03-comptable.md` est périmé.
    → *Recommandé :* le réécrire après les corrections, à partir du texte de la vidéo.

---

## 4. Vidéo 2 — Taxes communales et suivi des mairies (comptables)

**Chapitres prévus :**
1. **Les deux taxes** :
   - ODP : due pour tout panneau debout, même vide ;
   - TM : due seulement les mois de campagne, par face.
2. **Tarifs communes** : référentiel, historique, « 📅 Programmer » un nouveau tarif, tarif éclairé.
3. **Page Taxes communales** : périodes, indicateurs, détail par commune, « Inclure les pieds Lumipub ».
4. **Détail par panneau** : filtres, sélection, Excel, et le **relevé PDF pour la mairie** (ancien design, total en lettres).
5. **Suivi des mairies** :
   - 2026, année de reprise : saisir le montant réclamé par chaque mairie ;
   - à partir de 2027 : calcul Panora.
6. **Enregistrer un règlement** : quittance, annulation avec motif, relevé pour la mairie, exports.
7. **L'alerte trimestrielle**.

Incohérences propres aux taxes : n° 5, 6, 7, 23 et 24 ci-dessus.

---

## 5. Vidéo 3 — Le métier du média planner

Le sujet est vaste : je propose **deux parties** d'environ 8 minutes.

**Partie A — De la campagne au terrain**
1. **Ce que voit le MP** : son menu, ce qu'il peut faire et ce que le commercial ne peut pas (campagnes, prix négociés, poses, piges, parc).
2. **Recevoir le travail** :
   - l'alerte et le mail « 📥 BC reçu, campagne à créer » ;
   - le récap quotidien ;
   - le point des renouvellements à confirmer ;
   - les piges à valider.
3. **Créer la campagne après le BC** (« 🚀 Créer la campagne ») :
   - statuts Planifiée et En cours ;
   - « ▶ Démarrer », pause, terminer, annuler ;
   - les mails envoyés au client.
4. **Les autres façons de créer une campagne** : nouvelle campagne directe, import PDF, « 🔁 Renouveler » (dupliquer).
5. **Panneaux et prix** :
   - ajouter ou retirer un panneau (date d'entrée ou de sortie, motif, démantèlement) ;
   - prix négociés ;
   - prolonger, notifier le client ;
   - l'interlocuteur ;
   - la fiche de pose PDF.
6. **Les poses** :
   - la liste, assigner un technicien ou une équipe (« Suggérer »), replanifier ;
   - le calendrier, la carte, les poses oubliées ;
   - le message WhatsApp et le lien personnel du technicien.
7. **Les piges** : validation rapide (V / R), refus avec motif, ce que reçoit le client, le rapport PDF.
8. **Vagues, rechange, retouche**, puis le **décapage** (Rapports → Décapages).

**Partie B — Le parc et le pilotage**
1. **Inventaire** : fiche d'un panneau, statut, tarifs, GPS, axes Lumipub (axe, pied, face).
2. **Maintenance, signalements, conflits.**
3. **Régies externes** et **carte & heatmap**.
4. **Pilotage terrain en direct**, puis la performance des techniciens et des équipes.
5. **Rapports du MP** et taxes (consultation).
6. **Renouvellements** côté MP : « ✓ Confirmer le renouvellement ».

**Décor** : 2 campagnes (une planifiée, une en cours), 1 campagne annuelle à renouveler, 1 technicien avec des poses, 3 piges à valider (dont une à refuser), une maintenance et un signalement.

### Incohérences à corriger avant le tournage (MP)

**Bugs**
1. ✅ **Ajouter un panneau à une campagne, la dupliquer ou la renouveler plantait** (erreur 500 depuis le 24/11). **Corrigé le 26/11** (develop 15714fba, main 55329312) et vérifié en réel. **À redéployer.**
2. **« Renouveler » (dupliquer)** :
   - la fenêtre promet les prix négociés, mais la copie reprend les prix du catalogue ;
   - en cas de conflit, la nouvelle campagne naît sans panneau, alors que le message dit « mêmes panneaux ».
3. **WhatsApp en double** à l'attribution des poses (même point que pour les techniciens).
4. **Le champ « Équipe » des formulaires de pose est ignoré** à l'enregistrement.
5. **Une pose sans campagne** peut être créée, mais elle ne peut jamais être démarrée ni terminée, et elle est cachée de la liste.
6. **« Marquer réalisée »** : sur une seule pose, Panora accepte sans photo ; en lot, il la refuse.

**Droits et sécurité**

7. **Ces boutons mènent à « accès refusé »** :
   - « 🧾 Créer la facture » (fiche réservation, pour MP et commercial) ;
   - les exports de Rapports ;
   - « 🧾 Générer le devis » d'un renouvellement (pour le MP) ;
   - « 🔁 Renouveler la campagne » sur une campagne terminée ;
   - le lien « configurer » le numéro WhatsApp, qui ouvre la fiche utilisateur réservée à l'admin ;
   - les tuiles « Poses » et « Piges » de la campagne pour le commercial.
8. **« Réinitialiser le lien » est inversé** : il est affiché au commercial, mais permis au MP.
9. **Droits inversés sur la facturation rapide.** Elle est affichée à ceux qui n'y ont pas droit. Le MP, lui, peut **créer des factures ou les marquer payées par l'adresse**, alors qu'il n'a pas accès aux factures.
10. **Une réservation peut être supprimée ou modifiée par l'adresse, sans contrôle des droits.** Le comptable peut ainsi modifier une réservation.
11. Une ancienne route encore active permet au MP de **confirmer une réservation et créer sa campagne** sans passer par la règle du BC.
12. ⚠ **Le MP enregistre un BC lui-même** en choisissant « 🔒 Ferme — BC reçu » dans les Disponibilités, alors que la règle réserve le BC au commercial (et à l'admin).
    → *À trancher :* masquer « Ferme » au MP, ou l'autoriser officiellement.
13. **Espace client** : la photo seule se télécharge même si elle n'est pas encore validée.

**Logique en double ou contradictoire**

14. **Deux façons de créer une campagne depuis une réservation** : 🚀, ou le formulaire. Elles n'envoient pas le même mail au client et ne tiennent pas le même historique.
15. **Fin de campagne traitée deux fois** :
    - en affichant la page, la campagne passe « Terminée » sans libérer les panneaux ni annuler les poses ;
    - la nuit, la tâche automatique fait les deux.

    Une campagne en pause ne se termine jamais.
16. « ▶ Démarrer » est proposé alors que le début est dans le futur (le clic donne une erreur). Un rechange est proposé sur une campagne en pause (refusé ensuite).
17. **Alertes** :
    - « Supprimer les lues » efface celles de tout le monde, et la lecture est partagée ;
    - le tableau de bord du MP montre des alertes commerciales et financières ;
    - le MP ne voit pas les alertes de pige qu'il doit valider ;
    - des alertes sont mal classées (une campagne qui contient « fin » devient une alerte de fin de campagne).
18. La validation groupée des piges ne prévient pas le technicien. La réouverture et la clôture des poses sont codées en double, avec des règles différentes.
19. **Décapage** : « Marquer décapé » ignore les panneaux externes, alors que le contrôle « 100 % » les compte.
20. L'annulation d'une campagne annonce « factures non payées annulées », mais elle n'annule que les brouillons et les factures envoyées.
21. **Textes faux** :
    - le lien « d'intervention détaillée », présenté comme un lien vers une seule pose, ouvre en fait tout l'espace du technicien ;
    - le WhatsApp « nouvelle pose » est envoyé pour une photo à refaire ;
    - le bouton du récap quotidien ouvre les Rapports et non le tableau de bord.
22. Le manuel `docs/manuels/01-mediaplanner.md` (juillet) est périmé : proposition envoyée par le MP, campagne créée automatiquement, facturation par le MP…

---

## 5 bis. Points transversaux (plusieurs vidéos)

Ces points reviennent dans plusieurs analyses. Il vaut mieux les traiter une seule fois :

- **Boutons visibles qui mènent à « accès refusé »**, pour presque tous les rôles. → *Recommandé :* une seule règle, « on ne montre un bouton qu'à qui peut s'en servir », appliquée écran par écran avec la source des droits (`config/acces.php` et les policies).
- **Alertes partagées entre utilisateurs** : la lecture vaut pour tous, et « Vider les lues » efface tout. → *Recommandé :* lecture et suppression **par personne**.
- **WhatsApp en double** et messages mal formulés.
- **Indicateurs calculés différemment** selon l'écran : tableau de bord, Finance, Rapports.
- **Manuels périmés** (admin, MP, comptable). Les vidéos et leurs textes deviennent la référence ; les manuels sont réécrits ensuite.

---

## 6. Vidéo 4 — Ma journée de technicien (format vertical, pour le téléphone)

Les techniciens n'ont pas de compte. Ils reçoivent un **lien personnel** par WhatsApp et travaillent sur leur téléphone.

**Chapitres prévus :**
1. **Le message WhatsApp et le lien personnel** (à ne pas partager).
2. **« Ma journée »** :
   - bonjour, progression du jour ;
   - « Maintenant · la plus urgente » ;
   - les poses rangées par commune ;
   - la recherche.
3. **Une pose** : « Y aller » (Google Maps), « Je suis arrivé », le chrono.
4. **La photo (pige)** :
   - aperçu et contrôle GPS (distance au panneau) ;
   - « Envoyer la photo », jusqu'à 3 photos ;
   - « Photo envoyée », puis la pose suivante.
5. **Les axes Lumipub** : une photo par pied ; un axe d'ancien contrat se ferme avec « Terminé ».
6. **« Il y a un problème »** : les 9 motifs, une photo du souci, l'envoi au bureau.
7. **Les photos refusées** : la bannière, « Photos à refaire », le message du chef.
8. **Carte et tournée** : « Me localiser », « Mon chemin ».
9. **Autres outils** :
   - « Mes photos » ;
   - les alertes ;
   - la feuille de route papier ;
   - le travail **sans réseau** (les photos partent quand le réseau revient) ;
   - la fin de journée.

**Décor** : un technicien fictif, 6 à 8 poses dans 2 communes, dont un axe, une pose en retard et une photo refusée. Le tournage se fait sur une **copie d'essai** : chaque geste modifie de vraies données et envoie des mails.

### Incohérences à corriger avant le tournage (techniciens)

**Importantes**
1. **« Demander une nouvelle tournée »** (écran de fin de journée) n'envoie rien au bureau. L'écran affiche aussi deux valeurs fictives (« Durée totale », « Poses / heure »).
2. **Les notes internes s'affichent chez le client** : la page de la pige et l'espace client montrent « [via espace tech] · Tech: X », et parfois la justification d'un signalement.
3. **« Appeler mon chef » et le tutoriel n'apparaissent jamais** : le fichier de configuration `config/tech_space.php` n'existe pas.
4. **WhatsApp en double** à l'attribution d'une pose : l'observateur envoie un message, puis le service en envoie un autre. Un lot de N poses peut donner N messages plus un résumé.
5. **Message de refus de photo** :
   - il se termine par « Une nouvelle pose vous est assignée » ;
   - avec le modèle Meta, le motif disparaît ;
   - le modèle des envois groupés n'est pas configuré (il échouera hors de la fenêtre de 24 h).
6. Le bouton « Pose effectuée » de l'ancienne page affiche « Le superviseur en est notifié », mais personne n'est notifié.
7. **La page de décapage montrée au client affiche toujours « 0/N » et « En attente »**, alors que son titre dit « DÉCAPÉE ».
8. Un technicien désactivé peut encore utiliser ses anciens liens `/pige/…`.
9. **Maintenances** :
   - elles envoient le technicien vers une page admin qu'il ne peut pas ouvrir ;
   - elles n'apparaissent pas dans son espace ;
   - le WhatsApp de maintenance ignore l'interrupteur `WHATSAPP_ENABLED`.
10. **L'icône ajoutée sur l'écran d'accueil du téléphone ouvre le site**, pas l'espace du technicien.

**Moyennes**

11. Refuser une seule photo rouvre la pose même si deux autres photos sont valides. La pose s'affiche alors « Sur place » alors que le technicien n'y est pas.
12. Les poses d'une campagne en pause s'affichent, mais la photo est refusée avec une erreur 423.
13. Une photo hors ligne refusée définitivement reste dans la file d'envoi pour toujours.
14. **Trop de mails au MP par photo** : deux mails, plus un aux admins. La règle du 11/08 disait « MP uniquement ».
15. Les avis « Photo validée » et « Photo refusée » partent parfois au commercial. La validation groupée ne prévient pas le technicien.
16. Messages non traduits : « validation.mimes » s'affiche tel quel, et le champ de signalement est appelé « type de réservation ».
17. Sur l'ancienne page, le bouton « Superviseur » appelle… le technicien lui-même.
18. L'aide dit « Tape Souci », mais le bouton s'appelle « Il y a un problème ».
19. Le tiroir des préférences ne s'ouvre plus.
20. « Terminer » et « Terminé » sont faciles à confondre : la narration l'expliquera.
21. L'entrée « Ma performance » du technicien ne peut jamais être atteinte.

**Petites** : deux seuils « hors créneau » (60 et 120 min), « 00h00 » affiché sur les poses sans heure, « En cours » à un endroit et « Sur place » à un autre, la carte (« Y aller » sans effet, texte non échappé), un message 404 écrit pour les clients, du code mort encore chargé, une limite de requêtes partagée par une même antenne mobile.

---

## 7. Vidéo 5 — Piloter Panora (direction et admins)

**Chapitres prévus :**
1. **Les rôles et ce que chacun voit** : admin, commercial, MP, comptable, techniciens par lien, accès nominatif (baromètre).
2. **Utilisateurs** : créer, renvoyer les accès, désactiver, codes agents.
3. **Le tableau de bord**, puis Pilotage terrain (en direct), puis la performance (commerciale, techniciens, équipes, délais).
4. **Rapports et analyses** : onglets, exports Excel et PDF, synthèse direction, récaps automatiques (quotidien, hebdomadaire direction, mensuel).
5. **Les pouvoirs de l'admin dans les dossiers** :
   - confirmer sans BC (exception motivée), refuser, annuler la réception d'un BC ;
   - corbeille des devis ;
   - conflits, réattribution d'un commercial.
6. **Clients et contacts** : regrouper deux fiches, prospects et clients, Gmail → Sheet → Panora, comptes de l'espace client.
7. **Marketing** : diffusion des disponibilités (test ou production, envoi automatique, journal, exclusions), promotions, baromètre, renouvellements (textes des mails).
8. **Supervision** : alertes, messages clients, journal des mails, audit d'une facture, paramètres (communes, zones, formats, catégories, tarifs).

### Incohérences à corriger avant le tournage (direction)

1. **« Logs d'audit » est toujours vide** : le mouchard n'est branché nulle part, et ses compteurs chercheraient de toute façon les mauvais mots.
2. **Alertes partagées** :
   - « Vider les lues » efface les alertes lues de **tout le monde** ;
   - quand un admin ouvre la page, les alertes deviennent lues pour tous.
3. **Un compte désactivé peut encore se connecter** et voir le tableau de bord. `/profile` est la page anglaise d'origine, avec un formulaire de suppression de son propre compte.
4. **Boutons qui mènent à « accès refusé »** :
   - « ＋ Nouveau panneau » et Maintenance sur le tableau de bord ;
   - les exports et la carte « Annulations » de Rapports ;
   - « ⚙ Attribuer campagnes » pour le MP ;
   - « Supprimer », « Créer un compte » et « Importer » sur les clients.
5. **Actions groupées plus larges que les actions une par une** : un MP peut supprimer des campagnes en lot alors qu'il ne peut pas en supprimer une seule. Même chose pour les réservations.
6. **Indicateurs calculés différemment selon l'écran** :
   - « CA mensuel » est un tarif catalogue, pas du chiffre d'affaires facturé ;
   - « À recouvrer » est limité à 200 factures ;
   - « Factures en retard » n'a pas la même définition partout ;
   - l'occupation par commune est calculée de 3 façons différentes, et le badge « Mois en cours » montre en fait la situation du jour.
7. **Utilisateurs** :
   - il n'y a pas de préfixe de code agent pour le comptable ;
   - le formulaire annonce un rôle Technicien qu'il ne propose pas ;
   - rien n'empêche de retirer le dernier admin ;
   - un utilisateur supprimé bloque la réutilisation de son adresse.
8. **Pages mortes ou orphelines** :
   - `admin.taxes.show` (erreur 500) ;
   - la page SLA, la vue Pilotage par équipe, l'archive des piges ;
   - plusieurs vues sans route ;
   - un écran d'édition du site vitrine promis mais absent.
9. **Droits de facturation contradictoires** : les droits du code autorisent le commercial à facturer, les routes non.
10. **Le manuel admin `docs/manuels/00-admin.md` est périmé.**
11. **Il n'y a pas d'écran** pour les promotions (commande seulement), les sauvegardes, l'état de WhatsApp, ni la double authentification (absente).
12. **Enquête de satisfaction** : risque de double invitation en fin de campagne (à vérifier).

---

## 8. Modules à part (vidéos courtes, à choisir)

Pour ne rien oublier, voici ce que les vidéos par rôle ne couvrent qu'en survol. Chacun peut faire une vidéo de 3 à 5 minutes :

| Module | Pour qui |
|---|---|
| **Clients et contacts** : fiche, interlocuteurs, regroupement, prospects, import | Commerciaux, admins |
| **L'espace client** : ce que voit le client (devis, propositions, campagnes, piges, équipe, messages). Utile pour le présenter à un client | Commerciaux, et clients |
| **Renouvellements** en entier : séquence J-60 → J-7, réponses, textes, mode test | Commerciaux, MP, admins |
| **Diffusion des disponibilités et promotions** (Brevo) | Admins, marketing |
| **Baromètre de satisfaction** | Admins |
| **Inventaire et parc** : panneaux, axes Lumipub, carte et heatmap, régies externes | MP, commerciaux |
| **Signalements, maintenance et conflits** | MP, admins |
| **Alertes et messages clients** | Tous |
| **Rapports et performance**, en détail | Direction |
