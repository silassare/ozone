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

namespace OZONE\Core\Runtime\Interfaces;

use OZONE\Core\Http\Response;
use OZONE\Core\Http\ResponseEmitter;
use OZONE\Core\OZone;

/**
 * Interface ResponseSinkInterface.
 *
 * Where a request's response goes when it is not written to PHP's output.
 *
 * PHP-FPM and FrankenPHP read the response from `header()` and `echo`, which is what OZone does by
 * default ({@see ResponseEmitter::emit()}). RoadRunner and Swoole do not: they
 * hand the worker a request object and expect a response object back, and under RoadRunner the
 * process output is the protocol pipe itself. A worker loop for such a server passes a sink to
 * {@see OZone::handleRequest()}, and the finished `Response` is given to it instead.
 *
 * It is called exactly where the response would have been written: after `ResponseHook` and the
 * final preparation (`Content-Length`, byte ranges), before `FinishHook`. So `FinishHook` keeps
 * running with the client already answered, whichever way the response left.
 */
interface ResponseSinkInterface
{
	/**
	 * Sends the response to the client.
	 *
	 * Read the body with {@see ResponseEmitter::bodyChunks()}: it honours a byte
	 * range already applied to the stream and never loads the body into memory at once.
	 *
	 * @param Response $response the prepared response
	 */
	public function send(Response $response): void;
}
