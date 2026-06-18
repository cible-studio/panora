# SM1 — Déploiement staging + tests utilisateur 2-3 jours

**Branche** : `feature/tech-refonte-sm1`
**Dernier commit** : `22ad5b3` (cleanup SM1, 2026-06-18)
**Étape de la procédure de merge** : 1/4 (tests utilisateur réel)

---

## 🚀 Déploiement staging

```bash
# Sur le serveur staging
cd /chemin/vers/panora-staging
git fetch origin feature/tech-refonte-sm1
git checkout feature/tech-refonte-sm1
git pull origin feature/tech-refonte-sm1

# Nettoyage cache (obligatoire — sinon les vues compilées
# pré-refonte restent en cache et neutralisent les partials)
php artisan view:clear
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

**Pas de migrations** à exécuter (SM1 est purement frontend).

---

## ✅ Sanity check post-déploiement (~5 min)

À effectuer immédiatement après le déploiement :

- [ ] Ouvrir `https://staging.panora-cible.com/tech/{token}/poses`
      d'un tech avec ≥ 1 pose active. Vérifier le rendu visuel.
- [ ] Console DevTools (F12) : aucune erreur rouge.
- [ ] DevTools Network → filtrer `heartbeat` → vérifier que la
      requête est faite toutes les 20 s.
- [ ] DevTools Application → Service Workers → vérifier
      `/tech-sw.js` enregistré + activé.
- [ ] Ouvrir `view-source:` de la page → confirmer la présence des
      tags suivants juste avant le 1er `<script>` inline :
      ```html
      <script> window.TECH_CONFIG = { ... } </script>
      <script type="module" src="/js/tech/tech-app.js?v=..."></script>
      ```
- [ ] DevTools Network → filtrer `tech-app` → vérifier que
      `tech-app.js` charge (200) ET ses 5 imports (api.js, state.js,
      offline.js, sw-register.js, heartbeat.js, pwa-install.js).

Si l'un de ces points échoue → **stop, ne pas mettre les techs CIBLE
dessus**. Investiguer (probablement un cache view qui n'a pas été
purgé, ou un blocage CDN/firewall sur les paths `/js/tech/`).

---

## 👥 Brief tech CIBLE (à copier-coller en WhatsApp)

> Salut [Prénom tech],
>
> On a fait des changements techniques sur l'espace technicien
> (https://staging.panora-cible.com/tech/{ton token}/poses).
>
> On a TOUT essayé pour que rien ne change pour toi. La question
> qu'on te pose, c'est simple :
>
> Pendant 2 jours, utilise ton lien comme d'habitude — prends tes
> photos, signale tes soucis, vois ta carte. Si tu remarques quoi
> que ce soit de différent (un truc qui s'affiche bizarrement, un
> bouton qui marche pas, ton téléphone qui rame plus, un message
> d'erreur…), envoie-nous un message dès que tu vois.
>
> Si tu remarques rien du tout pendant 2 jours, parfait — réponds
> juste "rien à signaler".
>
> Merci !

**Bonne réponse attendue** : "Rien à signaler" (= 100 % de succès).

**Réponses suspectes à investiguer** :
- "Ça rame plus qu'avant" → vérifier double-binding (heartbeat
  doublé ? listener online/offline doublé ?)
- "Les photos partent plus" → vérifier que `flushUploadQueue`
  (inline) est toujours appelé après upload réussi
- "Mon écran clignote" → vérifier que les KPIs ne se mettent pas
  à jour trop souvent (heartbeat n'est appelé qu'au visibilitychange
  + intervalle 20s, pas plus)
- "Tu as changé mon historique" → impossible côté SM1 (pas de DB
  touchée), mais à vérifier malgré tout

---

## 📊 Checklist d'observation côté admin (1-2x par jour)

### Logs Laravel

```bash
# Sur le serveur staging
tail -n 200 storage/logs/laravel.log | grep -iE "tech|pose|pige|heartbeat"
```

Surveiller : `ERROR`, `Exception`, `TypeError`.

### Métriques heartbeat

```bash
# Compter les hits /heartbeat sur les dernières 24h
grep "tech/.*/heartbeat" storage/logs/laravel.log | wc -l
```

Référence : 1 tech actif 8h/j → ~1440 hits/jour (8 h × 60 min / 0,33 min).
Si beaucoup plus → soupçon de polling doublé (bug à traquer).

### Vérification audit

```bash
# Pige récente créée par un tech sur staging
php artisan tinker
>>> \App\Models\Pige::latest()->take(5)->get(['id','panel_id','user_id','status','taken_at','geo_check'])
```

Confirmer que les nouveaux uploads pendant la phase de test ont :
- `status: 'en_attente'` (workflow inchangé)
- `geo_check` non null (la géoloc EXIF marche encore)
- `user_id` correspondant au tech via tech_public_token

---

## 🔙 Procédure de rollback (au cas où)

Si feedback négatif ou anomalie détectée pendant les 2 jours :

```bash
cd /chemin/vers/panora-staging
git checkout main   # ou la branche stable précédente
php artisan view:clear
php artisan cache:clear
```

Pas de rollback DB nécessaire (SM1 n'a pas touché à la DB).

Sur le repo local pour comprendre la régression :

```bash
git checkout feature/tech-refonte-sm1
git log --oneline -10
# Identifier le commit suspect
git revert <hash>
# OU bisect si pas clair :
git bisect start feature/tech-refonte-sm1 main
```

---

## ✅ Critères de validation pour passer à l'étape 2 (merge develop)

- [ ] 2 jours d'utilisation par 1-2 techs sans feedback négatif
- [ ] Logs Laravel propres (aucune ERROR liée au module tech)
- [ ] Aucune création de pige bloquée
- [ ] Heartbeat actif et conforme au volume attendu (~180/h/tech)
- [ ] Service Worker enregistré et opérationnel offline

Si tous ces critères sont remplis → procéder à l'étape 2 :

```bash
git checkout develop
git merge feature/tech-refonte-sm1 --no-ff
git push origin develop
```

---

## 📚 Référence pour la SM1.5 (à programmer plus tard)

Voir `docs/TECHNICAL_DEBT.md` section "Refonte Espace Technicien — SM1.5
à programmer". 6 modules JS encore en inline à extraire (~1160 lignes,
8-12h focus). Aucune urgence — peut attendre la fin de la SM2 (refonte
UI) selon la recommandation finale de l'utilisateur.
