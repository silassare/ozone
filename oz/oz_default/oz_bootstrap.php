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

// Whether a project is booting: its boot file defines this before it requires the autoloader, and
// `oz_define.php` below gives it a fallback, so the answer has to be read before that include. A tool
// that only autoloads the package -- static analysis, the code style fixer, a build script -- defines
// nothing and is left with its own error handling.
$oz_booting_a_project = \defined('OZ_PROJECT_DIR');

include_once __DIR__ . \DIRECTORY_SEPARATOR . 'oz_define.php';

include_once __DIR__ . \DIRECTORY_SEPARATOR . 'oz_func.php';

// Here and not at the top of `OZone::bootstrap()` or `Cli::run()`, because what happens in between is
// where this is needed: the project's boot file, its app file, and every class the autoloader loads
// for them. An error there used to reach only PHP's own error log, and came back at the end of the run
// as the last error with no reason attached, which reads like a failure at shutdown.
if ($oz_booting_a_project) {
	ErrorUtils::registerHandlers();
}
