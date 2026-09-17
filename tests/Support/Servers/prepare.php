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

/**
 * Builds the sandbox project a worker server serves, before the server starts: once per container,
 * so every worker process of the server shares one project and one database.
 */

use OZONE\Core\Testing\Sandbox;

$loader = require __DIR__ . '/../../../vendor/autoload.php';

// Also with a vendor installed without dev dependencies (see app.php).
$loader->addPsr4('OZONE\Tests\\', \dirname(__DIR__, 2) . '/');

$sandbox = \getenv('OZ_TEST_SERVER_SANDBOX');

if (!\is_string($sandbox) || '' === $sandbox) {
	\fwrite(\STDERR, 'OZ_TEST_SERVER_SANDBOX is not set.' . \PHP_EOL);

	exit(1);
}

// A restarted container starts over: fresh keys, empty database.
Sandbox::remove($sandbox);
// OZ_TEST_SERVER_INSTALLED: the schema installed through a migration, as a deployed project has it.
Sandbox::create($sandbox, (bool) \getenv('OZ_TEST_SERVER_INSTALLED'), [\dirname(__DIR__, 2) . '/settings']);

\fwrite(\STDERR, 'Sandbox ready: ' . $sandbox . \PHP_EOL);
