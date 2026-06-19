# MISSION CLAUDE CODE — SM2c : Écrans bonus + finitions

> 📍 RÉFÉRENCE OBLIGATOIRE : lis `SM2_DOSSIER_SPECIFICATION.md` AVANT
> de toucher au code.

> 🎯 OBJECTIF : ajouter 3 écrans bonus (B1, B2, B3) qui complètent
> l'expérience tech, et faire les finitions transverses.

> ⚠️ PRÉCONDITION : SM2a + SM2b terminées et mergées, stables 48h+
> en conditions réelles.

> ⚠️ APPLIQUE LA RÈGLE N°1 DU CLAUDE.md.

---

## 📋 Préconditions strictes

- ✅ SM2a + SM2b mergées sur `develop`
- ✅ Observation 48h+ en conditions réelles sans remontée critique
- ✅ Branche `feature/tech-bonus-sm2c` créée depuis develop
- ✅ Suite phpunit baseline notée

---

## 🎯 Périmètre exact

### Inclus
- 3 écrans bonus côté tech (B1, B2, B3) selon spec §5
- Finitions transverses (préférences, polices, accessibilité)
- Activation des fonctionnalités préparées en SM2a/b mais non
  encore branchées

### Exclus
- Aucun nouveau backend lourd (sauf endpoint léger pour B3 si
  nécessaire)
- Pas de nouveaux écrans admin

---

## 🔧 Procédure détaillée

### Phase α — Préparation (30 min)

1. Lis spec complète
2. `git checkout -b feature/tech-bonus-sm2c develop`
3. `php artisan test > .baseline-sm2c.txt`
4. Snapshot des écrans tech actuels (sécurité)

### Phase 1 — B1 Pose hors heure (1h)

**Lot 1.1 — Détection serveur**

Quand le tech demande à ouvrir une pose via T2, le controller
vérifie :

```php
$now = now();
$scheduledAt = $task->scheduled_at;
$tolerance = config('tech_space.schedule_tolerance', 60); // 60 min

if ($scheduledAt && abs($now->diffInMinutes($scheduledAt)) > $tolerance) {
    // Marquer la tâche comme "hors heure" sera fait au moment du status change
    $task->is_off_schedule_attempt = true;
    // (Pas d'écriture en BDD ici, juste un flag local pour la vue)
}
```

**Lot 1.2 — Modale B1**

Crée `_modal_off_schedule.blade.php` selon spec B1. S'affiche
au-dessus de T2 si `$task->is_off_schedule_attempt`.

Logique :
- Bouton "Oui je continue" → `localStorage['off_schedule_ack_{taskId}'] = true`
  + ferme la modale + procède normalement
- Bouton "Non je reviens" → ferme modale + ferme T2 → retour T1

**Lot 1.3 — Marquage côté serveur**

Quand le tech valide la pose hors heure et envoie sa photo, la
`Pige` est créée avec un flag `is_off_schedule = true`. Visible
côté admin dans la fiche tech A2 timeline avec une indication.

→ STOP, mini-rapport intermédiaire 1

### Phase 2 — B2 Fin de journée (1-2h)

**Lot 2.1 — Détection complétion**

Quand le tech finit sa dernière pose (envoi de la 12ème photo sur
12), au lieu d'afficher T4 puis revenir à T1, on transitionne vers
B2.

Logique côté JS dans `features/upload.js` :

```javascript
async function onUploadSuccess(response) {
  if (response.is_last_pose_of_day) {
    showEndOfDayScreen(response);
  } else {
    showSuccessScreen(response);
  }
}
```

Côté serveur, la réponse de l'upload inclut un booléen
`is_last_pose_of_day` calculé en fin de traitement :

```php
$todayTasksRemaining = PoseTask::where('assigned_user_id', $tech->id)
    ->whereDate('scheduled_at', today())
    ->where('status', '!=', 'realisee')
    ->count();

return response()->json([
    ...,
    'is_last_pose_of_day' => $todayTasksRemaining === 0,
]);
```

**Lot 2.2 — Écran B2**

Crée `_screen_end_of_day.blade.php` selon spec B2.

Animation confettis : pure CSS, pas de bibliothèque externe. Exemple :

```css
@keyframes confetti-fall {
  0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
  100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
}
.confetti {
  position: absolute;
  width: 8px; height: 8px;
  background: var(--c-orange-action);
  animation: confetti-fall 3s ease-out forwards;
}
```

20-30 particules générées en JS au mount de l'écran. Pas de loop
infinie (économie batterie).

**Lot 2.3 — Stats journée**

Calculées depuis le payload final :
- Temps total : `last_pose_time - first_action_time`
- Distance parcourue : optionnel, somme des distances GPS entre
  poses consécutives (si GPS dispo)
- Poses/heure moyenne : `total_poses / temps_total_heures`

**Lot 2.4 — Boutons fin**

