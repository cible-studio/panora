#!/bin/bash
# ═══════════════════════════════════════════
# Script de déploiement CIBLE CI
# Exécuté automatiquement par Coolify
# à chaque push sur la branche main
# ═══════════════════════════════════════════

set -e  # Arrêter si une commande échoue

echo "🚀 Début du déploiement — $(date)"

# ── 1. Dépendances PHP ─────────────────────
echo "📦 Installation des dépendances..."
composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-dev

# ── 2. Migrations base de données ──────────
echo "🗄️  Migrations..."
php artisan migrate --force

# ── 3. Nettoyage des anciens caches ────────
echo "🧹 Nettoyage caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# ── 4. Génération des nouveaux caches ──────
echo "⚡ Génération des caches production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── 5. Storage ─────────────────────────────
echo "📁 Lien storage..."
php artisan storage:link 2>/dev/null || true

# ── 6. Permissions ─────────────────────────
echo "🔒 Permissions..."
chmod -R 775 storage bootstrap/cache

# ── 7. Redémarrer les queues ───────────────
echo "⚙️  Redémarrage queues..."
php artisan queue:restart

echo "✅ Déploiement terminé — $(date)"