#!/bin/sh
# Prepares an OZone project container, then runs its command (php-fpm, apache2-foreground, a
# cron loop, a queue worker...).
set -eu

cd /var/www/html

# The `apache` image starts as root (Apache drops to www-data): run oz as www-data there too, so
# the files it writes stay writable by PHP.
oz() {
	if [ "$(id -u)" = "0" ]; then
		su -s /bin/sh www-data -c "php vendor/bin/oz $*"
	else
		php vendor/bin/oz "$@"
	fi
}

# The state directories live in the mounted volume and the public symlinks are not in the image
# (they are not version-controlled), so every container creates them before doing anything else.
oz project link

# The ORM classes of OZone and of the plugins are generated in .ozone/: generating needs the
# project .env, mounted at run time, so it happens here rather than in the image.
oz db build --build-all --class-only

# Opt-in: apply pending migrations (run it in one container only, e.g. `app`).
if [ "${OZ_MIGRATE_ON_START:-0}" = "1" ]; then
	oz migrations run
fi

# The containers that answer requests (PHP-FPM, Apache): compile what their requests would at first
# use -- the .env, the settings, the route tables, a class map -- and the preload script. After the
# migrations, which change a setting the route tables are keyed by.
case "${1:-}" in
	php-fpm | apache2-foreground)
		oz project build --skip-orm

		# PHP compiles the preload script into OPcache when it starts, as root, then serves as www-data.
		# Opt out with OZ_PRELOAD=0.
		preload_ini="${PHP_INI_DIR:-/usr/local/etc/php}/conf.d/zz-ozone-preload.ini"

		if [ "$(id -u)" = "0" ]; then
			if [ "${OZ_PRELOAD:-1}" = "1" ] && [ -f .ozone/preload.php ]; then
				printf 'opcache.preload=%s\nopcache.preload_user=www-data\n' "$PWD/.ozone/preload.php" > "$preload_ini"
			else
				rm -f "$preload_ini"
			fi
		fi
		;;
esac

exec "$@"
