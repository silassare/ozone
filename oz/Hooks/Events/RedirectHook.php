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

use OZONE\OZ\Core\Context;
use OZONE\OZ\Hooks\Hook;
use OZONE\OZ\Http\Uri;

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
	 * @param \OZONE\OZ\Core\Context $context
	 * @param \OZONE\OZ\Http\Uri     $redirect_uri
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
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function getRedirectUri(): Uri
	{
		return $this->redirect_uri;
	}
}
