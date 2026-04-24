FROM php:8.3-fpm-alpine

# Extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql opcache && \
    apk add --no-cache \
        nodejs npm \
        nginx \
        git \
        unzip \
        curl && \
    pecl install redis && \
    docker-php-ext-enable redis

# PHP.ini production avec limites upload
RUN echo "upload_max_filesize=35M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size=350M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/uploads.ini

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copier les fichiers
COPY . .

# Installer les dépendances
RUN composer install --optimize-autoloader --no-dev --no-interaction

# NPM build
RUN npm ci && npm run build

# Permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD php artisan migrate --force && \
    php artisan storage:link && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php -S 0.0.0.0:8000 -t public