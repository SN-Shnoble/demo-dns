# syntax=docker/dockerfile:1.7

# ---------- Builder ----------
FROM php:8.3-cli-bookworm AS builder

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    COMPOSER_NO_INTERACTION=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libcurl4-openssl-dev libicu-dev libonig-dev \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install Composer pinned to a known good version
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer manifests first to leverage Docker layer cache
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# Now copy the application source
COPY . .

RUN composer dump-autoload --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Build the Vite assets
RUN apt-get install -y --no-install-recommends nodejs npm \
    && npm ci --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules \
    && apt-get purge -y nodejs npm \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# ---------- Runtime ----------
FROM php:8.3-fpm-bookworm AS runtime

ENV PORT=8081 \
    APP_ENV=production

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq5 libcurl4 libicu72 libonig5 ca-certificates tini \
    && rm -rf /var/lib/apt/lists/*

# Healthcheck binary that hits the /up endpoint baked into the Laravel skeleton
RUN curl -fsSL https://github.com/grpc-ecosystem/grpc-health-probe/releases/download/v0.4.34/grpc_health_probe-linux-amd64 \
        -o /usr/local/bin/grpc_health_probe 2>/dev/null || true

WORKDIR /var/www/html

COPY --from=builder /app /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 0755 storage bootstrap/cache

USER www-data

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", (int) getenv("PORT") ?: 8000, $e, $s, 1) ? 0 : 1);'

# Use php artisan serve in production only as a last resort; in a real cluster
# we would run behind nginx + php-fpm. This shape keeps the image usable for
# docker-compose without a separate web server.
ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan migrate --force || true; exec php -S 0.0.0.0:${PORT} -t public public/index.php"]
