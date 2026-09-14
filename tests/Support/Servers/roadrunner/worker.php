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

// The RoadRunner worker (docker/compose.yaml, service `roadrunner`; see rr.yaml).

use OZONE\Core\Runtime\Bridges\RoadRunnerBridge;

$app = require __DIR__ . '/../app.php';

// spiral/roadrunner-http is a `suggest` of OZone, not a dependency: the image installs it apart
// (docker/runtimes/roadrunner/composer.json).
require \getenv('OZ_ROADRUNNER_AUTOLOAD') ?: '/opt/roadrunner/vendor/autoload.php';

RoadRunnerBridge::serve($app);
