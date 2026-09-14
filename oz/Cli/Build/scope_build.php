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

// One scope of a project, prepared for production in a process of its own: run by `oz project build`
// from the project directory, with the scope name as argument. Prints its report as JSON, last.

use OZONE\Core\Cli\Build\ScopeBuilder;

$scope = $argv[1] ?? '';
$root  = \getcwd() . \DIRECTORY_SEPARATOR;

// As the scope's index.php sets it up.
\define('OZ_SCOPE_NAME', $scope);

$index = $root . 'public' . \DIRECTORY_SEPARATOR . $scope . \DIRECTORY_SEPARATOR . 'index.php';

if (\is_file($index) && \str_contains((string) \file_get_contents($index), 'OZ_OZONE_IS_WEB_CONTEXT')) {
	\define('OZ_OZONE_IS_WEB_CONTEXT', true);
}

require_once $root . 'app' . \DIRECTORY_SEPARATOR . 'boot.php';

/** @psalm-suppress MissingFile the project's, which psalm does not analyse */
$app = require OZ_APP_DIR . 'app.php';

/** @psalm-suppress InternalMethod OZone's own tooling */
$report = ScopeBuilder::run($app, $scope);

echo \PHP_EOL, \json_encode($report, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), \PHP_EOL;
