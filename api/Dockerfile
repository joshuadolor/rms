FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    libzip-dev \
    zip \
    sqlite \
    sqlite-dev \
    && docker-php-ext-install -j$(nproc) pdo_sqlite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

RUN cp .env.example .env 2>/dev/null || true \
    && php artisan key:generate --no-interaction \
    && touch database/database.sqlite \
    && php artisan migrate --force --no-interaction

EXPOSE 3000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=3000"]