- "Retour à l'accueil" → revient à T1 (qui affichera l'état "0 poses
  restantes, journée terminée")
- "Demander une nouvelle tournée" → envoie un message au chef via
  une notification interne (pas de Twilio)

→ STOP, mini-rapport intermédiaire 2

### Phase 3 — B3 Centre de notifications (2h)

**Lot 3.1 — Modèle Notification**

Si pas déjà existant, créer une table légère :

```php
Schema::create('tech_notifications', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained();
    $t->string('type'); // photo_rejected, new_pose, ...
    $t->string('title');
    $t->text('detail');
    $t->json('payload')->nullable(); // task_id, pige_id, etc.
    $t->timestamp('read_at')->nullable();
    $t->timestamps();
    $t->index(['user_id', 'read_at']);
});
```

Note : si Laravel Notifications est déjà en place, l'utiliser à la
place.

**Lot 3.2 — Émission des notifications**

Modifie les controllers existants pour créer une notification :
- Quand `Pige::rejection_comment` est set → notif "photo_rejected"
- Quand `PoseTask` est créée avec `assigned_user_id` → notif "new_pose"
- Quand une `Pige` est validée → notif "photo_validated"

**Lot 3.3 — Endpoint et vue**

```php
Route::get('/tech/{token}/notifications', ...)->name('tech.notifications');
Route::post('/tech/{token}/notifications/mark-read', ...);
```

Crée `_drawer_notifications.blade.php` selon spec B3.

Filtres rapides (chips en haut) :
- "Toutes"
- "Photos refusées"
- "Nouvelles poses"

Liste : 1 ligne par notif avec icône colorée, titre, détail, heure
relative. Point bleu si non lue.

**Lot 3.4 — Badge sur le bouton "?" du header T1**

Si des notifs non lues, ajouter petit badge rouge sur le bouton
aide jaune du header. Tap → ouvre B3 (au lieu de T8).

Note : on garde la double fonction du bouton "?" :
- Si pas de notifs non lues : ouvre T8 (aide)
- Si notifs non lues : ouvre B3 (centre notifs)

C'est un compromis pour ne pas ajouter un nouveau bouton dans le
header déjà chargé. À documenter dans T1.

→ STOP, mini-rapport intermédiaire 3

### Phase 4 — Finitions transverses (2h)

**Lot 4.1 — Préférences tech**

Création d'un drawer accessible depuis le menu (ou tap long sur le
header T1) :

`_drawer_tech_preferences.blade.php` avec :
- Toggle "Notifications visuelles" (on/off)
- Toggle "Confirmer avant Y aller" (active/désactive T7)
- Toggle "Mode économie batterie" (réduit polling à 60s, désactive
  animations)
- Choix langue (FR par défaut, plus tard EN/wolof si demandé)

Stockage localStorage + sync serveur (création table légère
`tech_preferences` ou réutilisation d'une table existante).

**Lot 4.2 — Accessibilité**

- Tous les boutons icône-only doivent avoir `aria-label`
- Contraste vérifié sur tous les boutons (WCAG AA minimum)
- Taille de police minimum 13px partout
- Focus visible sur navigation clavier (si tech utilise un téléphone
  avec un mode accessibilité)

**Lot 4.3 — Mode haute lisibilité**

Toggle dans préférences : "Texte plus gros". Multiplie toutes les
tailles de police par 1.2x. Stockage localStorage.

**Lot 4.4 — Performance audit**

Lance Lighthouse sur les écrans tech principaux :
- T1 (carnet) → score Performance > 85
- T2 (détail) → score Performance > 85
- T3 (photo prise) → score Performance > 80

Si score < seuils, identifie les optimisations possibles :
- Compression images de référence
- Lazy loading des thumbnails
- Réduction du bundle JS

→ STOP, mini-rapport intermédiaire 4

### Phase 5 — Rapport final SM2c

Format `✅ HARMONISATION TERMINÉE` du CLAUDE.md.

---

## 🛡️ Garde-fous obligatoires

1. **node --check** + **curl smoke test** sur chaque modification
2. **Tests Feature** pour les nouvelles fonctionnalités (B1, B2, B3,
   préférences)
3. **Test fin de journée** : créer un tech avec 1 pose unique, la
   compléter, vérifier que B2 s'affiche
4. **Test notifications** : créer un refus de photo en BDD, vérifier
   l'apparition du badge sur "?"
5. **Test off schedule** : modifier `scheduled_at` d'une pose à
   +5h, vérifier que B1 s'affiche

---

## ⛔ STOPs obligatoires

4 STOPs durant l'exécution (1 par phase complétée).

---

## 🎯 Critères de réussite SM2c

- [ ] 3 écrans bonus B1, B2, B3 fonctionnels
- [ ] Préférences tech persistées (localStorage + sync serveur)
- [ ] Accessibilité de base (aria-label, contraste, focus)
- [ ] Mode haute lisibilité opérationnel
- [ ] Score Lighthouse Performance > 80 sur écrans clés
- [ ] Suite phpunit baseline préservée
- [ ] Aucune régression sur les écrans T1-T9 et A1-A5
- [ ] Rapport final au format CLAUDE.md ✅ HARMONISATION TERMINÉE

Tu peux lancer la Phase α maintenant.
