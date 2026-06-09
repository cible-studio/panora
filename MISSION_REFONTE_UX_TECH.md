# MISSION CLAUDE CODE — Refonte UX espace technicien + Vérif géoloc

> IMPORTANT : ce document remplace toute proposition de design que tu as
> faite précédemment pour l'espace technicien. Le design actuel (cartes
> verticales empilées, beaucoup de texte) est REJETÉ par le métier. Ne le
> redéfends pas. Applique la nouvelle logique ci-dessous.

---

## PARTIE A — D'ABORD : vérifie que les étapes géo précédentes sont faites

Avant de toucher à l'UI, réponds précisément (dans ta réponse, pas juste
dans ta tête) à cette CHECKLIST. Pour chaque point : ✅ fait / ❌ pas fait /
⚠️ partiel + explication. Si quelque chose manque, FINIS-LE avant l'UI.

### Étape 1 — Auto-géolocalisation panneaux
- [ ] Migration `panels` : colonnes `gps_source`
      (manual|pige_provisional|pige_confirmed), `gps_dispersion_flag`,
      `gps_computed_at` existent et sont migrées ?
- [ ] `GeoService` existe avec `haversine`, `median`, `maxDispersion` ?
- [ ] `PanelGeoLocator::recomputeFromPiges()` existe avec : règle manual
      intouchable, 1 pige=provisional, 2+=médiane confirmed, dispersion>100m
      = flag, garde-fou "ne pas écraser un lat/lng existant si gps_source NULL" ?
- [ ] Branché dans `PigeService::verify()` ET `PigeController::update()`
      (cas status=verifie) en try/catch best-effort ?
- [ ] `verifyBatch` couvert (il boucle sur verify) — confirmé ?
- [ ] Modèle `Panel` : fillable + casts des 3 colonnes ?
- [ ] Le formulaire création/édition panneau pose `gps_source='manual'`
      quand l'admin saisit lat/lng ?
- [ ] Bug corrigé dans `panels/show.blade.php` : `$pige->is_verified`
      remplacé par `$pige->isVerifiee()` ?

### Étape 2 — Anti-fraude pige
- [ ] Migration `piges` : `geo_distance_m`, `geo_check`
      (ok|warn|out|no_gps|no_panel_gps) ?
- [ ] Observer central sur `Pige` (creating) qui calcule distance+verdict,
      traversant LES 4 chemins d'upload ? (vérifie qu'il n'y a pas 2
      observers en conflit sur Pige)
- [ ] Seuils ≤50=ok / ≤200=warn / >200=out respectés ?
- [ ] Affichage badge geo sur `piges/validation`, `piges/show`, `piges/index` ?
- [ ] Flag purement informatif (ne bloque AUCUN upload/validation) ?

### Étape 3 — Backfill
- [ ] Commande `php artisan geo:backfill` existe et est idempotente ?
- [ ] Elle peuple les panneaux depuis piges validées historiques + classe
      les piges existantes (geo_check) ?

### Étape 4 — Carte & SLA
- [ ] Carte poses se remplit maintenant que les panneaux se géolocalisent ?
- [ ] Marqueur distinct pour `gps_dispersion_flag=true` ?
- [ ] KPI "taux piges hors-zone" ajouté au SLA ?

> Donne le bilan de cette checklist EN PREMIER dans ta réponse. Ensuite,
> attaque la Partie B.

---

## PARTIE B — Refonte UX espace technicien (le vrai sujet)

### Le problème central à résoudre
Un technicien reçoit UN lien WhatsApp vers son espace perso
(`/tech/{token}/poses`). Il peut avoir **10+ poses de campagnes DIFFÉRENTES**.
Le design actuel empile 10 grosses cartes verticales pleines de texte →
ingérable sur un téléphone, au soleil, avec des gants, par quelqu'un peu
alphabétisé.

### Principe directeur (à appliquer absolument)
**Le technicien ne pense pas en "campagnes". Il pense en "où je vais et quoi
je fais maintenant".** Donc on N'ORGANISE PAS l'écran par campagne. La
campagne devient une info secondaire (petit label discret).

### Nouvelle logique d'affichage (le cœur de la refonte)

1. **En-tête compact avec progression visuelle.**
   - Grand compteur : "5 à faire" + barre de progression (ex. 3/8 faites,
     remplie en vert). Le tech voit sa liste se vider = motivant.
   - Pas de jargon, gros chiffres, fort contraste.

2. **Regroupement par COMMUNE / ZONE (pas par campagne).**
   - Sections : "📍 ABOBO — 3 poses", "📍 COCODY — 2 poses"...
   - Logique tournée : le tech fait toute une zone avant de se déplacer.
   - Chaque section est pliable/dépliable (collapsée par défaut sauf la
     zone la plus prioritaire/proche).

