# Production image: FrankenPHP (Caddy + PHP in one process) serving the Laravel app.

# --- Stage 1: install PHP dependencies (no dev packages) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# --- Stage 2: runtime ---
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_pgsql opcache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

WORKDIR /app
COPY --from=vendor /app /app

RUN chmod +x docker/entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# The host tells us which port to listen on through $PORT (Render uses 10000).
EXPOSE 10000

CMD ["docker/entrypoint.sh"]
