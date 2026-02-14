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
    && sed -i '/^MAIL_/d' .env \
    && echo 'MAIL_MAILER=smtp' >> .env \
    && echo 'MAIL_HOST=mailhog' >> .env \
    && echo 'MAIL_PORT=1025' >> .env \
    && echo 'MAIL_USERNAME=null' >> .env \
    && echo 'MAIL_PASSWORD=null' >> .env \
    && echo 'MAIL_ENCRYPTION=null' >> .env \
    && echo 'MAIL_FROM_ADDRESS=noreply@rms.local' >> .env \
    && echo "MAIL_FROM_NAME=RMS" >> .env \
    && php artisan key:generate --no-interaction \
    && touch database/database.sqlite \
    && php artisan migrate --force --no-interaction \
    && php artisan config:clear 2>/dev/null || true

EXPOSE 3000

# Mailhog defaults so mail works in Docker even if compose env isn't visible to the PHP process.
ENV MAIL_MAILER=smtp
ENV MAIL_HOST=mailhog
ENV MAIL_PORT=1025
ENV MAIL_USERNAME=
ENV MAIL_PASSWORD=
ENV MAIL_ENCRYPTION=
ENV MAIL_FROM_ADDRESS=noreply@rms.local
ENV MAIL_FROM_NAME=RMS

# Clear config on start so runtime env (e.g. MAIL_* from docker-compose) is used, not cached build-time config.
CMD sh -c "php artisan config:clear && exec php artisan serve --host=0.0.0.0 --port=3000"
