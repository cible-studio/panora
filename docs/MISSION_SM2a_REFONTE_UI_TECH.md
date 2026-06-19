# MISSION CLAUDE CODE — SM2a : Refonte UI Espace Technicien

> 📍 RÉFÉRENCE OBLIGATOIRE : lis `SM2_DOSSIER_SPECIFICATION.md` AVANT
> de toucher au code. C'est ta bible visuelle et technique. Tous les
> détails de design (couleurs, espacements, comportements) y sont.

> 🎯 OBJECTIF : refondre les 9 écrans tech (T1 à T9) selon les
> maquettes du dossier de spécification. Aucune nouvelle feature
> backend, c'est uniquement de la transformation UI sur le code SM1+.

> ⚠️ APPLIQUE LA RÈGLE N°1 DU CLAUDE.md (harmonisation globale)
> AVANT toute modification.

---

## 📋 Préconditions

Avant de lancer cette mission, vérifier :

- ✅ SM1 + SM1.5 mergées sur `develop`
- ✅ Branche `feature/tech-refonte-sm2a` créée depuis develop
- ✅ Snapshot ground truth pris (5 captures PNG dans `docs/snapshots/sm2-before/`)
- ✅ `docs/TECHNICAL_DEBT.md` à jour
- ✅ Suite phpunit baseline notée (pour comparaison post-mission)

---

## 🎯 Périmètre exact

### Inclus
- Refonte visuelle des 9 écrans tech selon `SM2_DOSSIER_SPECIFICATION.md` §3
- Création/refonte des partials Blade correspondants
- Extension des modules JS existants (pas de nouveau backend lourd)
- Ajustements CSS (extraire du `_styles.blade.php` vers fichier `.css` externe)
- Application des règles visuelles transverses §6 (couleurs sémantiques universelles)

### Exclus (sera fait en SM2b / SM2c)
- Dashboard admin live et tous écrans admin
- Polling admin et nouveaux endpoints (sauf si nécessaire pour T9)
- Écrans bonus B1, B2, B3
- Carte temps réel

---

## 🔧 Procédure détaillée

### Phase α — Préparation (1-2h)

1. **Lis le dossier de spécification complet** (`SM2_DOSSIER_SPECIFICATION.md`)
2. **Captures snapshot** : prends les 5 captures actuelles dans
   `docs/snapshots/sm2-before/` (tech-space-0-poses, 3-poses,
   50-poses, piges-historique, pige-legacy)
3. **Branche** : `git checkout -b feature/tech-refonte-sm2a develop`
4. **Note la baseline phpunit** : `php artisan test > .baseline-sm2a.txt`
5. **Inventorie les routes existantes** :
   ```bash
   php artisan route:list | grep -i tech > .routes-tech-baseline.txt
   ```

### Phase 1 — Refonte du carnet du jour T1 (3-4h)

Ce sera la base de tous les autres écrans (réutilisation du Blade
squelette et des composants).

**Lot 1.1 — Squelette + Header + Progress**
- Refonte `_topbar.blade.php` : ajouter bouton aide rond jaune 44px
- Création `_progress_bar.blade.php` : barre verte + compteur + message
  motivant (calculer message selon avancement : <30% "Allez Koffi !",
  30-70% "Continue, tu avances bien !", >70% "Plus que X !")
- Test garde-fou 1 : `node --check` sur tech-app.js
- Test garde-fou 2 : curl smoke sur `/tech/{token}/poses`

**Lot 1.2 — Card MAINTENANT**
- Refonte `_focus_card.blade.php` selon spec T1 :
  - Bordure orange 2px, fond `--c-orange-bg`
  - Étiquette "MAINTENANT" + icône flamme (svg inline)
  - Thumbnail 60×60px de `$task->panel->main_photo_url`
  - 2 boutons côte à côte : Y aller (vert) + Photo (orange)
- Si pas de pose courante : afficher card alternative "Aucune pose
  en cours, choisis-en une dans la liste ↓"
- Bandeau rouge T9 (photo refusée) doit s'afficher AU-DESSUS de
  cette card si applicable

**Lot 1.3 — Liste groupée par commune (compacte)**
- Refonte `_pose_list.blade.php` : style ultra-compact (1 ligne = 1 pose)
- Refonte `_pose_card.blade.php` ou créer `_pose_row.blade.php` pour
  la version compacte :
  - Pastille colorée 18px (🔵 à venir, 🟢 fait, 🔴 problème)
  - Nom panneau tronqué + chevron
  - Pas de thumbnail dans cette vue (économie performance)
- Variable `$problemType` qui causait un bug latent : nettoyer ici
  (cf. TECHNICAL_DEBT.md)

