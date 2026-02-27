FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    libzip-dev \
    zip \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_sqlite gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

RUN cp .env.example .env 2>/dev/null || true \
    && sed -i '/^MAIL_/d' .env \
    && echo 'DB_DATABASE=/app/storage/db/database.sqlite' >> .env \
    && echo 'MAIL_MAILER=smtp' >> .env \
    && echo 'MAIL_HOST=mailhog' >> .env \
    && echo 'MAIL_PORT=1025' >> .env \
    && echo 'MAIL_USERNAME=null' >> .env \
    && echo 'MAIL_PASSWORD=null' >> .env \
    && echo 'MAIL_ENCRYPTION=null' >> .env \
    && echo 'MAIL_FROM_ADDRESS=noreply@rms.local' >> .env \
    && echo "MAIL_FROM_NAME=RMS" >> .env \
    && php artisan key:generate --no-interaction \
    && mkdir -p storage/db \
    && touch storage/db/database.sqlite \
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

# On start: force Mailhog into .env when ./api is mounted (host .env has MAIL_MAILER=log), then migrate and serve.
CMD sh -c "sed -i '/^MAIL_/d' .env 2>/dev/null; echo 'MAIL_MAILER=smtp' >> .env; echo 'MAIL_HOST=mailhog' >> .env; echo 'MAIL_PORT=1025' >> .env; echo 'MAIL_USERNAME=null' >> .env; echo 'MAIL_PASSWORD=null' >> .env; echo 'MAIL_ENCRYPTION=null' >> .env; echo 'MAIL_FROM_ADDRESS=noreply@rms.local' >> .env; echo 'MAIL_FROM_NAME=RMS' >> .env; php artisan config:clear && mkdir -p /app/storage/db && touch /app/storage/db/database.sqlite && php artisan migrate --force --no-interaction && exec php artisan serve --host=0.0.0.0 --port=3000"
