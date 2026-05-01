# 🛠 Étapes manuelles — Système Mail Panora

Ce document liste les actions à faire **côté serveur de production** après le déploiement
du nouveau système mail. Tout le code est livré, il ne reste que la configuration.

## ✅ 1. Vider les caches Laravel après déploiement (OBLIGATOIRE)

Les caches `config`, `view`, `route` doivent être rafraîchis pour que les nouveaux
mails et templates soient pris en compte.

```bash
cd /chemin/vers/panora
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan event:clear
# Reconstruire les caches optimisés
php artisan config:cache
php artisan view:cache
php artisan route:cache
```

## ✅ 2. Vérifier la configuration mail (.env)

La config doit contenir :

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com         # ou ton SMTP serveur
MAIL_PORT=587                    # 587 (TLS) ou 465 (SSL)
MAIL_USERNAME=noreply@cible-ci.com
MAIL_PASSWORD=********           # mot de passe d'application Gmail si 2FA
MAIL_ENCRYPTION=tls              # tls ou ssl
MAIL_FROM_ADDRESS=noreply@cible-ci.com
MAIL_FROM_NAME="CIBLE CI"
```

⚠️ **IMPORTANT** : éviter les **doublons** de clés `MAIL_*` dans le .env.
Laravel ne lit que la **dernière** occurrence — si tu as `MAIL_MAILER=smtp` puis
plus loin `MAIL_MAILER=log`, c'est `log` qui s'applique. Bug courant.

Test depuis le serveur :
```bash
php artisan tinker --execute="Mail::raw('Test depuis Panora', fn(\$m) => \$m->to('toi@example.com')->subject('Test SMTP'));"
```

## ✅ 3. Configurer la queue (RECOMMANDÉ pour la perf)

Tous les mails de l'app implémentent `ShouldQueue` — ils sont prêts pour une
queue worker.

**Sans worker (par défaut)** : `QUEUE_CONNECTION=sync` → les mails partent immédiatement
mais bloquent l'utilisateur 1-3 sec à chaque action. Acceptable pour le MVP.

**Avec worker (recommandé prod)** :
```env
QUEUE_CONNECTION=database
```

Puis créer la table jobs :
```bash
php artisan queue:table
php artisan migrate
```

Démarrer un worker (sur Hetzner/Coolify, créer un service supervisé) :
```bash
php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
```

Sur **Coolify** : ajouter une commande dans le `start` de l'app :
```bash
php artisan queue:work --daemon &
php artisan serve --host=0.0.0.0 --port=8080
```

Plus propre : créer un **service séparé** dans Coolify type `worker` avec la
commande `php artisan queue:work`. Cf. doc Coolify > Services.

## ✅ 4. Tester le flux complet

Une fois en prod :

1. **Création utilisateur** : Admin → Utilisateurs → Nouveau → entrer email valide
   - Le user reçoit un email de bienvenue avec ses identifiants ✓
   - Flash : "Utilisateur créé. 📧 Email de bienvenue envoyé."

2. **Réactivation utilisateur** : Toggle d'activation sur un user inactif
   - Email "Compte réactivé" envoyé silencieusement ✓

3. **Envoi de proposition** : Réservation > Envoyer la proposition
   - Le client reçoit le mail proposition avec lien fonctionnel
   - Flash : "✅ Proposition envoyée à xxx@yyy.com"

4. **Acceptation par client** : Le client clique sur le lien > Confirmer
   - Le commercial créateur reçoit "✅ Proposition acceptée"
   - Si pas de commercial assigné → tous les admins reçoivent le mail

5. **Refus par client** : Le client clique sur le lien > Refuser (avec motif)
   - Le commercial reçoit "❌ Proposition refusée" avec le motif

## ✅ 5. Surveillance des logs

Tous les envois de mail sont tracés dans `storage/logs/laravel.log` avec :
- `mail.sent` (succès)
- `mail.failed` (échec + diagnostic)
- `mail.skipped.invalid_recipient` (destinataire invalide)
- `proposition.decision.no_recipient` (pas de destinataire pour notif)

Pour suivre en temps réel :
```bash
tail -f storage/logs/laravel.log | grep mail
```

## ✅ 6. Trouble-shooting

| Symptôme | Cause probable | Action |
|----------|----------------|--------|
| "Connection refused" | MAIL_HOST/MAIL_PORT incorrects ou pare-feu serveur | Vérifier `.env` + ouvrir le port 587 sortant |
| "Authentication failed" | Mauvais USERNAME/PASSWORD | Régénérer un mot de passe d'application Gmail |
| "TLS error" | Mauvais MAIL_ENCRYPTION | Mettre `tls` (port 587) ou `ssl` (port 465) |
| Mail non reçu mais pas d'erreur | Queue worker arrêté ou mail dans spam | `php artisan queue:listen` pour debug |
| "No hint path defined" | Cache view obsolète | `php artisan view:clear` |

## ✅ 7. Variables que tu dois ajouter / vérifier

À la racine de `.env` (production) :

```env
# Mail (obligatoire)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@cible-ci.com
MAIL_FROM_NAME="CIBLE CI"

# Queue (recommandé)
QUEUE_CONNECTION=database

# App URL (CRITIQUE pour les liens dans les mails)
APP_URL=https://app.cible-ci.com

# Logs (recommandé prod)
LOG_CHANNEL=daily
LOG_LEVEL=info
```

## ✅ 8. Cron Laravel (autres fonctionnalités)

Si pas déjà en place, ajouter au crontab du serveur :
```cron
* * * * * cd /chemin/panora && php artisan schedule:run >> /dev/null 2>&1
```

Ce cron pilote :
- `campaigns:activate-planned` (00h05) — bascule planifiée → active
- `campaigns:sync-expired` (01h30) — bascule active → terminée à expiration
- Autres tâches programmées éventuelles

## 📝 Récap des nouveautés mail

| Mail | Destinataire | Quand |
|------|--------------|-------|
| **PropositionMail** | Client | Admin envoie une proposition |
| **PropositionDecisionMail** | Commercial / admin | Client accepte/refuse une proposition |
| **UserWelcomeMail** | Utilisateur | Création / réactivation de compte admin |
| **ClientAccountMail** | Client | (Existant — création compte espace client) |

Tous utilisent le **layout commun** `resources/views/components/mail/layout.blade.php`
pour une cohérence visuelle (header CIBLE CI, footer, responsive).

Tous passent par **`NotificationMailer`** qui :
- Wrap try/catch (un mail KO ne casse JAMAIS le flux métier)
- Diagnostic SMTP humain pour l'admin
- Log uniforme (channel `mail`)
- Compatible queue (si worker actif)
