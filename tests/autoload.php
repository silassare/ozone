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

use OZONE\Core\App\Settings;
use OZONE\Core\OZone;
use OZONE\Tests\App;
use OZONE\Tests\Support\Sandbox;

// PHPUnit prefers phpunit.xml over phpunit.xml.dist, so a local one silently decides which servers
// the suites see and which groups run. The Makefile passes `-c phpunit.xml.dist`; say so loudly
// when a stray file exists, rather than letting it drift out of date unnoticed.
if (\is_file(__DIR__ . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . 'phpunit.xml')) {
	\fwrite(\STDERR, "\nWarning: phpunit.xml exists and PHPUnit prefers it over phpunit.xml.dist.\n"
		. "         Run the suites with `make ...`, which passes `-c phpunit.xml.dist`, and delete it:\n"
		. "         server addresses belong in the environment (see CONTRIBUTING.md).\n\n");
}

// The suite runs in a sandbox project created for this run and removed when it ends, so tests
// neither depend on nor leave state in the repository (.env, data/, .ozone/).
$sandbox = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_unit_' . \getmypid() . '_' . \bin2hex(\random_bytes(4))
	. \DIRECTORY_SEPARATOR;

\register_shutdown_function(static function () use ($sandbox): void {
	Sandbox::remove($sandbox);
});

Sandbox::create($sandbox);

// Register test-specific settings overrides before bootstrap so that
// lazily-loaded groups pick them up on first access.
Settings::addSource(__DIR__ . '/settings');

OZone::bootstrap(new App($sandbox));
