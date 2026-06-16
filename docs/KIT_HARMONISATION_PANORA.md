# KIT HARMONISATION PANORA — 3 outils pour que Claude Code arrête de faire à moitié

> Ce dossier contient TOUT ce qu'il faut pour forcer Claude Code à
> harmoniser ses modifs sur toute l'app. **Lis les 3 sections, applique
> les 3, et le problème disparaît.**

---

## 🛠️ OUTIL 1 — Fichier CLAUDE.md à la racine du projet

Le fichier ci-dessous (`CLAUDE.md`) doit être déposé à la racine de Panora
(à côté de `composer.json`, `package.json`, etc.).

Claude Code lit ce fichier **automatiquement à chaque démarrage de session**
dans un projet. C'est sa "bible" pour ce projet. C'est le mécanisme
officiellement prévu par Anthropic pour les règles permanentes.

**Contenu à mettre dans `CLAUDE.md` à la racine** :

```markdown
# CLAUDE.md — Règles permanentes du projet Panora

> ⚠️ Tu DOIS relire et appliquer ce fichier au début de CHAQUE tâche, même
> si l'utilisateur ne le mentionne pas. Ces règles ne sont jamais optionnelles.

## RÈGLE N°1 — HARMONISATION GLOBALE (la plus importante)

Quand l'utilisateur te demande une modification, tu NE te limites JAMAIS à
l'endroit qu'il a cité. Tu cherches PARTOUT dans le projet où la même
logique apparaît, et tu adaptes TOUT en même temps.

### Pourquoi
L'utilisateur a constaté à répétition que tu corriges une page mais
oublies les autres qui utilisent la même logique. Résultat : un calcul
différent entre la facture, l'aperçu, le PDF et l'email. C'est
INACCEPTABLE pour ce projet.

### Procédure obligatoire à CHAQUE modification de logique métier

Avant d'écrire la moindre ligne de code, tu DOIS :

**Étape 1 — Identifier la logique**
Donne-lui un nom clair (ex : "calcul de durée de facturation",
"formatage des montants FCFA", "calcul de la TVA", "vérification de
disponibilité d'un panneau").

**Étape 2 — Chercher partout dans le projet**
Lance des recherches `grep` exhaustives :
- Sur les noms de méthodes/variables liés
- Sur les formules brutes (ex : "/ 30", "* 30", "diffInMonths")
- Sur les chaînes affichées (ex : "mois", "FCFA", "TTC")
- Dans : controllers, services, models, jobs, commands, notifications,
  emails, observers, middlewares
- Dans : vues Blade (formulaires create/edit, vues show, vues index,
  PDFs, emails, partials)
- Dans : JavaScript inline ou externe (ATTENTION : c'est souvent ici
  qu'une copie de la logique serveur traîne et diverge)
- Dans : tests existants

**Étape 3 — LISTER avant de modifier (étape critique)**
Tu réponds à l'utilisateur AVANT de toucher au code, sous cette forme :

    J'ai trouvé X endroits qui utilisent la logique "<nom>" :

    1. app/Services/InvoiceCalculator.php (méthode foo() ligne 42)
       — Formule actuelle : ...
    2. resources/views/admin/invoices/create.blade.php (JS ligne 318)
       — Formule actuelle : ...
    3. ...

    Je vais centraliser dans <fichier unique> et adapter les X
    endroits ci-dessus pour appeler ce fichier.

    Confirme-moi de continuer.

**Étape 4 — Attendre la validation de l'utilisateur**
NE COMMENCE PAS à coder tant que l'utilisateur n'a pas validé la liste.
S'il dit "tu as oublié telle page", tu cherches à nouveau et tu mets
à jour la liste.

**Étape 5 — Centraliser**
Crée (ou utilise) UN service / helper / méthode unique qui porte la
logique. Tous les autres endroits doivent l'appeler. Aucune duplication
tolérée (sauf en JS si vraiment impossible — auquel cas, route AJAX
serveur, jamais une copie de la formule en JS).

**Étape 6 — Adapter TOUS les endroits**
Modifie chacun des X endroits listés à l'étape 3. Pas 6 sur 7. Pas 5 sur
7. LES SEPT.

**Étape 7 — Rapport final obligatoire**
À la fin de ta réponse, tu produis ce bloc :

    ✅ HARMONISATION TERMINÉE — "<nom de la logique>"

    Centralisé dans : <fichier>

    Endroits adaptés :
       ✅ <fichier 1>
       ✅ <fichier 2>
       ✅ <fichier 3>
       ...

    À vérifier manuellement par l'utilisateur :
       □ <écran A> : la valeur attendue est ...
       □ <PDF B> : la valeur attendue est ...
       □ <formulaire C> : la valeur attendue est ...

    Tests automatisés :
       ✅ <fichier de test> — N tests passent

Sans ce bloc, la mission n'est pas considérée comme terminée.

### En cas de doute
Si tu n'es pas sûr d'avoir tout trouvé, DIS-LE EXPLICITEMENT à
l'utilisateur. Mieux vaut admettre un doute que livrer une incohérence
cachée qui causera un litige client plus tard.

## RÈGLE N°2 — Lire avant d'écrire

Avant de modifier un fichier, tu le lis en entier. Avant de modifier
une table, tu vérifies sa vraie structure (`SHOW CREATE TABLE`). Les
migrations Panora ont divergé de la base réelle (plusieurs `ALTER`),
donc ne te fie qu'à l'état réel observé.

## RÈGLE N°3 — Modifications additives uniquement

Ne casse jamais le comportement existant hors du périmètre demandé. Pas
de refactor "tant qu'à faire". Pas de renommage de colonne. Pas de
suppression de méthode au prétexte qu'elle ne sert plus.

## RÈGLE N°4 — Ne pas inventer

Si l'utilisateur dit "le champ s'appelle X", vérifie qu'il existe avant
de l'utiliser. Si tu vois un appel à `$model->property` dans le code,
vérifie que cette propriété existe dans le modèle réel. Sinon, signale.

## RÈGLE N°5 — Logique métier validée à part

Pour tout calcul financier, durée, taxe, taux : la règle EXACTE doit
avoir été validée explicitement par l'utilisateur (par écrit, dans la
conversation). En cas de doute, DEMANDE avant de coder. Ne tente pas de
deviner ce qui paraît "logique".

## Points de vigilance Panora (à scanner systématiquement)

Pour toute modif touchant la facturation / calcul / affichage de
montants ou durées :

- [ ] Formulaires admin (create / edit factures, réservations, campagnes)
- [ ] JavaScript de prévisualisation en temps réel dans ces formulaires
- [ ] Vues de détail (show.blade.php)
- [ ] Vue PDF facture (FNE PDF)
- [ ] Rapports PDF (liste factures, exports comptables)
- [ ] Vues liste/index (badges, totaux affichés en colonne)
- [ ] Dashboards et KPIs
- [ ] Services PHP (InvoiceCalculator, PaymentService, etc.)
- [ ] Jobs planifiés (passage "en_retard", alertes)
- [ ] Emails et notifications client
- [ ] Exports (Excel, CSV)

Cette liste est un MINIMUM. Cherche au-delà.
```