3. **Tri intelligent à l'intérieur :**
   - Les poses EN RETARD remontent en haut (badge rouge discret).
   - Si le tech autorise sa géoloc : trier par proximité ("la plus proche
     de toi en premier"). Réutilise GeoService::haversine côté serveur ou
     calcule côté client avec sa position.

4. **Chaque pose = une LIGNE COMPACTE, pas une grosse carte :**
   - Vignette PHOTO du panneau cible à gauche (réutilise panel->photos,
     première photo ; si aucune photo, placeholder neutre). C'EST CLÉ : le
     tech reconnaît le lieu visuellement.
   - Référence panneau (gros, monospace) + nom court.
   - Pastille de couleur d'état (pas du texte de statut).
   - UN bouton/zone tap principale.

5. **Quand une pose est faite → elle quitte la liste active** (passe dans
   une section "✓ Faites" repliée en bas, grisée). La liste active se vide.

### Parcours en 2 gestes (validé avec le métier)
- **Geste 1** : le tech tape sur la ligne de la pose → ça OUVRE DIRECTEMENT
  l'appareil photo arrière (`<input type="file" accept="image/*"
  capture="environment">` — déjà présent dans ton code, réutilise-le). PAS
  d'écran intermédiaire "détail de la pose".
- **Geste 2** : il prend la photo → la photo + le GPS sont capturés
  (`navigator.geolocation.getCurrentPosition`, augmente le timeout à ~10s
  avec retry), aperçu rapide, puis UN gros bouton "✅ Envoyer". À l'envoi :
  upload + la pose passe à "réalisée" automatiquement (le code
  `TechSpaceController::uploadPhoto` le fait déjà : il crée la pige ET marque
  COMPLETED — réutilise cette logique).
- **Feedback fort au succès** : grand check vert plein écran +
  `navigator.vibrate(200)` si dispo. La pose disparaît de la liste active.

### Boutons secondaires (discrets, pas au même niveau que la photo)
- "🧭 Y aller" → ouvre Google Maps / Waze vers `panel.latitude,longitude`
  si dispo (sinon vers l'adresse texte). Lien `https://www.google.com/maps/
  dir/?api=1&destination=LAT,LNG`.
- "⚠️ Problème" → signaler (panneau cassé / accès bloqué / mauvaise adresse)
  avec photo → crée une alerte MP via AdminAlertNotifier.

### Style visuel (le métier trouve l'actuel "moche")
- Palette claire, moderne, sobre. Fond clair, cartes/lignes à coins
  arrondis doux, ombres légères. ÉVITE les gros aplats violet/bleu vifs
  côte à côte (l'actuel fait "criard").
- Couleurs d'état cohérentes et douces : à faire = neutre/ambre, en retard
  = rouge doux, faite = vert. Une SEULE couleur d'accent forte par écran.
- Typo généreuse, hiérarchie claire (référence panneau = l'élément le plus
  gros après le compteur d'en-tête).
- Tap targets ≥ 48px, contraste fort (lisible plein soleil), utilisable à
  une main (actions principales en bas de l'écran/pouce).
- Pictogrammes + couleurs portent le sens ; le texte est un appui, pas la
  source principale d'information.
- Mobile-first strict (cible ~360–400px de large). Pas de SPA lourde, Blade
  + un peu de JS/Alpine, PWA acceptable.

### Contraintes techniques (ne rien casser)
- Lis AVANT toute modif : `TechSpaceController` (show + buildPayload +
  uploadPhoto + updateStatus), la vue `resources/views/public/tech-space.
  blade.php`, et `resources/views/public/pose-task.blade.php`.
- Réutilise la capture caméra + GPS + compression déjà présentes. Ne les
  réécris pas de zéro.
- Le regroupement par commune : `buildPayload()` groupe actuellement par
  DATE (overdue/today/tomorrow/week/later). Tu peux soit changer ce
  groupement pour la commune, soit ajouter un groupement commune en gardant
  le tri retard prioritaire. Décide en lisant le code, garde la perf (pas de
  N+1 ; `panel.commune` est déjà chargé en eager loading).
- Garde la sécurité existante (token, is_active, throttle).

### Définition de "terminé" pour la Partie B
- Avec 10 poses de 4 campagnes différentes : l'écran reste lisible et
  navigable d'une main, regroupé par zone, sans scroll infini de grosses
  cartes.
- Taper une pose ouvre directement la caméra. Photo → Envoyer → pose faite,
  feedback vert + vibration, la pose quitte la liste active.
- "Y aller" ouvre l'itinéraire GPS. "Problème" crée une alerte MP.
- Aucune régression sur l'upload/validation existant. Tous les chemins
  testés.

### Workflow
- Branche `feat/tech-ux-refonte`, commits atomiques, PR vers develop.
- Teste en viewport mobile étroit (~375px) ET sur un vrai téléphone via le
  lien WhatsApp avant de merger.

> Si une partie de la Partie A (géoloc) n'est pas finie, termine-la d'abord :
> l'UX "Y aller" et le regroupement par proximité dépendent des coordonnées
> des panneaux.
