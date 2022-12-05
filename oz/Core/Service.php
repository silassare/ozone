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

namespace OZONE\OZ\Core;

use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\Interfaces\RouteProviderInterface;
use OZONE\OZ\Router\RouteInfo;

/**
 * Class Service.
 */
abstract class Service implements RouteProviderInterface
{
	/**
	 * @var \OZONE\OZ\Core\JSONResponse
	 */
	private JSONResponse $json_response;

	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private Context $context;

	/**
	 * Service constructor.
	 *
	 * @param \OZONE\OZ\Core\Context|\OZONE\OZ\Router\RouteInfo $context
	 */
	public function __construct(Context|RouteInfo $context)
	{
		$this->context       = $context instanceof RouteInfo ? $context->getContext() : $context;
		$this->json_response = new JSONResponse();
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Gets the service json response.
	 *
	 * @return \OZONE\OZ\Core\JSONResponse
	 */
	public function getJSONResponse(): JSONResponse
	{
		return $this->json_response;
	}

	/**
	 * Return service response.
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function respond(): Response
	{
		$json_response = $this->getJSONResponse();
		$data          = $json_response->toArray();
		$now           = \time();
		$data['utime'] = $now;
		$um            = $this->context->getUsersManager();

		if ($um->userVerified()) {
			$session            = $this->context->getSession();
			$client             = $session->getClient();

			/** @var int $lifetime */
			$lifetime           = $client->getSessionLifeTime();
			$data['stime']      = $now + $lifetime;

			if (Configs::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_ENABLED')) {
				$data['stoken'] = $session->getDataStore()->getToken();
			}
		}

		return $this->context->getResponse()
			->withJson($data);
	}
}
