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

namespace OZONE\Core\Auth\Methods;

use OZONE\Core\Router\RouteInfo;

/**
 * Class DigestRFC2617Auth.
 */
class DigestRFC2617Auth extends DigestAuth
{
	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, string $realm): self
	{
		return new self($ri, $realm, true);
	}
}
