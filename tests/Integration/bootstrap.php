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

// Minimal bootstrap for integration tests.
// Does NOT call OZone::bootstrap() - each test project runs its own bootstrap
// via 'bin/oz' subprocesses.
require_once __DIR__ . '/../../vendor/autoload.php';