**Lot 1.4 — Section "Déjà faites" pliée**
- Bandeau vert avec compteur + bouton "Voir ▾"
- Toggle JS qui déplie la section (pas de modale)
- Stockage état déplié/plié en localStorage

**Lot 1.5 — Bouton tournée + Bandeau refus T9**
- Bouton secondaire bas "Voir ma tournée sur la carte" (gris)
- Si `$rejectedPhotosCount > 0` : afficher bandeau rouge épinglé en
  haut absolu, avec compteur + chevron → ouvre drawer T9

→ STOP, mini-rapport intermédiaire 1 (T1 terminé)

### Phase 2 — Écrans détail pose T2 + T7 (2-3h)

**Lot 2.1 — Drawer détail T2**
- Création `_drawer_pose_detail.blade.php` qui remplace l'ouverture
  `/pige/{token}` pour les techs (les liens WhatsApp continuent de
  marcher via le redirect 301 du `TechUrlResolverService`)
- Sections selon spec T2 §3
- Détection débutant : si flag localStorage `tech_first_use` true,
  afficher bandeau jaune d'aide contextuelle

**Lot 2.2 — Confirmation Y aller T7**
- Création `_modal_y_aller.blade.php` selon spec T7
- Mini-carte stylisée (PAS Leaflet) : juste un div avec dégradé
  bleu clair + 2 badges (toi + panneau) + ligne pointillée SVG
- Calcul des stats (5 min · 200 m) côté JS depuis position GPS si
  permission accordée, sinon valeurs par défaut basées sur le
  serveur (`$task->distance_estimate`)

→ STOP, mini-rapport intermédiaire 2 (T1+T2+T7 terminés)

### Phase 3 — Workflow photo T3 + T4 (3-4h)

C'est la phase la plus critique côté UX.

**Lot 3.1 — Modale photo prise T3**
- Création `_modal_photo_preview.blade.php` selon spec T3
- Affichage photo capturée en plein cadre
- Bandeau noir overlay GPS + heure (extrait des EXIF côté client)
- Boîte validation : verte/orange/rouge selon distance calculée
  (Haversine entre GPS photo et GPS panneau)
- Logique côté `features/upload.js` :
  1. Tech ouvre caméra → capture
  2. Capture → calculer GPS distance immédiatement
  3. Afficher T3 avec verdict (vert/orange/rouge)
  4. Bouton "Refaire" → relance caméra
  5. Bouton "Envoyer" → upload XHR + transition vers T4

**Lot 3.2 — Écran succès T4**
- Création `_screen_success.blade.php` selon spec T4
- Bandeau vert plein écran (modal full-screen 4s par défaut, puis
  transition vers T1 ou T2 du prochain)
- Calcul automatique de la pose suivante :
  - Même commune en priorité
  - Plus proche en distance GPS sinon
  - Sinon ordre de prévision
- 2 actions : "Continuer avec celle-ci" (ouvre T2 de la suivante) ou
  "Choisir une autre" (ferme T4 et revient à T1)

→ STOP, mini-rapport intermédiaire 3 (T3+T4 terminés)

### Phase 4 — Signalement T5 + T6 (2h)

**Lot 4.1 — Choix de motif T5**
- Refonte `_modal_report.blade.php` selon spec T5
- Liste verticale de 6 motifs (au lieu de la grille actuelle)
- Tap sur un motif → transition vers T6

**Lot 4.2 — Détails signalement T6**
- Nouveau partial `_modal_report_details.blade.php`
- Rappel visuel motif choisi (emoji 32px + fond jaune)
- Photo optionnelle (réutiliser pipeline upload)
- Textarea avec placeholder dynamique selon motif :
  - "Panneau cassé" → "Ex: le côté droit est arraché par le vent..."
  - "Accès bloqué" → "Ex: chantier en cours, retour demain..."
  - etc.
- Bouton "Envoyer" → POST sur route existante + retour T1

→ STOP, mini-rapport intermédiaire 4 (T5+T6 terminés)

### Phase 5 — Aide T8 + Photo refusée T9 (2h)

**Lot 5.1 — Modale aide T8**
- Création `_modal_help.blade.php` selon spec T8
- Accessible depuis bouton "?" jaune du header T1
- Stockage du flag "déjà vu" en localStorage
- 3 cards horizontales avec emojis
- Bouton tutoriel : pour l'instant lien vers URL externe (vidéo
  YouTube à fournir plus tard par la patronne)
- Bouton appel : utilise `tel:` avec le numéro du chef d'équipe

**Lot 5.2 — Photo refusée T9**
- Création `_drawer_photo_rejected.blade.php` selon spec T9
- Récupération des photos refusées via endpoint existant ou ajout
  nouveau si nécessaire :
  ```
  GET /tech/{token}/piges?rejected=1
  ```
