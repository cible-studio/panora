# CLAUDE.md — Règles permanentes du projet Panora

> ⚠️ Je DOIS relire et appliquer ce fichier au début de CHAQUE tâche, même
> si l'utilisateur ne le mentionne pas. Ces règles ne sont jamais optionnelles.
>
> Source canonique : `docs/KIT_HARMONISATION_PANORA.md` (validé par le métier
> le 2026-06-16). En cas de désaccord entre les deux, le KIT fait foi.

---

## RÈGLE N°1 — HARMONISATION GLOBALE (la plus importante)

Quand l'utilisateur me demande une modification, je NE me limite JAMAIS à
l'endroit qu'il a cité. Je cherche PARTOUT dans le projet où la même
logique apparaît, et j'adapte TOUT en même temps.

### Pourquoi
L'utilisateur a constaté à répétition que les corrections "page par page"
laissent l'app incohérente (la facture dit X, l'aperçu dit Y, le PDF dit Z).
C'est INACCEPTABLE pour ce projet.

### Procédure obligatoire à CHAQUE modification de logique métier

**Étape 1 — Nommer la logique**
Lui donner un nom clair ("calcul de durée de facturation", "formatage
montants FCFA", "calcul TVA", "vérification disponibilité panneau"…).

**Étape 2 — Chercher PARTOUT**
Greps exhaustifs sur :
- Noms de méthodes/variables liés
- Formules brutes (`/ 30`, `* 30`, `diffInMonths`, etc.)
- Chaînes affichées ("mois", "FCFA", "TTC", "HT"…)
- Tous les répertoires : controllers, services, models, jobs, commands,
  notifications, emails, observers, middlewares, requests, policies
- Toutes les vues : formulaires create/edit, show, index, PDF, emails,
  partials, composants Blade
- JavaScript inline ou externe (souvent la duplication la plus dangereuse)
- Tests existants
- Routes (parfois la formule est dans une closure de route)

**Étape 3 — LISTER avant de modifier (étape critique)**
Avant de toucher la moindre ligne de code, je réponds à l'utilisateur sous
cette forme :

```
J'ai trouvé X endroits qui utilisent la logique "<nom>" :

1. app/Services/Xxx.php (méthode foo() ligne 42)
   — Formule actuelle : …
2. resources/views/.../yyy.blade.php (JS ligne 318)
   — Formule actuelle : …
3. …

Je vais centraliser dans <fichier unique> et adapter les X endroits.
Confirme-moi de continuer.
```

**Étape 4 — Attendre la validation explicite de l'utilisateur**
Je NE commence PAS à coder tant que l'utilisateur n'a pas validé la liste.
S'il dit "tu as oublié telle page", je cherche à nouveau et je mets à jour.

**Étape 5 — Centraliser**
Créer (ou réutiliser) UN service / helper / méthode unique qui porte la
logique. Tous les autres endroits doivent l'appeler. Aucune duplication
tolérée. Si le JS a besoin de la valeur : route AJAX serveur, JAMAIS une
copie de la formule en JS.

**Étape 6 — Adapter TOUS les endroits**
Modifier chacun des X endroits listés. Pas N-1. TOUS.

**Étape 7 — Rapport final obligatoire**
À la fin de la mission, je produis ce bloc (sans lui, la mission n'est
pas considérée comme terminée) :

```
✅ HARMONISATION TERMINÉE — "<nom de la logique>"

Centralisé dans : <fichier>

Endroits adaptés :
   ✅ <fichier 1>
   ✅ <fichier 2>
   …

À vérifier manuellement par l'utilisateur :
   □ <écran A> : la valeur attendue est …
   □ <PDF B>  : la valeur attendue est …
   □ <formulaire C> : la valeur attendue est …

Tests automatisés :
   ✅ <fichier de test> — N tests passent
```

### En cas de doute
Si je ne suis pas sûr d'avoir tout trouvé, JE LE DIS EXPLICITEMENT. Mieux
vaut admettre un doute que livrer une incohérence cachée qui causera un
litige client plus tard.

### Les 3 endroits "pièges" à ne JAMAIS oublier

1. **JavaScript inline des formulaires** (souvent une copie buggée de la
   logique serveur)
2. **Les PDF Blade** (souvent dans `resources/views/pdf/` ou
   `resources/views/.../pdf/`)
3. **Les emails / notifications** (souvent dans `resources/views/emails/`
   ou `app/Notifications/`, ou en `@php` inline dans le mailable)

Si l'un des 3 manque dans ma liste, je vais y retourner avant de coder.

---

## RÈGLE N°2 — LIRE AVANT D'ÉCRIRE

Avant de modifier un fichier, je le lis en entier (ou au minimum la zone
touchée + ses appelants). Avant de modifier une table, je vérifie sa vraie
structure (`SHOW CREATE TABLE`). Les migrations Panora ont divergé de la
base réelle (plusieurs `ALTER`), donc seule la structure observée fait foi.

---

## RÈGLE N°3 — MODIFICATIONS ADDITIVES UNIQUEMENT

Je ne casse JAMAIS le comportement existant hors du périmètre demandé. Pas
de refactor "tant qu'à faire". Pas de renommage de colonne. Pas de
suppression de méthode au prétexte qu'elle ne sert plus.

---

## RÈGLE N°4 — NE PAS INVENTER

Si l'utilisateur dit "le champ s'appelle X", je vérifie qu'il existe avant
de l'utiliser. Si je vois un appel à `$model->property` dans le code, je
vérifie que cette propriété existe dans le modèle réel. Sinon, je signale.

---

## RÈGLE N°5 — LOGIQUE MÉTIER VALIDÉE À PART

Pour tout calcul financier, durée, taxe, taux : la règle EXACTE doit avoir
été validée explicitement par l'utilisateur (par écrit, dans la
conversation). En cas de doute, je DEMANDE avant de coder. Je ne tente pas
de deviner ce qui paraît "logique".

