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

return [
	/**
	 * The OZone framework version. Do not override - auto-set from the framework constant.
	 */
	'OZ_OZONE_VERSION'          => OZ_OZONE_VERSION,

	/**
	 * Project name.
	 *
	 * Shown in the CLI and used during project/scope code generation.
	 */
	'OZ_PROJECT_NAME'           => 'Sample App',

	/**
	 * Root PHP namespace for generated classes.
	 */
	'OZ_PROJECT_NAMESPACE'      => 'NOOP',

	/**
	 * App class name used during project/scope code generation.
	 */
	'OZ_PROJECT_APP_CLASS_NAME' => 'NoopApp',

	/**
	 * Short prefix (2 characters) used in various place like for temp file names.
	 */
	'OZ_PROJECT_PREFIX'         => 'SA',

	/**
	 * Show a welcome page at the root URL in web context when no route found.
	 */
	'OZ_SHOW_WELCOME_PAGE'      => true,

	/**
	 * The absolute path of the PHP command-line binary OZone starts a PHP process with (an async
	 * job's `oz jobs run`, a scope's build), {@see \OZONE\Core\Cli\Process::phpBinary()}.
	 *
	 * Null: `PHP_BINARY` where this process is the command line, else a `php` found beside it, in
	 * `PHP_BINDIR` or on PATH. Set it where a request runs a cron tick (no scheduler) on a server
	 * whose CLI is none of those, or is not the version the server runs. A path that is not an
	 * executable file is an error, never a reason to guess. Here and not in a group a request can
	 * write, since it names a program OZone executes.
	 *
	 * @default null
	 */
	'OZ_PHP_CLI_BINARY'         => env('OZ_PHP_CLI_BINARY'),
];