**Dépose ce fichier `CLAUDE.md` à la racine de Panora.** Claude Code le
lira automatiquement à chaque session.

---

## 🔑 OUTIL 2 — Phrase déclencheur à recoller AVANT CHAQUE prompt

Même avec `CLAUDE.md` en place, je te recommande de **rappeler la règle
explicitement** au début de chaque nouvelle demande de modif. Pourquoi ?
Parce que Claude Code peut "oublier" les règles permanentes quand il se
concentre sur une tâche concrète.

### Le déclencheur (à copier-coller AVANT chaque demande)

```
Avant tout : applique la RÈGLE N°1 du CLAUDE.md (harmonisation globale).
Je veux d'abord la liste exhaustive de tous les endroits concernés
AVANT que tu modifies quoi que ce soit. Ne commence à coder qu'après
ma validation de la liste.

Ma demande :
<ICI tu écris ta demande normale>
```

### Exemple d'utilisation

```
Avant tout : applique la RÈGLE N°1 du CLAUDE.md (harmonisation globale).
Je veux d'abord la liste exhaustive de tous les endroits concernés
AVANT que tu modifies quoi que ce soit. Ne commence à coder qu'après
ma validation de la liste.

Ma demande :
Corrige le calcul de la TVA dans le formulaire de facturation, je
remarque qu'il calcule 17% au lieu de 18%.
```

