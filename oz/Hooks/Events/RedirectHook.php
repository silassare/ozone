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
	 * @param \OZONE\Core\App\Context $context
	 * @param \OZONE\Core\Http\Uri    $redirect_uri
	 */
	public function __construct(Context $context, protected Uri $redirect_uri)
	{
		parent::__construct($context);
	}

	/**
	 * RedirectHook destructor.
	 */
	public function __destruct()
	{
		parent::__destruct();

		unset($this->redirect_uri);
	}

	/**
	 * Gets the redirect uri.
	 *
	 * @return \OZONE\Core\Http\Uri
	 */
	public function getRedirectUri(): Uri
	{
		return $this->redirect_uri;
	}
}
