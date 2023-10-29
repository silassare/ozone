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
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use PHPUtils\Events\Event;

/**
 * Class Hook.
 */
class Hook extends Event
{
	/**
	 * Hook constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 */
	public function __construct(protected Context $context) {}

	/**
	 * Hook destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * Gets context.
	 *
	 * @return \OZONE\Core\App\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Shortcut for {@see \OZONE\Core\App\Context::getRequest()}.
	 *
	 * @return \OZONE\Core\Http\Request
	 */
	public function getRequest(): Request
	{
		return $this->context->getRequest();
	}

	/**
	 * Shortcut for {@see \OZONE\Core\App\Context::getResponse()}.
	 *
	 * @return \OZONE\Core\Http\Response
	 */
	public function getResponse(): Response
	{
		return $this->context->getResponse();
	}

	/**
	 * Shortcut for {@see \OZONE\Core\App\Context::setResponse()}.
	 *
	 * @param \OZONE\Core\Http\Response $response
	 *
	 * @return $this
	 */
	public function setResponse(Response $response): self
	{
		$this->context->setResponse($response);

		return $this;
	}
}
