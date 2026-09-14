# Sample images of an OZone project. Copy conf/docker/ to the project root as docker/ (see
# docker/README.md) and build from the project root:
#
#   docker build -f docker/Dockerfile --target app    .   # PHP-FPM with the project
#   docker build -f docker/Dockerfile --target web    .   # nginx, in front of `app`
#   docker build -f docker/Dockerfile --target apache .   # or Apache with mod_php, alone
#
# Build argument WITH_REDIS=1 adds ext-redis (Redis job store and cache).

ARG PHP_VERSION=8.4

# = Dependencies

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# The extensions are checked in the PHP images below, not in this one.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --no-dev --optimize

# = PHP-FPM

FROM php:${PHP_VERSION}-fpm AS app

ARG WITH_REDIS=0

COPY docker/install-extensions.sh /usr/local/bin/install-extensions
RUN install-extensions "${WITH_REDIS}"

COPY docker/php.ini "${PHP_INI_DIR}/conf.d/zz-ozone.ini"
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# Writable directories, mounted as volumes by docker/compose.yaml.
# data/ is never created by OZone itself (it stands for the mounted volume), and the public
# symlinks are made by `oz project link` in the entrypoint, once the volume is there.
RUN mkdir -p data .ozone/logs \
	&& chown -R www-data:www-data data .ozone public

USER www-data

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# = nginx

FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public

# = Apache (mod_php): an alternative to `app` + `web`

FROM php:${PHP_VERSION}-apache AS apache

ARG WITH_REDIS=0

COPY docker/install-extensions.sh /usr/local/bin/install-extensions
RUN install-extensions "${WITH_REDIS}"

COPY docker/php.ini "${PHP_INI_DIR}/conf.d/zz-ozone.ini"
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY docker/apache/ozone.conf /etc/apache2/conf-available/ozone.conf

# The scope served (public/<scope>/, whose .htaccess routes every request to index.php).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public/api

RUN a2enmod rewrite headers \
	&& a2enconf ozone \
	&& sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

# data/ is never created by OZone itself (it stands for the mounted volume), and the public
# symlinks are made by `oz project link` in the entrypoint, once the volume is there.
RUN mkdir -p data .ozone/logs \
	&& chown -R www-data:www-data data .ozone public

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
