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

// The Swoole server (docker/compose.yaml, service `swoole`).

use OZONE\Core\Runtime\Bridges\SwooleBridge;
use Swoole\Http\Server;

$app = require __DIR__ . '/../app.php';

$server = new Server('0.0.0.0', 8080);

$server->set([
	// Two workers, so the tests see requests spread over more than one process.
	'worker_num' => 2,
	'log_level'  => SWOOLE_LOG_WARNING,
]);

SwooleBridge::attach($server, $app);

$server->start();
