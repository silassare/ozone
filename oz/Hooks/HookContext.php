<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Hooks;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Http\Response;
use RuntimeException;

\defined('OZ_SELF_SECURITY_CHECK') || die;

class HookContext
{
	/**
	 * @var \OZONE\OZ\Http\Response
	 */
	private $response;

	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * @var bool
	 */
	private $can_set_response;

	/**
	 * HookContext constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param bool                   $can_set_response
	 */
	public function __construct(Context $context, $can_set_response = true)
	{
		$this->response         = $context->getResponse();
		$this->context          = $context;
		$this->can_set_response = $can_set_response;
	}

	/**
	 * HookContext destructor.
	 */
	public function __destruct()
	{
		unset($this->response, $this->context);
	}

	/**
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * @return \OZONE\OZ\Http\Request
	 */
	public function getRequest()
	{
		return $this->context->getRequest();
	}

	/**
	 * @return \OZONE\OZ\Http\Response
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return \OZONE\OZ\Hooks\HookContext
	 */
	public function setResponse(Response $response)
	{
		if (!$this->can_set_response) {
			throw new RuntimeException('The response cannot be modified in this hook context.');
		}

		$this->response = $response;

		return $this;
	}
}