---

## Points de vigilance Panora (à scanner systématiquement)

Pour toute modif touchant la facturation / calcul / affichage de montants
ou durées :

- [ ] Formulaires admin (create / edit factures, réservations, campagnes,
      poses, piges)
- [ ] JavaScript de prévisualisation en temps réel dans ces formulaires
- [ ] Vues de détail (`show.blade.php`)
- [ ] Vue PDF facture (FNE PDF)
- [ ] Rapports PDF (liste factures, exports comptables, disponibilités)
- [ ] Vues liste/index (badges, totaux affichés en colonne)
- [ ] Dashboards et KPIs (admin + client)
- [ ] Services PHP (`InvoiceCalculator`, `PaymentService`,
      `ScheduleGenerator`, `ReservationService`, `CampaignService`,
      `BillingAllocationService`, etc.)
- [ ] Jobs planifiés (passage `en_retard`, alertes J-15/J0/J+30)
- [ ] Emails et notifications client (`resources/views/emails/*`,
      `resources/views/admin/emails/*`, `app/Mail/*`)
- [ ] Exports (Excel, CSV)
- [ ] Routes / endpoints AJAX
- [ ] Tests Unit + Feature

Cette liste est un MINIMUM. Chercher au-delà.

---

## Conventions Panora à respecter

- **Tout commit → develop ET main** par défaut (merge --no-ff develop → main),
  la prod déploie depuis main. **Sauf instruction contraire explicite** ("ne
  commit que sur develop pour tester avant"), auquel cas commit develop seul.
- **Montants FCFA en entier** (BIGINT UNSIGNED, pas de centimes). Casts
  `'integer'` côté Eloquent.
- **Snapshot taxes** : `Commune::ratesAt($date)` pour figer les taux à la
  date d'émission, jamais le taux courant.
- **Audit trail** via `owen-it/laravel-auditing` sur les modèles métier
  sensibles (Invoice, InvoiceLine, InvoiceService, InvoicePayment, Relance).
- **WhatsApp** : compte Twilio en Trial, prod en pause — ne pas activer
  l'envoi prod sans upgrade explicite.
- **Code-review** : avant de marquer une mission "terminée", relire le diff
  comme un reviewer externe.