- Affichage des données du refus :
  - Photo originale (thumbnail + lien)
  - Commentaire de refus (`pige.rejection_comment`)
  - Heure d'envoi + heure de refus
  - Distance au panneau
- Bouton "Refaire la photo" → ouvre flow T3 normal
- Bouton "Appeler le chef" → `tel:` avec numéro chef

→ STOP, mini-rapport intermédiaire 5 (T8+T9 terminés)

### Phase 6 — CSS extraction et finitions (1-2h)

**Lot 6.1 — Extraction CSS**
- Créer `public/css/tech/tech-app.css`
- Migrer le contenu de `_styles.blade.php` (934 lignes) vers ce
  fichier, en utilisant les variables CSS de la spec §6.1
- Ajouter cache-busting via `?v={app.version}`
- Garder `_styles.blade.php` réduit à 0 ligne ou supprimé
- Cette extraction résout une dette technique documentée en SM1

**Lot 6.2 — Tests finaux**
- Test sur Chrome DevTools throttling Slow 3G
- Test sur Chrome DevTools dimensions Android Go (360×640)
- Test offline (toute la SM2a doit rester utilisable hors-ligne)
- Suite phpunit : 0 régression par rapport à `.baseline-sm2a.txt`

### Phase 7 — Rapport final SM2a

Format obligatoire `✅ HARMONISATION TERMINÉE` du CLAUDE.md, avec :

- Récap des 9 écrans livrés (T1-T9)
- Bundle CSS final (lignes + KB gzip)
- Bundle JS final (vérifier que rien n'a dégradé depuis SM1.5)
- Captures comparatives avant/après (5 scénarios)
- Liste des partials créés/modifiés
- Tests phpunit : 0 régression
- Modules JS modifiés : lister
- Branche : `feature/tech-refonte-sm2a`

---

## 🛡️ Garde-fous obligatoires

1. **node --check** après chaque modification JS
2. **curl smoke test** après chaque modification de route :
   ```
   curl -s http://localhost:8000/tech/{TOKEN}/poses -o /tmp/r.html
   grep -q "tech-app" /tmp/r.html && echo OK
   ```
3. **Tests Feature** : exécution complète avant chaque commit
4. **Snapshot pixel** : comparaison avant/après pour les éléments
   non touchés (Service Worker, heartbeat, etc.)
5. **Console DevTools propre** : aucune erreur rouge en chargeant
   chaque écran

---

## ⛔ STOPs obligatoires

5 STOPs durant l'exécution (1 par phase complétée) :

1. STOP après Phase 1 (T1 terminé)
2. STOP après Phase 2 (T2 + T7 terminés)
3. STOP après Phase 3 (T3 + T4 terminés)
4. STOP après Phase 4 (T5 + T6 terminés)
5. STOP après Phase 5 (T8 + T9 terminés)

À chaque STOP : mini-rapport intermédiaire avec checklist visuelle
correspondante, puis attente du GO utilisateur avant de continuer.

Phase 6 (CSS + tests) : pas de STOP intermédiaire, mais STOP final
avant le rapport de clôture.

---

## 📋 Variables à exposer en TECH_CONFIG

Étendre `_js_config.blade.php` pour exposer :

```javascript
window.TECH_CONFIG = {
  ...existing,
  
  // Nouvelles données SM2a
  flags: {
    firstUse: localStorage.getItem('tech_first_use') !== 'false',
    hasRejectedPhotos: {{ $rejectedPhotosCount > 0 ? 'true' : 'false' }},
    rejectedPhotosCount: {{ $rejectedPhotosCount ?? 0 }},
  },
  contacts: {
    chiefPhone: @json($chiefPhone ?? null),
    tutorialVideoUrl: @json($tutorialVideoUrl ?? null),
  },
  thresholds: {
    gpsGoodMaxMeters: 100,
    gpsWarningMaxMeters: 500,
  },
  messages: {
    progressLow: "Allez Koffi !",
    progressMid: "Continue, tu avances bien !",
    progressHigh: "Plus que {n} !",
  }
};
```

---

## 🎯 Critères de réussite SM2a

- [ ] 9 écrans T1-T9 fonctionnels selon spec
- [ ] Aucune régression terrain (T1 historique fonctionne, /pige/
      legacy redirige correctement, etc.)
- [ ] Tests Feature passent (baseline préservée)
- [ ] CSS extrait dans fichier externe (dette SM1 résolue)
- [ ] Console DevTools propre sur les 9 écrans
- [ ] Test mobile Android Go OK (DevTools throttling)
- [ ] Test offline : T1 chargé reste utilisable hors-ligne
- [ ] Commits atomiques (max 200 lignes / commit, environ 12 commits
      attendus)
- [ ] Rapport final au format CLAUDE.md ✅ HARMONISATION TERMINÉE

Tu peux lancer la Phase α maintenant. Bonne route.
