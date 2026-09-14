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

// The FrankenPHP worker script (docker/compose.yaml, service `frankenphp`; see Caddyfile).

use OZONE\Core\Runtime\Bridges\FrankenPhpBridge;

$app = require __DIR__ . '/../app.php';

FrankenPhpBridge::serve($app);
