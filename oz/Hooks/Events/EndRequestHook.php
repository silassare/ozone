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

use OZONE\Core\App\Context;
use OZONE\Core\OZone;
use PHPUtils\Events\Event;

/**
 * Class EndRequestHook.
 *
 * Dispatched by {@see OZone::endRequest()} when a request's state is released: the
 * place for per-request cleanup of anything kept outside the `Context` -- a static, a singleton, a
 * memoized value. Register the listener once, in a boot hook receiver's `boot()`.
 *
 * A worker dispatches it before and after each request, so a listener must be idempotent. The
 * context tree is released right after the listeners ran, whatever they did.
 *
 * !This event is not triggered for sub-request.
 */
final class EndRequestHook extends Event
{
	/**
	 * EndRequestHook constructor.
	 *
	 * @param null|Context $context the root context being released, or null when there is none
	 */
	public function __construct(
		public readonly ?Context $context
	) {}
}
