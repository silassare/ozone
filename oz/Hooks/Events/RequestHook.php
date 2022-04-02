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

namespace OZONE\OZ\Hooks\Events;

use OZONE\OZ\Hooks\Hook;

/**
 * Class RequestHook.
 *
 * This event is triggered before the request is handled.
 * This may be called multiple times. Ex: when there is a sub-request.
 */
final class RequestHook extends Hook
{
	/**
	 * Shortcut for {@see \OZONE\OZ\Core\Context::isSubRequest()}.
	 *
	 * @return bool
	 */
	public function isSubRequest(): bool
	{
		return $this->context->isSubRequest();
	}
}
