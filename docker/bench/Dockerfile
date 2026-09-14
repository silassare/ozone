# syntax=docker/dockerfile:1

# `make benchmark-http`: OZone, Laravel and Symfony served the same way -- FrankenPHP, PHP 8.4,
# OPcache on, production settings -- once per request (classic, like PHP-FPM) and in worker mode
# (OZone's FrankenPhpBridge, Laravel Octane, symfony/runtime's own FrankenPHP support), for wrk to
# measure.
#
# The Laravel and Symfony applications are built here, from their official skeletons, with one route
# answering "pong", one answering {"hello":"world"} and one reading a row by its primary key through
# the framework's ORM (Eloquent, Doctrine), from SQLite; OZone serves the repository mounted at /ozone
# (tests/Support/Servers/, docker/bench/ozone/seed.php). Built by docker/bench/compose.yaml.

ARG PHP_VERSION=8.4

FROM dunglas/frankenphp:1-php${PHP_VERSION} AS base

RUN install-php-extensions bcmath gd zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-bench.ini

ENV COMPOSER_ALLOW_SUPERUSER=1

# The image ships a demo site in /app; the framework targets replace it.
FROM base AS ozone

# /ozone is filled at start with a copy of the repository (compose.yaml): served from the container's
# own filesystem, as the other applications are, never through a bind mount. It points into /tmp so
# the container user, whoever it is, creates the directory it extracts into.
RUN ln -s /tmp/ozone /ozone

FROM base AS laravel

RUN rm -rf /app \
	&& composer create-project --no-interaction --prefer-dist laravel/laravel /app "^12" \
	&& cd /app \
	&& composer require --no-interaction laravel/octane \
	&& (php artisan octane:install --server=frankenphp --no-interaction || true) \
	&& { [ -f public/frankenphp-worker.php ] || cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/; }

COPY apps/laravel/api.php /app/routes/api.php
COPY apps/laravel/Country.php /app/app/Models/Country.php
COPY apps/laravel/create_countries_table.php /app/database/migrations/2026_09_13_000000_create_countries_table.php

RUN cd /app \
	&& sed -i "s#web: __DIR__.'/../routes/web.php',#web: __DIR__.'/../routes/web.php',\n        api: __DIR__.'/../routes/api.php',#" bootstrap/app.php \
	&& grep -q "routes/api.php" bootstrap/app.php \
	&& sed -i -e 's/^APP_ENV=.*/APP_ENV=production/' -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' -e 's/^LOG_LEVEL=.*/LOG_LEVEL=error/' .env \
	&& composer install --no-dev --no-interaction --optimize-autoloader \
	&& php artisan migrate --force \
	&& php artisan optimize \
	&& chmod -R a+rwX storage bootstrap/cache database

COPY caddy/laravel-classic.Caddyfile caddy/laravel-octane.Caddyfile /etc/bench/

FROM base AS symfony

RUN rm -rf /app \
	&& composer create-project --no-interaction --prefer-dist symfony/skeleton /app "7.4.*" \
	&& cd /app \
	&& composer require --no-interaction symfony/orm-pack \
	&& sed -i 's#^DATABASE_URL=.*#DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"#' .env \
	&& grep -q '^DATABASE_URL="sqlite' .env

COPY apps/symfony/BenchController.php apps/symfony/BenchDbController.php /app/src/Controller/
COPY apps/symfony/Country.php /app/src/Entity/Country.php

RUN cd /app \
	&& composer dump-env prod \
	&& composer install --no-dev --no-interaction --optimize-autoloader \
	&& APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup \
	&& APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:schema:create --no-interaction \
	&& APP_ENV=prod APP_DEBUG=0 php bin/console dbal:run-sql "INSERT INTO countries (cc2, calling_code, name, name_real, data, is_valid, created_at, updated_at, deleted, deleted_at) VALUES ('BJ', '+229', 'Benin', 'Benin', '{}', 1, 0, 0, 0, NULL)" \
	&& chmod -R a+rwX var

COPY caddy/symfony-classic.Caddyfile caddy/symfony-worker.Caddyfile /etc/bench/

FROM alpine:3 AS wrk

RUN apk add --no-cache wrk
