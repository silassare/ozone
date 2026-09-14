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

// `oz project build` for the probe application of tests/Support/Servers/ (`make benchmark-http`),
// which has no scopes/ for the command to find: its compiled caches, its class map and, in classic
// mode, the preload script. Run once the sandbox is prepared, before the server starts, with the mode
// ("classic" or "worker") as argument.

use OZONE\Core\Cli\Build\ProjectBuilder;
use OZONE\Core\Cli\Build\ScopeBuilder;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;

$classic = 'worker' !== ($argv[1] ?? 'classic');
$app     = require __DIR__ . '/../../../tests/Support/Servers/app.php';
$report  = ScopeBuilder::run($app, ScopeInterface::ROOT_SCOPE);

if (!$report['production']) {
	\fwrite(\STDERR, 'ENV_MODE is not "production": the production caches would never be read.' . \PHP_EOL);

	exit(1);
}

$classes = ProjectBuilder::writeClassMap();

if ($classic) {
	ProjectBuilder::writePreload($report['preload']);
}

\fwrite(\STDERR, \sprintf(
	'Built: %d settings bundles, a class map of %d classes, %s.' . \PHP_EOL,
	$report['settings_bundles'],
	$classes,
	$classic ? \count($report['preload']) . ' files to preload' : 'no preload (worker mode)'
));
