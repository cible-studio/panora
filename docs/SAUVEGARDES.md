# Sauvegardes de Panora

> Mis en place le 2026-10-02 avec `spatie/laravel-backup`.
> Configuration : `config/backup.php` · Planification : `routes/console.php` (§ 15).

## 1. Ce qui est sauvegardé

| Élément | Où | Sauvegarde |
|---|---|---|
| Code | GitHub (develop + main) | Déjà assuré par git |
| **Base de données** (clients, factures, paiements, campagnes, réservations…) | MySQL | Export complet chaque nuit |
| **Fichiers déposés** (photos de panneaux, piges, PDF de diffusion…) | `storage/app` | Copiés chaque nuit dans la même archive |
| Variables Coolify (clés API, jetons, mots de passe) | Coolify | **Non sauvegardées** : à garder dans un gestionnaire de mots de passe |

Chaque sauvegarde est **une archive `.zip`** contenant :
- `db-dumps/mysql-<base>.sql` : la base complète ;
- les fichiers de `storage/app`.

L'export de la base se fait **sans verrouiller les tables** (`--single-transaction`) : l'application reste utilisable pendant la sauvegarde.

## 2. Quand

| Heure | Tâche |
|---|---|
| 01:30 | Suppression des copies trop anciennes |
| **02:00** | Sauvegarde (heure creuse, loin de la diffusion de 9h-10h) |
| 08:30 | Contrôle : alerte si la dernière copie a plus d'un jour |

**Copies conservées :** toutes celles des 7 derniers jours, puis 1 par jour pendant 7 jours de plus, 1 par semaine sur 4 semaines, 1 par mois sur 6 mois et 1 par an sur 2 ans. Les plus anciennes sont supprimées au-delà de `BACKUP_MAX_MO` (20 Go par défaut).

**Alertes :** un mail est envoyé **seulement en cas de problème** (échec, copie trop vieille, nettoyage raté) aux adresses de `BACKUP_NOTIFICATION_EMAIL`.

## 3. Activer, dans l'ordre

Tant que `BACKUP_ENABLED=false`, **aucune tâche ne tourne**.

1. **Production (Coolify → variables) :**
   ```dotenv
   BACKUP_ENABLED=true
   BACKUP_NOTIFICATION_EMAIL=studio@cible-ci.com,commercial@cible-ci.com
   BACKUP_ARCHIVE_PASSWORD=<long mot de passe, gardé dans un coffre>
   ```
   Puis **Redeploy**. L'image installe `mariadb-client`, qui fournit `mysqldump`.
2. **Premier essai manuel** (terminal Coolify du conteneur) :
   ```bash
   php artisan backup:run --only-db   # base seule, rapide
   php artisan backup:run             # base + fichiers
   php artisan backup:list            # doit lister la copie
   ```
3. **Copie hors serveur (indispensable)** : une copie rangée sur le même serveur disparaît avec lui.
   - Créer un espace de stockage compatible S3 (Hetzner Object Storage, Backblaze B2, Cloudflare R2…) et une clé d'accès limitée à ce bucket.
   - Renseigner :
     ```dotenv
     BACKUP_DISKS=backups,backups-s3
     BACKUP_S3_KEY=...
     BACKUP_S3_SECRET=...
     BACKUP_S3_BUCKET=panora-sauvegardes
     BACKUP_S3_ENDPOINT=https://...      # fourni par l'hébergeur
     BACKUP_S3_REGION=auto               # ou la région indiquée
     ```
   - Redeploy, puis `php artisan backup:run --only-db` et vérifier que le fichier apparaît dans le bucket.
   - Si le stockage externe est indisponible, la copie locale est quand même gardée et un mail d'alerte part.

**Staging :** laisser `BACKUP_ENABLED=false`. Ses données sont des données de test.

## 4. Restaurer

Toujours **tester sur le staging ou une base temporaire**, jamais directement sur la prod.

1. Récupérer l'archive voulue (`storage/app/backups/panora/` ou le bucket S3).
2. Ouvrir le zip, avec le mot de passe `BACKUP_ARCHIVE_PASSWORD` s'il est défini.
3. **Base :**
   ```bash
   mysql -u <user> -p <base_cible> < db-dumps/mysql-<base>.sql
   ```
4. **Fichiers :** recopier le contenu de l'archive dans `storage/app/` (les chemins y sont relatifs).

**Test de restauration** à faire une fois par trimestre.

Test réalisé le 2026-10-02 en local : sauvegarde en 11 s (102 Mo). La base a été restaurée dans une base temporaire : **60 tables sur 60, toutes les lignes identiques**, contenu des factures identique.

## 5. Dépannage

| Symptôme | Cause probable |
|---|---|
| `mysqldump: not found` | Image construite sans `mariadb-client` : redéployer |
| Alerte « backup is unhealthy » | La sauvegarde de 2h n'a pas tourné : vérifier la tâche planifiée `schedule:run` dans Coolify |
| Échec vers `backups-s3` | Clé, bucket ou endpoint S3 incorrect ; la copie locale existe quand même |
| En local (Windows) | `DB_DUMP_BINARY_PATH=C:/wamp64/bin/mysql/mysql9.1.0/bin/` |
