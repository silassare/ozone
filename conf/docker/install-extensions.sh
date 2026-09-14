#!/bin/sh
# Installs the PHP extensions OZone needs, in the official PHP images (Debian based).
#
# Usage: install-extensions <with_redis: 0|1>
set -eu

WITH_REDIS="${1:-0}"

apt-get update
apt-get install -y --no-install-recommends \
	libfreetype6-dev \
	libjpeg62-turbo-dev \
	libpng-dev \
	libwebp-dev \
	libpq-dev

docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
docker-php-ext-install -j"$(nproc)" bcmath gd opcache pdo_mysql pdo_pgsql

if [ "${WITH_REDIS}" = "1" ]; then
	pecl install redis
	docker-php-ext-enable redis
fi

rm -rf /var/lib/apt/lists/* /tmp/pear
