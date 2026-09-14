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

// FrankenPHP in classic mode (port 8081 of the `frankenphp` service; see ../frankenphp/Caddyfile): the
// script runs once per request, as a scope's index.php does under PHP-FPM. FrankenPHP defines
// frankenphp_handle_request() here too, which must not be taken for worker mode.

use OZONE\Core\OZone;

$app = require __DIR__ . '/../app.php';

OZone::run($app);
