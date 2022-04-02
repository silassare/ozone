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

const OZ_OZONE_VERSION      = '2.0.0';
const OZ_OZONE_VERSION_NAME = 'OZone v' . OZ_OZONE_VERSION;
const OZ_OZONE_IS_CLI       = ('cli' === \PHP_SAPI);
\define('OZ_OZONE_DIR', \dirname(__DIR__) . \DIRECTORY_SEPARATOR);
\define('OZ_OZONE_START_TIME', \microtime(true));

if (!\defined('PHP_INT_MIN')) {
	// Available since PHP 7.0.0 http://php.net/manual/en/reserved.constants.php
	\define('PHP_INT_MIN', ~\PHP_INT_MAX);
}
