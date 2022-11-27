<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

const OZ_OZONE_VERSION           = '3.0.0';
const OZ_OZONE_VERSION_NAME      = 'OZone v' . OZ_OZONE_VERSION;
const OZ_OZONE_PACKAGE_NAME      = 'silassare/ozone';
const OZ_OZONE_IS_CLI            = ('cli' === \PHP_SAPI);
\define('OZ_OZONE_DIR', \dirname(__DIR__) . \DIRECTORY_SEPARATOR);
\define('OZ_OZONE_START_TIME', \microtime(true));

// = Don't forget to use DS instead of \ or / and
// = always add the last DS to your directories path
if (!\defined('DS')) {
	\define('DS', \DIRECTORY_SEPARATOR);
}

// = Project directory
// = any relative path will be resolved using this path as starting point
if (!\defined('OZ_PROJECT_DIR')) {
	\define('OZ_PROJECT_DIR', \getcwd() . DS);
}

// = App directory
if (!\defined('OZ_APP_DIR')) {
	\define('OZ_APP_DIR', OZ_PROJECT_DIR . 'api' . DS . 'app' . DS);
}

// = Files directory
if (!\defined('OZ_FILES_DIR')) {
	\define('OZ_FILES_DIR', OZ_APP_DIR . 'oz_users_files' . DS);
}

// = Cache directory
if (!\defined('OZ_CACHE_DIR')) {
	\define('OZ_CACHE_DIR', OZ_APP_DIR . 'oz_cache' . DS);
}

// = Logs directory
if (!\defined('OZ_LOG_DIR')) {
	\define('OZ_LOG_DIR', \getcwd() . DS);
}