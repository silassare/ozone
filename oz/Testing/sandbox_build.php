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

// Prepares a sandbox project (OZONE\Core\Testing\Sandbox::create() runs it in its own process): generates
// the ORM classes of every enabled ORM namespace (OZone's, the project's, the enabled plugins') in
// `.ozone/plugins/`, as `oz db build` does in a project, and creates the schema in its SQLite database,
// so whoever boots next finds both present, like an installed project.
//
// With `--installed` the schema is created the way a deployed project gets it: a migration is created
// and installed, which records its version (`OZ_MIGRATION_VERSION`), so every later boot loads the schema
// from that migration instead of rebuilding it from the table builders.
//
// Usage: php sandbox_build.php <composer autoload.php> <sandbox directory> [--installed] [--settings=<dir>]...

use OZONE\Core\Testing\Sandbox;

$autoload  = $argv[1] ?? '';
$sandbox   = $argv[2] ?? '';
$installed = false;
$sources   = [];

foreach (\array_slice($argv, 3) as $arg) {
	if ('--installed' === $arg) {
		$installed = true;
	} elseif (\str_starts_with($arg, '--settings=')) {
		$sources[] = \substr($arg, \strlen('--settings='));
	}
}

if ('' === $autoload || !\is_file($autoload) || '' === $sandbox || !\is_dir($sandbox)) {
	\fwrite(
		\STDERR,
		'Usage: php sandbox_build.php <autoload.php> <sandbox directory> [--installed] [--settings=<dir>]...'
		. \PHP_EOL
	);

	exit(1);
}

require $autoload;

Sandbox::bootstrap($sandbox, $sources);
Sandbox::build($installed);
