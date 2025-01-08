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
use OZONE\Core\Hooks\Hook;
use OZONE\Core\Http\Uri;

/**
 * Class RedirectHook.
 *
 * This event is triggered before each URL redirection.
 */
final class RedirectHook extends Hook
{
	/**
	 * RedirectHook constructor.
	 *
	 * @param Context $context The context
	 * @param Uri     $to      The redirection target
	 */
	public function __construct(Context $context, public readonly Uri $to)
	{
		parent::__construct($context);
	}
}
