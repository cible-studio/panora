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

## ✅ 3. Queue mail — IMPORTANT À LIRE

⚠️ **Comportement par défaut sécurisé** : `NotificationMailer::send()` utilise
maintenant `sendNow()` en interne, ce qui force l'envoi **synchrone** quel
que soit ton `QUEUE_CONNECTION`. Conséquences :

- ✅ Le mail part immédiatement → pas de faux positif "envoyé" alors qu'il
  dort dans la table `jobs`
- ⚠️ La requête HTTP de l'admin attend le SMTP (généralement < 1 sec, rarement
  problématique sur Gmail/Resend/Mailgun)

### Si tu veux la queue async pour la perf (RECOMMANDÉ à terme)

1. Créer la table jobs (si pas déjà fait) :
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

2. Garder ou définir :
   ```env
   QUEUE_CONNECTION=database
   ```

3. Démarrer un worker en daemon :
   ```bash
   php artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
   ```

4. Sur **Coolify** : créer un **service séparé** type `worker` (image identique
   à l'app) avec comme commande de démarrage :
   ```bash
   php artisan queue:work --queue=default --tries=3 --backoff=10
   ```

5. Une fois le worker en place, dans le code, remplacer `send()` par
   `dispatchAsync()` aux endroits non-critiques :
   ```php
   // au lieu de :
   $mailer->send(...);
   // utiliser :
   $mailer->dispatchAsync(...);
   ```

   Les notifications décision proposition / réactivation user sont déjà en
   `sendSilently` (sync) — tu peux les passer en `dispatchAsync` quand le
   worker tourne.

### Comment savoir si un worker tourne ?

```bash
ps aux | grep "queue:work"
# OU sur Coolify : voir le statut du service worker
```

S'il n'y a pas de processus → reste sur `send()` / `sendSilently()` (sync).

## ✅ 3.bis Anti-spam — IMPORTANT

Si tes mails arrivent en **spam**, c'est presque toujours dû à 3 choses :
authentification de domaine, contenu, et IP d'envoi. Voici l'ordre de priorité.

### A. Authentifier le domaine `cible-ci.com` (le plus impactant)

Sans ces 3 enregistrements DNS, Gmail/Outlook classifient en spam quasi
systématiquement les mails envoyés via Gmail SMTP au nom de `@cible-ci.com`.

#### 1. SPF (TXT)

Sur Cloudflare DNS, ajouter un TXT à la racine `cible-ci.com` :

```
Type:  TXT
Name:  cible-ci.com  (ou @)
Value: v=spf1 include:_spf.google.com ~all
```

Si tu envoies aussi via d'autres serveurs (Hetzner direct, Mailgun…), inclure
chacun :
```
v=spf1 include:_spf.google.com ip4:<IP_HETZNER> ~all
```

#### 2. DKIM (TXT) — Gmail signe les mails

Dans **Google Workspace Admin** (admin.google.com) :
- Apps → Google Workspace → Gmail → Authenticate email
- Generate new record (sélectionner `cible-ci.com`)
- Copier la valeur DKIM générée → ajouter dans Cloudflare :
  ```
  Type:  TXT
  Name:  google._domainkey
  Value: (la longue chaîne v=DKIM1; k=rsa; p=...)
  ```
- Patienter 24-48h puis cliquer "Start Authentication" dans Workspace

#### 3. DMARC (TXT)

Une fois SPF et DKIM en place, ajouter DMARC :
```
Type:  TXT
Name:  _dmarc
Value: v=DMARC1; p=quarantine; rua=mailto:postmaster@cible-ci.com; pct=100
```

Pour vérifier que tout est bien configuré : https://www.mail-tester.com/
(envoyer un mail à l'adresse fournie, score 10/10 attendu).

### B. Bonnes pratiques contenu (déjà appliquées dans le code)

| Règle | Implémentation |
|-------|----------------|
| Pas d'emoji dans le subject | ✅ "Proposition commerciale CIBLE CI - Réf. R-001" au lieu de "📋 ✅" |
| Version texte/plain en + du HTML | ✅ `text: 'emails.plain.xxx'` dans chaque Mailable |
| Préheader court et descriptif | ✅ Slot `$preheader` dans le layout |
| Logo embarqué (data-URI base64) | ✅ Pas d'image externe à charger |
| Lien "From" qui matche le domaine | ✅ `MAIL_FROM_ADDRESS=noreply@cible-ci.com` |
| Pas de `!`, MAJUSCULES, "FREE", etc. | ✅ Texte sobre |
| Light theme propre | ✅ Fini les fonds sombres "spammy" |
| Ratio texte/HTML équilibré | ✅ |

### C. IP d'envoi

Tu utilises **Gmail SMTP** (`smtp.gmail.com`). C'est OK pour des volumes faibles
(< 500 mails/jour) si SPF/DKIM/DMARC sont en place. Au-delà :
- Migrer vers un service transactionnel : **Resend**, **Postmark**, **Mailgun**
- Avantages : meilleure réputation IP, dashboard, webhooks bounce/spam, 99 %+ delivrabilité
- Tarif : Resend gratuit jusqu'à 3 000 mails/mois — largement suffisant pour démarrer

Pour Resend (recommandé) :
```env
MAIL_MAILER=resend
RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS=noreply@cible-ci.com
MAIL_FROM_NAME="CIBLE CI"
```
+ `composer require resend/resend-laravel`

### D. Première étape concrète à faire MAINTENANT

1. Aller sur https://www.mail-tester.com/
2. Copier l'adresse de test fournie
3. Depuis Panora, créer un user avec cette adresse → mail welcome envoyé
4. Cliquer "Then check your score" sur mail-tester
5. Score :
   - **9-10/10** : tout est OK, juste arrivé en spam pour autre raison
   - **7-8/10** : SPF ou DKIM manquant
   - **< 7/10** : SPF + DKIM + DMARC tous manquants

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

## ✅ 7.bis Module WhatsApp — notifications techniciens

Le module Pose OOH envoie maintenant un WhatsApp au technicien à chaque
nouvelle assignation (et à la réassignation). Configuration :

### A. Renseigner le WhatsApp des techniciens

Dans la fiche utilisateur (admin → Utilisateurs → modifier), saisir le numéro
au format CI :
- `0707070707` (sera auto-converti en `+225 07 07 07 07 07`)
- ou directement `+2250707070707`

### B. Choisir un provider (.env)

```env
# Activer / désactiver globalement
WHATSAPP_ENABLED=true

# "callmebot" (gratuit, MVP) ou "twilio" (prod payant)
WHATSAPP_PROVIDER=callmebot
```

#### Option 1 — CallMeBot (gratuit, démarrage rapide)

1. Sur ton téléphone, ajoute le numéro CallMeBot (`+34 644 51 95 23`) en contact
2. Envoie un message WhatsApp à ce numéro :
   `I allow callmebot to send me messages`
3. Tu recevras une clé API en réponse
4. Mets-la dans `.env` :
   ```env
   CALLMEBOT_API_KEY=123456
   ```

⚠️ **Limite CallMeBot** : la clé API est liée à un seul numéro destinataire.
   Pour envoyer à plusieurs techniciens, il faut que **chacun** suive la
   procédure (contacter CallMeBot avec son propre téléphone). Pratique pour
   tester et démarrer, mais peu scalable.

#### Option 2 — Twilio (recommandé production)

1. Créer un compte sur https://www.twilio.com (sandbox WhatsApp gratuit pour test)
2. Activer "Twilio Sandbox for WhatsApp" dans la console
3. Récupérer SID, Auth Token, et le numéro From
4. Mettre dans `.env` :
   ```env
   WHATSAPP_PROVIDER=twilio
   TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_AUTH_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   TWILIO_WHATSAPP_FROM=14155238886
   ```
5. Pour les vrais clients (hors sandbox), il faut faire approuver des
   "templates" WhatsApp Business par Twilio (procédure 24-48h)

### C. Tester l'envoi

Depuis le terminal :
```bash
php artisan tinker
> app(\App\Services\WhatsAppService::class)->send('07XXXXXXXX', 'Test depuis CIBLE CI');
```

Si tout est OK, le technicien reçoit le message ; sinon `storage/logs/laravel.log`
contient le diagnostic (`whatsapp.failed`, `whatsapp.callmebot.no_api_key`...).

### D. URL publique technicien

Chaque tâche reçoit un `public_token` (32 chars). Le technicien clique sur le
lien WhatsApp et arrive sur :
```
https://app.cible-ci.com/pose/<token>
```
Page mobile-friendly avec :
- Slider 0-100 % + presets rapides 0/25/50/75/100
- Note libre optionnelle
- Bouton "Mettre à jour"

À l'atteinte de 100 %, la tâche est automatiquement marquée comme **réalisée**
(`status = realisee`, `done_at` rempli, `real_minutes` calculé depuis `started_at`).

### E. Polling temps réel côté admin

La vue admin → Gestion Pose OOH rafraîchit automatiquement les barres de
progression toutes les **30 secondes** (sans rechargement de page). C'est
visible dans la colonne "Statut" : pill + barre de progression colorée.

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
