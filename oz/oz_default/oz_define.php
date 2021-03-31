<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

\define('OZ_OZONE_VERSION', '2.0.0');
\define('OZ_OZONE_VERSION_NAME', 'O\'Zone v' . OZ_OZONE_VERSION);
\define('OZ_OZONE_IS_CLI', 'cli' === \php_sapi_name());

if (!\defined('PHP_INT_MIN')) {
	// Available since PHP 7.0.0 http://php.net/manual/en/reserved.constants.php
	\define('PHP_INT_MIN', ~\PHP_INT_MAX);
}
