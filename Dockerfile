# syntax=docker/dockerfile:1

# ---------------------------------------------------------------- assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
COPY lang ./lang
# Tailwind scans Blade for class names; without the views the CSS is empty.
RUN npm run build

# ----------------------------------------------------------------- app ------
# FrankenPHP: production PHP server in one binary, officially supported by
# Laravel. One container runs the web server, the queue worker and the
# scheduler (docker/start.sh), because free hosting tiers give exactly one
# container.
#
# Composer runs INSIDE this stage rather than in the composer:2 image, so the
# dependency resolution sees the PHP version and extensions the app will
# actually run on — a mismatch there fails at build time instead of at 3am.
FROM dunglas/frankenphp:1-php8.4 AS app

RUN install-php-extensions pdo_pgsql intl zip gd opcache pcntl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# postgresql-client: for the one-shot host migration in start.sh. Moving
# between managed Postgres providers is a thing every deployment does once,
# and doing it with pg_dump beats hand-rolling a row copier that has to know
# about foreign key order, sequences and the migrations table.
RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# The base image setcaps cap_net_bind_service onto the frankenphp binary so it
# can bind ports 80/443 as non-root. Render (like several PaaS runtimes) runs
# containers with no-new-privileges, where exec()ing a binary that carries
# file capabilities fails outright — "Operation not permitted", exit 126.
# The capability is useless here anyway: the platform hands us an
# unprivileged $PORT. Strip it.
RUN (command -v setcap >/dev/null 2>&1 \
        || (apt-get update && apt-get install -y --no-install-recommends libcap2-bin && rm -rf /var/lib/apt/lists/*)) \
    && setcap -r /usr/local/bin/frankenphp || true

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

# Now the full source is present: generate the optimised autoloader and let
# Laravel's package discovery run.
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080
ENTRYPOINT ["start.sh"]
