# PHP for OZone's tests and tools: the extensions OZone requires (and the ones it suggests: the
# MySQL, PostgreSQL and Redis drivers, intl for the plural rules of translations), pcov for
# coverage, and Composer.
#
# The database *clients* are here for `oz db backup`, which shells out to mysqldump and pg_dump --
# `oz migrations rollback` takes a backup first, so without them it cannot roll back either.
#
# Built by docker/compose.yaml; use it through the Makefile (`make test`, `make shell`, ...).

ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-cli

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		git \
		unzip \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		libwebp-dev \
		libpq-dev \
		libicu-dev \
		default-mysql-client \
		postgresql-client \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
	&& docker-php-ext-install -j"$(nproc)" bcmath gd intl pcntl pdo_mysql pdo_pgsql \
	&& pecl install redis pcov \
	&& docker-php-ext-enable redis pcov \
	&& rm -rf /var/lib/apt/lists/* /tmp/pear

# Debian ships MariaDB's client, which verifies the server certificate by default; the MySQL 8.4
# container serves a self-signed one, so `oz db backup` (and the backup `oz migrations rollback`
# takes first) fails to connect. MySQL's own client does not verify by default, so this only
# realigns the client with what the framework's mysqldump call expects. Test containers on a private
# network: nothing here relaxes anything a deployment relies on.
RUN printf '[client]\nssl-verify-server-cert=0\n' > /etc/my.cnf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-ozone.ini

# The repository is mounted with the host user's ownership; the container runs as that user.
RUN git config --system --add safe.directory '*'

# The mount points of the named volumes, created here so Docker initializes them with these
# permissions: a volume mounted on a path the image does not have is created root-owned, and the
# container runs as the host user. Sticky and world-writable like /tmp itself, so any OZ_UID works.
RUN mkdir -p /tmp/_oz_tests_ /tmp/composer \
	&& chmod 1777 /tmp/_oz_tests_ /tmp/composer

ENV HOME=/tmp \
	COMPOSER_HOME=/tmp/composer

WORKDIR /ozone
