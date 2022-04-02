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

namespace OZONE\OZ\Hooks;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Http\Request;
use OZONE\OZ\Http\Response;
use PHPUtils\Events\Event;

/**
 * Class Hook.
 */
class Hook extends Event
{
	/**
	 * Hook constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(protected Context $context)
	{
	}

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
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Core\Context::getRequest()}.
	 *
	 * @return \OZONE\OZ\Http\Request
	 */
	public function getRequest(): Request
	{
		return $this->context->getRequest();
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Core\Context::getResponse()}.
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function getResponse(): Response
	{
		return $this->context->getResponse();
	}

	/**
	 * Shortcut for {@see \OZONE\OZ\Core\Context::setResponse()}.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return $this
	 */
	public function setResponse(Response $response): self
	{
		$this->context->setResponse($response);

		return $this;
	}
}
