# syntax=docker/dockerfile:1

# The worker servers `make test-runtimes` runs OZone under -- FrankenPHP in worker mode, RoadRunner
# and Swoole -- each with the PHP extensions OZone requires. They serve tests/Support/Servers/ from
# the repository mounted at /ozone; tests/Runtime/Servers/ talks to them over HTTP.
#
# Built by docker/compose.yaml (profile `runtimes`), one target per server. php.ini is copied last in
# each target, so editing it never rebuilds the extensions (Swoole compiles from source).

ARG PHP_VERSION=8.4

FROM dunglas/frankenphp:1-php${PHP_VERSION} AS frankenphp

RUN install-php-extensions bcmath gd

COPY php.ini /usr/local/etc/php/conf.d/zz-ozone.ini

# php-cli images, with the extension installer the FrankenPHP image already ships.
FROM php:${PHP_VERSION}-cli AS worker

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

FROM worker AS roadrunner

# ext-sockets: spiral/goridge, RoadRunner's PHP relay. ext-zip: composer, to unpack the packages.
RUN install-php-extensions bcmath gd sockets zip

COPY --from=ghcr.io/roadrunner-server/roadrunner:2025.1 /usr/bin/rr /usr/local/bin/rr
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# spiral/roadrunner-http is a `suggest` of OZone, not a dependency, so it is installed here apart
# from the repository's vendor/; the worker script loads both.
COPY roadrunner/composer.json /opt/roadrunner/composer.json

RUN composer install --working-dir=/opt/roadrunner --no-interaction --no-progress \
	&& rm -rf /root/.composer

COPY php.ini /usr/local/etc/php/conf.d/zz-ozone.ini

FROM worker AS swoole

RUN install-php-extensions bcmath gd swoole

COPY php.ini /usr/local/etc/php/conf.d/zz-ozone.ini
