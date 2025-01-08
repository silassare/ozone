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

namespace OZONE\Core\Hooks\Events;

use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use PHPUtils\Events\Event;

/**
 * Class FinishHook.
 *
 * This event is triggered when the response
 * is already sent to the client.
 *
 * !This event is not triggered for sub-request.
 */
final class FinishHook extends Event
{
	/**
	 * FinishHook constructor.
	 *
	 * @param Request  $request  the request
	 * @param Response $response the response
	 */
	public function __construct(
		public readonly Request $request,
		public readonly Response $response
	) {}
}
