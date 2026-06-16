# MISSION CLAUDE CODE — Correction du bug "échéances marquées payées à tort"

> 📍 Ce brief s'applique en complément du `CLAUDE.md` à la racine du
> projet (RÈGLE N°1 harmonisation globale). Tu DOIS suivre la procédure
> de listing AVANT de modifier quoi que ce soit.

> ⚠️ Cette mission a 3 phases. Tu NE PASSES PAS à la phase suivante sans
> validation explicite de l'utilisateur entre chaque phase.

---

## 🐛 Le bug observé (cas concret FAC-2026-010)

L'utilisateur a constaté sur la facture FAC-2026-010 :

- **Bandeau principal** : "Partiellement soldée — 53 000 / 398 700 FCFA
  (13,3 %) — Reste à payer : 345 700 FCFA"
- **Versements enregistrés** : 50 000 FCFA (espèces, 10/06) +
  3 000 FCFA (chèque, 11/06) = **53 000 FCFA au total**
- **Échéancier prévisionnel** (incohérent) :
  - Acompte 30 % — 119 610 FCFA — **badge "PAYÉE LE 10/06"** ❌
  - Solde 70 % — 279 090 FCFA — **badge "PAYÉE LE 16/06"** ❌

**Problème** : les deux échéances sont marquées payées alors que le
client n'a versé que 53 000 F sur les 398 700 F dus. Le total
échéancier "payé" affiché (398 700 F) ne correspond PAS au total
réellement encaissé (53 000 F).

C'est un **bug critique** : un commercial ou comptable qui regarde
cette facture peut croire que tout est réglé alors qu'il manque
345 700 F à recouvrer.

---

## 📍 PHASE 1 — Audit du code existant

**OBJECTIF** : comprendre comment fonctionne aujourd'hui le marquage
des échéances. NE MODIFIE RIEN À CE STADE.

### Ce que tu dois chercher

Cherche dans tout le projet Panora :

1. **Modèles** : `PaymentSchedule` (ou nom équivalent : Schedule,
   Echeance, InvoiceSchedule, Echeancier...). Liste ses colonnes
   réelles via `SHOW CREATE TABLE` ou la migration la plus récente.

2. **Modèles paiements** : `Payment` (ou Versement, etc.). Liste ses
   colonnes.

