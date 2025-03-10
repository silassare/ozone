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

use OZONE\Core\Cli\Cli;

// = Check if we are in an existing project root
if (\file_exists(\getcwd() . \DIRECTORY_SEPARATOR . 'app' . \DIRECTORY_SEPARATOR . 'boot.php')) {
	// = Check if the project has been installed
	if (!\file_exists(\getcwd() . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php')) {
		exit('Found an existing project but no autoload file.' . \PHP_EOL
			 . 'Please run "composer install" in your project directory.' . \PHP_EOL);
	}

	// = Load the project boot file
	require_once \getcwd() . \DIRECTORY_SEPARATOR . 'app' . \DIRECTORY_SEPARATOR . 'boot.php';
} else {
	// = There is no project, load ozone autoload file
	require_once \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';
}

// = Start the cli and execute any requested command
Cli::run($argv);
