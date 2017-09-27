#!/usr/bin/env php
<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	// Protect from unauthorized access/include
	define('OZ_SELF_SECURITY_CHECK', 1);

	// Don't forget to use DS instead of \ or / and always add the last DS to your directories path
	define('DS', DIRECTORY_SEPARATOR);

	// Project directory
	define('OZ_PROJECT_DIR', getcwd() . DS);

	// OZone directory
	define('OZ_OZONE_DIR', __DIR__ . DS);

	// OZone app directory 
	define('OZ_APP_DIR', OZ_PROJECT_DIR . 'api' . DS . 'app' . DS);

	// Logs directory
	define('OZ_LOG_DIR', OZ_PROJECT_DIR);

	include_once OZ_OZONE_DIR . 'Cli' . DS . 'OZoneCli.php';

	$oz_cli = new \OZONE\OZ\Cli\OZoneCli;
	$oz_cli->run($argv);