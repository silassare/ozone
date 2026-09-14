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

// OZone once per request (`make benchmark-http`, ozone-classic): what a scope's index.php does under
// PHP-FPM, on the probe application of tests/Support/Servers/.

use OZONE\Core\OZone;

$app = require __DIR__ . '/../../../tests/Support/Servers/app.php';

OZone::run($app);
