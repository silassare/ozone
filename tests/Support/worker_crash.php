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
 * A worker script that fails outside any request, run as its own process by
 * {@see WorkerLoopTest}: the loop itself throws, as a bootstrap or a bridge
 * could. No request is there to answer, so the process must end loudly -- a failing status and the
 * reason on stderr -- rather than with status 0 and the reason only in the OZone log.
 */

use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use OZONE\Tests\Runtime\WorkerLoopTest;

require __DIR__ . '/../../vendor/autoload.php';

require __DIR__ . '/../autoload.php';

Runtime::set(new WorkerRuntime('test-crash'));

throw new RuntimeException('the worker loop itself failed');
