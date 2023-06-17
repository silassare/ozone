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

\define('OZ_OZONE_START_TIME', \microtime(true));

// = Don't forget to use DS instead of \ or / and
// = always add the last DS to your directories path
if (!\defined('DS')) {
	\define('DS', \DIRECTORY_SEPARATOR);
}

\define('OZ_OZONE_DIR', \dirname(__DIR__) . DS);

const OZ_OZONE_VERSION      = '3.0.0';
const OZ_OZONE_VERSION_NAME = 'OZone v' . OZ_OZONE_VERSION;
const OZ_OZONE_IS_CLI       = ('cli' === \PHP_SAPI);

// = Project directory
// = any relative path will be resolved using this path as starting point
if (!\defined('OZ_PROJECT_DIR')) {
	\define('OZ_PROJECT_DIR', \getcwd() . DS);
}

// = App directory
if (!\defined('OZ_APP_DIR')) {
	\define('OZ_APP_DIR', OZ_PROJECT_DIR . 'noop' . DS);
}

// = Logs directory
if (!\defined('OZ_LOG_DIR')) {
	\define('OZ_LOG_DIR', \getcwd() . DS);
}
