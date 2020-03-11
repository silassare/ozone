#!/bin/bash

#"""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""
# This script is intended to be run like this:
#
# wget -q -O - https://github.com/silassare/ozone/raw/master/install.sh | sudo bash
#
#"""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""

# clear the screen
clear

shout(){
	case $2 in
		error)
		echo -e "\033[31;31m$1\033[0m"
		;;

		success)
		echo -e "\033[0;32m$1\033[0m"
		;;

		info)
		echo -e "\033[33;33m$1\033[0m"
		;;

		*)
		echo "$1"
		;;
	esac
}

shout_error(){
	shout "$1" "error"
}

shout_info(){
	shout "$1" "info"
}

shout_success(){
	shout "$1" "success"
}

install_composer(){
	shout "Installing composer..."

	# https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
	local EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	local ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

	if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
		shout_error 'Composer - Invalid installer signature'
		rm composer-setup.php
		exit 1
	fi

	php composer-setup.php --quiet --filename=composer --install-dir=/usr/bin/

	local RESULT=$?

	rm composer-setup.php

	return $RESULT
}

install_nginx(){
	# install nginx and Uncomplicated FireWall
	shout "Installing nginx and ufw (Uncomplicated FireWall)"

	apt-get -qq -o=Dpkg::Use-Pty=0 -y install nginx ufw < /dev/null

	# enable nginx via firewall
	# Nginx Full: 	This profile opens both port 80 (normal, unencrypted web traffic)
	# 		and port 443 (TLS/SSL encrypted traffic)
	# Nginx HTTP: 	This profile opens only port 80 (normal, unencrypted web traffic)
	# Nginx HTTPS: 	This profile opens only port 443 (TLS/SSL encrypted traffic)

	shout "Opening port 80 and 443 for nginx..."
	ufw allow 'Nginx Full'
}

install_git(){
	shout "Installing git..."
	apt-get -qq -o=Dpkg::Use-Pty=0 -y install git < /dev/null
}

install_php(){
	shout "Installing php and required extensions..."

	# install php and modules
	apt-get -qq -o=Dpkg::Use-Pty=0 -y install php-fpm php-mysql php-gd php-xml php-curl php-zip < /dev/null
}

configure_php(){

	shout "Updating php-fpm ini file..."

	local PHP_INSTALL_DIR="$(php -r "echo str_replace('/cli/php.ini', '', php_ini_loaded_file());")"

	set_php_ini_value date.timezone utc "$PHP_INSTALL_DIR"/fpm/php.ini
	set_php_ini_value cgi.fix_pathinfo 0 "$PHP_INSTALL_DIR"/fpm/php.ini
}

install_mysql(){

	local ROOT_PASS="$1"
	local DB_NAME="$2"
	local DB_USER="$3"
	local DB_PASS="$4"

	if [ "$ROOT_PASS" == "" ]; then
		ROOT_PASS="root"
	fi

	# install mysql server
	shout "Installing mysql server..."

	apt-get -qq -o=Dpkg::Use-Pty=0 -y install mysql-server < /dev/null

	# mysql_install_db deprecated using mysqld --initialize
	mysqld --initialize

	shout "Securring mysql..."

	# alternative to　"mysql_secure_installation"
	service mysql restart
	mysqladmin -u root password "$ROOT_PASS"
	mysql -u root -p$ROOT_PASS -e "DELETE FROM mysql.user WHERE User='';"
	mysql -u root -p$ROOT_PASS -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1');"
	mysql -u root -p$ROOT_PASS -e "DROP DATABASE test;"
	mysql -u root -p$ROOT_PASS -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"

	if [ "$DB_NAME" != "" ] && [ "$DB_USER" != "" ] && [ "$DB_PASS" != "" ] ; then
		mysql -u root -p$ROOT_PASS -e "CREATE DATABASE $DB_NAME DEFAULT CHARACTER SET utf8;"
		mysql -u root -p$ROOT_PASS -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO $DB_USER@'%' IDENTIFIED BY '$DB_PASS' WITH GRANT OPTION;"
	fi

	mysql -u root -p$ROOT_PASS -e "FLUSH PRIVILEGES;"

	# service mysql stop
	# this hang the script
	# mysqld_safe

	service mysql restart

}

install_7zip(){
	# install 7zip
	shout "Installing 7zip..."
	apt-get -qq -o=Dpkg::Use-Pty=0 -y install p7zip-full < /dev/null
}

# usage: set_php_ini_value date.timezone utc /fpm/php.ini
set_php_ini_value(){

	local KEY="$1"
	local VALUE="$2"
	local INI_FILE="$3"

	shout "Settings $KEY = $VALUE in $INI_FILE"
	sed -i -e "s/;\?$KEY\s*=\s*.*/$KEY = $VALUE/g" "$INI_FILE"
}

