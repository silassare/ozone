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
 * The application the worker servers of tests/Runtime/Servers/ serve, from the sandbox project
 * prepare.php built when the container started (`OZ_TEST_SERVER_SANDBOX`).
 *
 * It stands where a project's worker script does the set-up of its scope's index.php.
 */

use OZONE\Core\App\Settings;
use OZONE\Core\Testing\Sandbox;
use OZONE\Core\Testing\SandboxApp;

$loader = require __DIR__ . '/../../../vendor/autoload.php';

// A vendor installed without dev dependencies (`make benchmark-http`, as a deployment installs it)
// has no autoload-dev: the probe and the sandbox app are registered here.
$loader->addPsr4('OZONE\Tests\\', \dirname(__DIR__, 2) . '/');

// The sandbox's SQLite database and the suite's settings, then the probe routes.
Sandbox::addSettingsSources([\dirname(__DIR__, 2) . '/settings']);
Settings::addSource(__DIR__ . '/settings');

return new SandboxApp((string) \getenv('OZ_TEST_SERVER_SANDBOX'));
