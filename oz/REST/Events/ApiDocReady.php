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

namespace OZONE\Core\REST\Events;

use OZONE\Core\REST\ApiDoc;
use PHPUtils\Events\Event;

/**
 * Class ApiDocReady.
 *
 * This event is dispatched when the ApiDoc is ready.
 * You can use this event to modify the ApiDoc before it is sent to the client.
 */
final class ApiDocReady extends Event
{
	/**
	 * ApiDocReady constructor.
	 */
	public function __construct(
		public readonly ApiDoc $doc,
	) {}
}
