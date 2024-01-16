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

namespace OZONE\Core\Hooks;

use OZONE\Core\App\Context;
use PHPUtils\Events\Event;

/**
 * Class Hook.
 */
class Hook extends Event
{
	/**
	 * Hook constructor.
	 *
	 * @param Context $context
	 */
	public function __construct(public readonly Context $context) {}
}