create_oz_sh(){
	local VERSION="$1"
	local OUT="$2"

	cat << "EOF" > "$OUT"
#!/bin/bash

# When the current folder is that of an O'Zone project
# we launch the version corresponding to that used in
# the project or we use the default version

INSTALL_DIR=/opt/ozone
CONFIG_FILE="$(pwd)/api/app/oz_settings/oz.config.php"
VERSION="__DEFAULT_VERSION__"

if [ -f "$CONFIG_FILE" ]; then

	PROJECT_VERSION=$(grep "OZ_OZONE_VERSION" "$CONFIG_FILE" | sed -s 's/[^0-9\.]//g')

	if [ "$PROJECT_VERSION" != "" ] && [ -d "$INSTALL_DIR/$PROJECT_VERSION" ]; then
		VERSION=$PROJECT_VERSION
	fi
fi

"$INSTALL_DIR/$VERSION/oz/index.php" "$@"

EOF

	sed -i -e "s/__DEFAULT_VERSION__/$VERSION/g" "$OUT"
}

#"""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""""

# Are we running as root?
if [ $EUID -ne 0 ]; then
	shout_error "This script must be run as root. Did you leave out sudo?"
	exit
fi

# https://linuxhint.com/debian_frontend_noninteractive/
DEBIAN_FRONTEND_SAVED="$DEBIAN_FRONTEND"
export DEBIAN_FRONTEND=noninteractive

# install all required packages
shout "Updating packages list..."

apt-get -qq -o=Dpkg::Use-Pty=0 -y update < /dev/null

shout "Installing all required packages..."

apt-get -qq -o=Dpkg::Use-Pty=0 -y install apt-utils < /dev/null


shout "Getting latest O'Zone version"

CURRENT_VERSION="$(wget -q -O - https://github.com/silassare/ozone/raw/master/VERSION | sed -s 's/[^0-9\.]//g')"
INSTALL_PATH=/opt/ozone/$CURRENT_VERSION
FRESH_INSTALL=0

if [ "$CURRENT_VERSION" == "" ]; then
	shout_error "Unable to retrieve current O'Zone version."
	exit
fi

shout_info "Current verion is: v$CURRENT_VERSION"

# install mysql

if [ "$(which mysql)" == "" ]; then
	install_mysql "root"
	echo
fi

# install nginx

if [ "$(which nginx)" == "" ]; then
	install_nginx
	echo
fi

# install 7zip

if [ "$(which 7z)" == "" ]; then
	install_7zip
	echo
fi

# install php and required extensions

if [ "$(which php)" == "" ]; then
	install_php
	echo
fi

# configure php

configure_php

# install git

if [ "$(which git)" == "" ]; then
	install_git
	echo
fi

# install composer

if [ "$(which composer)" == "" ]; then
	install_composer
	echo
fi

# Clone the O'Zone repository if it doesn't exist.

if [ ! -d "$INSTALL_PATH" ]; then
	shout "Downloading O'Zone v$CURRENT_VERSION..."
	git clone -b v"$CURRENT_VERSION" --depth 1 https://github.com/silassare/ozone "$INSTALL_PATH" < /dev/null 2> /dev/null
	FRESH_INSTALL=1
	echo
fi

# Change directory to install path.
cd "$INSTALL_PATH" || exit


if [ $FRESH_INSTALL == 0 ]; then
	shout_info "O'Zone v$CURRENT_VERSION is already installed..."
	# Update it.
	if [ "v$CURRENT_VERSION" != "$(git describe)" ]; then
		shout "Updating O'Zone to $CURRENT_VERSION..."
		git fetch --depth 1 --force --prune origin tag v"$CURRENT_VERSION"
		if ! git checkout -q v"$CURRENT_VERSION"; then
			shout_error "Update failed. Did you modify something in $(pwd)?"
			exit
		fi
		echo
	fi

fi

shout "Running composer install..."
composer install

shout "Making O'Zone Cli globally executable..."

OZ_EXECUTABLE=/usr/bin/oz
OZ_INDEX=$INSTALL_PATH/oz/index.php

# O'Zone Cli should be accessible using "oz" or "ozone" command
create_oz_sh "$CURRENT_VERSION" "$OZ_EXECUTABLE"
ln -s -f "$OZ_EXECUTABLE" /usr/bin/ozone

# Set all directories permissions to 755 rwxr-xr-x
find . -type d -exec chmod 755 {} \;
# Set all files permissions to 644 rw-r--r--
find . -type f -exec chmod 644 {} \;

# Allow execution for owner and group on this files
chmod ug+x $OZ_EXECUTABLE
chmod ug+x "$OZ_INDEX"

if [ $FRESH_INSTALL == 1 ]; then
	shout_success "O'Zone v$CURRENT_VERSION successfuly installed..."
fi

export DEBIAN_FRONTEND="$DEBIAN_FRONTEND_SAVED"
