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

use OZONE\Core\App\Settings;
use OZONE\Core\OZone;
use OZONE\Tests\App;

// Register test-specific settings overrides before bootstrap so that
// lazily-loaded groups pick them up on first access.
Settings::addSource(__DIR__ . '/settings');

OZone::bootstrap(new App());