3. **Lien échéance ↔ paiement** : existe-t-il une colonne
   `schedule_id` sur `payments` ? Une table pivot
   `payment_schedule_payments` ? Ou aucun lien direct (c'est-à-dire
   l'échéance se calcule en parcourant les versements) ?

4. **Logique de marquage "payée"** : où le code décide-t-il qu'une
   échéance passe en statut "payée" ?
   - Cherche : `status = 'payee'`, `markAsPaid`, `setPaid`,
     `paid_at`, `payee`, etc.
   - Dans : Observers (sur Payment ou PaymentSchedule), Services
     (`PaymentService`, `ScheduleService`), Controllers,
     Jobs/Commands.

5. **Affichage actuel** : où s'affiche le badge "PAYÉE LE XX/XX" ?
   - Cherche les vues Blade qui affichent l'échéancier.
   - Repère comment elles déterminent le statut (champ direct en base
     ? calcul à la volée ?).

### Réponse attendue (format strict)

```
=== AUDIT ÉCHÉANCIER PANORA ===

📋 STRUCTURE DES DONNÉES

Table payment_schedules (ou nom réel) :
  - colonnes : id, invoice_id, label, due_date, amount, status, ...
  - statut possibles (enum) : a_venir, echue, payee, ...
  - liens : ...

Table payments (ou nom réel) :
  - colonnes : ...
  - lien avec échéance : OUI (colonne schedule_id) / NON / via table pivot

📋 LOGIQUE ACTUELLE DE MARQUAGE "PAYÉE"

Le statut "payee" d'une échéance est défini :
  - Endroit 1 : <fichier>:<ligne> — <ce que fait le code>
  - Endroit 2 : ...

Méthode utilisée :
  ☐ Comparaison stricte (cumul versements ≥ montant échéance)
  ☐ Marquage automatique dès qu'un paiement existe dans la période
  ☐ Marquage manuel par un utilisateur
  ☐ Autre : ...

📋 DIAGNOSTIC DU BUG

Cause probable du bug observé sur FAC-2026-010 :
  <explication précise basée sur le code lu>

📋 ENDROITS AFFECTÉS PAR LE BUG

Pages/vues où l'incohérence est visible pour l'utilisateur :
  - <fichier blade 1>
  - <fichier blade 2>
  - PDF/exports : ...
  - Emails : ...
```

**STOP ici. Attends la validation de l'utilisateur avant d'aller
plus loin.**

---

## 📍 PHASE 2 — Recommandation métier (à valider avec l'utilisateur)

Une fois l'audit validé, propose à l'utilisateur la **règle métier**
qui doit régir l'imputation des versements sur les échéances.

**Tu dois présenter clairement 3 options standards** avec leurs
conséquences concrètes sur ses factures (utilise des cas issus
d'autres factures réelles de la base si possible, sinon des exemples
simples).

### Les 3 options à présenter

**Option A — Pot commun (sans imputation)**
- Les échéances sont juste un calendrier informatif.
- Le statut d'une échéance dépend uniquement de sa date et du
  cumul global encaissé sur la facture.
- Simple, mais perd la traçabilité « quelle échéance est en retard ».

**Option B — FIFO avec débordement (standard comptable mondial)**
- Les versements remplissent les échéances dans l'ordre
  chronologique. L'excédent déborde sur la suivante.
- Utilisé par Sage, QuickBooks, Cegid, etc.
- Bon équilibre traçabilité ↔ flexibilité.

**Option C — Imputation explicite par l'utilisateur**
- À chaque versement, l'utilisateur choisit à quelle(s) échéance(s)
  l'affecter.
- Plus de contrôle, mais plus de saisie.

### Recommandation que tu dois donner

Après avoir étudié le code Panora (modèles, vues, workflow actuel),
**recommande à l'utilisateur l'option la plus adaptée à son contexte**
(régie publicitaire OOH en Côte d'Ivoire, facturation B2B avec
acomptes fréquents, paiements multiples mobile money/espèces/chèque
souvent partiels).

Argumente ta recommandation en 3-5 lignes. Ne fais PAS comme si toutes
les options se valaient. Tu as accès au code, tu connais le métier,
**TRANCHE**.

### Format de réponse attendu

```
=== RECOMMANDATION RÈGLE D'IMPUTATION ===

Compte tenu de :
  - <fait 1 observé dans le code/contexte Panora>
  - <fait 2>
  - <fait 3>

Je recommande : OPTION <X>

Pourquoi :
  - <raison 1>
  - <raison 2>
  - <raison 3>

Conséquence sur FAC-2026-010 (cas réel de l'utilisateur) :
  Versements : 50 000 + 3 000 = 53 000 F encaissés
  Avec la règle recommandée :
    - Échéance 1 (119 610 F) → <statut + montant imputé>
    - Échéance 2 (279 090 F) → <statut>

Conséquence sur les autres factures de la base : <impact estimé>.

Attends ma validation avant de coder.
```

**STOP ici. Attends la validation de l'utilisateur.**

---

## 📍 PHASE 3 — Implémentation (après validation des phases 1 et 2)

Une fois la règle métier validée par l'utilisateur, tu appliques
strictement la **RÈGLE N°1 du CLAUDE.md** (harmonisation globale) :

### Étape 3.1 — Plan détaillé (à valider avant d'exécuter)

Présente le plan complet :
- Nouveau service à créer (ex. `ScheduleAllocationService`) ou
  méthode à ajouter à `PaymentService`
- Liste exhaustive des endroits à modifier (modèles, observers,
  controllers, vues Blade, JS, PDFs, emails, jobs)
- Migration éventuelle si une colonne doit être ajoutée
- Script de recalcul rétroactif pour les factures existantes
  (utilisateur a validé qu'on recalcule)

Format :

```
=== PLAN D'IMPLÉMENTATION ===

1. Service centralisé :
   - À créer : app/Services/ScheduleAllocationService.php
   - Méthode principale : recomputeFromPayments(Invoice $invoice)
   - Source unique de vérité : tout statut d'échéance dérive de
     cette méthode.

2. Migration éventuelle :
   - <oui/non + détail>

3. Endroits à adapter (X au total) :
   ✏️ app/Models/PaymentSchedule.php (accesseur de statut)
   ✏️ app/Services/PaymentService.php (appel après chaque versement)
   ✏️ app/Observers/PaymentObserver.php (déclencher après create/update/delete)
   ✏️ resources/views/admin/invoices/show.blade.php (affichage)
   ✏️ resources/views/admin/invoices/pdf.blade.php (PDF)
   ✏️ ... (lister TOUT)

4. Recalcul rétroactif :
   - Commande artisan : php artisan schedules:recompute-all
   - Dry-run d'abord (option --dry-run) qui affiche ce qui va changer
     sans toucher à la base.

5. Tests automatisés :
   - Cas 1 : versement partiel < échéance 1 → partielle
   - Cas 2 : versement = échéance 1 → payée
   - Cas 3 : versement > échéance 1 → débordement sur échéance 2
   - Cas 4 : annulation d'un versement → recalcul rétroactif
   - Cas 5 : modification d'un versement → recalcul rétroactif

Attends ma validation pour exécuter.
```

### Étape 3.2 — Exécution

Après validation du plan :

1. Crée le service / la méthode centralisée. Source unique de vérité.
2. Crée les tests (Pest ou PHPUnit, selon le standard du projet).
   Lance-les. Tous doivent passer AVANT d'aller plus loin.
3. Adapte chacun des endroits du plan. Pas un de moins.
4. Crée la commande de recalcul rétroactif AVEC option `--dry-run`.
5. Lance le dry-run et présente le diff à l'utilisateur :

```
=== DRY-RUN RECALCUL ÉCHÉANCIERS ===

Factures à modifier : X

Détail :
  FAC-2026-001 : 0 changement
  FAC-2026-010 : 2 changements
    - Échéance "Acompte 30 %" : payee → partiellement_payee (44,3 %)
    - Échéance "Solde 70 %"   : payee → a_venir
  FAC-2026-011 : ...

Lance la commande sans --dry-run pour appliquer.
```

6. Attends que l'utilisateur dise « applique ». Puis exécute le
   recalcul réel.

### Étape 3.3 — Rapport final OBLIGATOIRE

Termine par le bloc imposé par le `CLAUDE.md` :

```
✅ HARMONISATION TERMINÉE — Statut des échéances de paiement

Centralisé dans : app/Services/ScheduleAllocationService.php
Règle métier appliquée : <Option choisie par l'utilisateur>

Endroits adaptés (X au total) :
   ✅ <fichier 1>
   ✅ <fichier 2>
   ...

Recalcul rétroactif effectué :
   ✅ N factures recalculées
   ✅ M échéances mises à jour
   (détail dans logs/schedule-recompute-YYYY-MM-DD.log)

Tests automatisés :
   ✅ tests/Unit/ScheduleAllocationServiceTest.php — N tests passent
   ✅ tests/Feature/ScheduleStatusFlowTest.php — M tests passent

À vérifier manuellement par l'utilisateur dans Panora :
   □ Facture FAC-2026-010 :
       - Échéance 1 doit afficher "Partielle 44,3 %" (53 000/119 610)
       - Échéance 2 doit afficher "À venir" (0/279 090)
   □ Ajouter un versement de 50 000 F sur FAC-2026-010 :
       - L'échéance 1 doit basculer en "Partielle 86 %"
   □ Ajouter un versement qui dépasse l'échéance 1 :
       - L'échéance 1 doit passer "Payée"
       - L'excédent doit s'imputer sur l'échéance 2
   □ Supprimer un versement existant :
       - Le statut des échéances doit se recalculer automatiquement
   □ Le PDF de la facture doit refléter les nouveaux statuts
   □ La page liste des factures doit afficher les bons % d'avancement
```

---

## 📌 Rappels critiques

- **NE COMMENCE PAS PAR LA PHASE 3.** Phase 1 (audit) d'abord, attente
  de validation, puis Phase 2 (recommandation), attente, puis Phase 3.

- **Si tu ne trouves pas une information** dans le code, dis-le
  explicitement. Ne devine pas.

- **Le recalcul rétroactif est SENSIBLE** : un bug ici peut corrompre
  toutes les factures de l'app. Le `--dry-run` n'est pas optionnel.

- **Toutes les pages affichant un statut d'échéance** doivent passer
  par le nouveau service. Une seule copie locale du calcul = bug
  futur garanti.
