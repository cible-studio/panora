FROM php:8.3-fpm-alpine

# ─────────────────────────────────────────────────────────────────────
# Extensions PHP via mlocati/docker-php-extension-installer.
#
# Pourquoi ce changement (2026-07-31) : le build précédent compilait
# PDO + PDO_MYSQL + OPcache+JIT + GD + ZIP + PECL redis avec gcc/g++
# dans un seul RUN monolithique. Peak RAM ~1.5 Go → OOM killer du
# kernel sur le container de build Coolify, exit 255 pile au démarrage
# du compile de ext/zip (cf. log deployment-ctu0e72r4si6j5ps3kiorsk9,
# étape #8 105.4). install-php-extensions installe des binaires
# précompilés → 15 s au lieu de 2 min, peak RAM ~200 Mo au lieu de
# 1.5 Go, plus de gcc/g++ résiduels dans l'image finale.
# ─────────────────────────────────────────────────────────────────────
RUN apk add --no-cache nodejs npm nginx git unzip curl zip && \
    curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo_mysql opcache gd zip xml redis


RUN echo "upload_max_filesize=35M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size=350M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-dev --no-interaction
RUN npm ci && npm run build
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && \
    php artisan storage:link && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php -S 0.0.0.0:8000 -t public"]
