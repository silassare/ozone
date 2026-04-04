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
];
