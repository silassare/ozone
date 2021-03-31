<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use OZONE\OZ\Router\RouteProviderInterface;

abstract class BaseService implements RouteProviderInterface
{
	/**
	 * @var \OZONE\OZ\Core\ResponseHolder
	 */
	private $response_holder;

	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * BaseService constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context         = $context;
		$this->response_holder = new ResponseHolder(static::class);
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * Gets the service responses holder object.
	 *
	 * @return \OZONE\OZ\Core\ResponseHolder
	 */
	public function getResponseHolder()
	{
		return $this->response_holder;
	}

	/**
	 * Return service response.
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function respond()
	{
		$response_holder = $this->getResponseHolder();
		$data            = $response_holder->getResponse();
		$now             = \time();
		$data['utime']   = $now;
		$um              = $this->context->getUsersManager();

		if ($um->userVerified()) {
			$lifetime      = 1 * $this->context->getClient()
											   ->getSessionLifeTime();
			$data['stime'] = $now + $lifetime;

			if (SettingsManager::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_ENABLED')) {
				$data['stoken'] = $um->getCurrentSessionToken();
			}
		}

		return $this->context->getResponse()
							 ->withJson($data);
	}
}
