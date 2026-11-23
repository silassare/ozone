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
 * A command line making a sub-request that is refused, run as its own process by
 * {@see ConsoleSubRequestTest}: what a job or a command does when it calls a route, and what must not
 * end it. Under the console the refusal used to be printed and the process ended with it, so reaching
 * the last line is the claim.
 */

use OZONE\Core\App\Context;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Runtime;
use OZONE\Tests\Exceptions\ConsoleSubRequestTest;

require __DIR__ . '/../../vendor/autoload.php';

require __DIR__ . '/../autoload.php';

\fwrite(\STDERR, \sprintf("console=%s\n", Runtime::isConsole() ? 'yes' : 'no'));

OZone::getApiRouter()->get('/console/refused', static function (): never {
	throw new ForbiddenException();
})->name('console:refused');

$response = Context::root()->callRoute('console:refused', [], [], false);

\fwrite(\STDERR, \sprintf("status=%d\n", $response->getStatusCode()));
\fwrite(\STDERR, "survived\n");