Ce déclencheur empêche Claude Code de partir bille en tête sur la TVA
du formulaire en oubliant la TVA du PDF, des emails, des rapports, etc.

---

## 🚦 OUTIL 3 — Ton processus de validation (ce que TU fais)

C'est l'outil le plus important, et il ne dépend que de toi. Voici la
procédure que tu dois suivre à chaque demande de modif :

### Étape 1 — Tu envoies la demande avec le déclencheur

Voir Outil 2.

### Étape 2 — Tu attends la LISTE, pas le code

Claude Code doit te répondre par une liste numérotée des endroits
trouvés. **S'il commence à modifier du code directement sans liste,
arrête-le** :

> "Stop. Pas de code. Donne-moi d'abord la liste comme demandé dans
> CLAUDE.md règle N°1. Je veux valider avant que tu touches au code."

### Étape 3 — Tu vérifies la liste avec ton intuition

Pose-toi 3 questions :

1. **Le formulaire de création est-il dans la liste ?**
2. **Le formulaire d'édition est-il dans la liste ?**
3. **Le PDF / l'aperçu / les emails sont-ils dans la liste ?**

Si UNE seule réponse est non, tu réponds :

> "Tu as oublié X. Cherche aussi là et reviens avec une liste mise à
> jour."

### Étape 4 — Tu valides explicitement

Quand la liste te paraît complète, tu réponds :

> "Liste validée. Procède selon la règle N°1 — centralise, adapte tout,
> et termine par le bloc de rapport final obligatoire."

### Étape 5 — Tu vérifies le rapport final

Quand Claude Code rend son travail, il DOIT terminer par le bloc
"✅ HARMONISATION TERMINÉE". Si ce bloc manque :

> "Tu n'as pas fourni le bloc de rapport final obligatoire. Donne-moi
> la liste précise de ce qui a été modifié et la checklist de
> vérification manuelle."

### Étape 6 — Tu testes les 3 endroits "pièges"

Les 3 endroits que Claude Code oublie le plus souvent :

1. **Le JavaScript inline dans les formulaires** (souvent une copie
   buggée de la logique serveur)
2. **Les PDFs** (souvent dans un sous-dossier de vues séparé)
3. **Les emails / notifications** (souvent dans `app/Notifications`)

Teste ces 3 endroits en priorité avec la nouvelle valeur attendue.

---

## 📌 EN RÉSUMÉ — La méthode complète

1. **UNE FOIS** : tu déposes `CLAUDE.md` à la racine de Panora
2. **À CHAQUE DEMANDE** : tu colles le déclencheur avant ta demande
3. **APRÈS CHAQUE RÉPONSE** : tu vérifies la liste, puis le rapport final
4. **SI TU SENS UN OUBLI** : tu refuses la livraison et tu redemandes

Avec ces 3 outils combinés, le problème "Claude Code fait à moitié" sera
réduit de ~90%. Pas 100% (aucun LLM n'est parfait), mais suffisamment
pour que tu reprennes le contrôle sur ton app.

---

## ⚠️ Honnêteté importante

Je ne peux pas te garantir 100% de fiabilité avec un simple prompt, peu
importe sa qualité. Les LLM ont des trous de mémoire, des biais
d'attention, des moments où ils prennent un raccourci.

**C'est TON processus de validation (Outil 3) qui fait la différence.**
Le prompt et le fichier CLAUDE.md préparent le terrain ; mais c'est en
refusant les livrables incomplets que tu forces la qualité.

La bonne nouvelle : une fois que Claude Code a fait 2-3 missions avec
cette méthode et que tu as refusé fermement les premières livraisons
incomplètes, il "comprend" le standard et le maintient ensuite tout
seul pour ce projet.
