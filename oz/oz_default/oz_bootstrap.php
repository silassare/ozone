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

use OZONE\Core\Exceptions\Utils\ErrorUtils;

include_once __DIR__ . \DIRECTORY_SEPARATOR . 'oz_php_config.php';

// Whether a project is booting, by any of the constants one sets before it requires the autoloader:
// `OZ_PROJECT_DIR` and `OZ_APP_DIR` from its boot file, `OZ_SCOPE_NAME` from the scope's index.php,
// `OZ_CLI_PROJECT` from `oz/index.php`. Several of them, because one is enough and a project whose
// boot file was edited into another order still sets one of the others. `oz_define.php` below gives
// three of them a fallback, so the answer has to be read before that include.
//
// A tool that only autoloads the package -- static analysis, the code style fixer, a build script --
// sets none of them and is left with its own error handling and its own shutdown.
$oz_booting_a_project = \defined('OZ_PROJECT_DIR')
	|| \defined('OZ_APP_DIR')
	|| \defined('OZ_SCOPE_NAME')
	|| \defined('OZ_CLI_PROJECT');

// Kept, because `oz_define.php` below gives three of those constants a fallback: afterwards none of
// them answers this question any more, and `OZone::env()` is the other place that has to know whether
// there is a project under this process or only a working directory that happens to hold a file.
\define('OZ_BOOTING_PROJECT', $oz_booting_a_project);

include_once __DIR__ . \DIRECTORY_SEPARATOR . 'oz_define.php';

include_once __DIR__ . \DIRECTORY_SEPARATOR . 'oz_func.php';

// Here and not at the top of `OZone::bootstrap()` or `Cli::run()`, because what happens in between is
// where this is needed: the project's boot file, its app file, and every class the autoloader loads
// for them. An error there used to reach only PHP's own error log, and came back at the end of the run
// as the last error with no reason attached, which reads like a failure at shutdown.
if ($oz_booting_a_project) {
	ErrorUtils::registerHandlers(true);
}
