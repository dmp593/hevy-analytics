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
