# syntax=docker/dockerfile:1

# The worker servers `make test-runtimes` runs OZone under -- FrankenPHP in worker mode, RoadRunner
# and Swoole -- each with the PHP extensions OZone requires. They serve tests/Support/Servers/ from
# the repository mounted at /ozone; tests/Runtime/Servers/ talks to them over HTTP.
#
# Built by docker/compose.yaml (profile `runtimes`), one target per server. php.ini is copied last in
# each target, so editing it never rebuilds the extensions (Swoole compiles from source).

ARG PHP_VERSION=8.5

FROM dunglas/frankenphp:1-php${PHP_VERSION} AS frankenphp

RUN install-php-extensions bcmath gd

COPY php.ini /usr/local/etc/php/conf.d/zz-ozone.ini

# php-cli images, which have to be given the extension installer the FrankenPHP image ships with.
# It comes from the installer's own image, pinned to a version as `rr` and `composer` are below, and
# no longer from a release's `latest/download`: that URL is whatever was published that day, so no
# two builds were alike, and it answered 503 and 504 while GitHub was unwell, which failed a test
# tier that has nothing to do with GitHub.
FROM php:${PHP_VERSION}-cli AS worker

COPY --from=mlocati/php-extension-installer:2.13 /usr/bin/install-php-extensions /usr/local/bin/

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
