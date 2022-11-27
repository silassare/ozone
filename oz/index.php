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

use OZONE\OZ\Cli\Cli;

// = Load composer autoload
if (\file_exists(\getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
	require_once \getcwd() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
} else {
	require_once \dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
}

if (!\defined('OZ_TEST') || !OZ_TEST) {
	// = Run the cli
	Cli::run($argv);
}
