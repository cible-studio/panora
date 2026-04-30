FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        nodejs npm nginx git unzip curl \
        autoconf gcc g++ make linux-headers \
        freetype-dev libjpeg-turbo-dev libpng-dev \
        libzip-dev zip && \
    docker-php-ext-configure gd \
        --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql opcache gd zip && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del autoconf gcc g++ make linux-headers && \
    rm -rf /tmp/pear

# (optionnel mais recommandé pour Excel / PDF / etc.)
RUN docker-php-ext-install xml

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