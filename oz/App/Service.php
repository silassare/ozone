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

namespace OZONE\Core\App;

use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Sessions\Session;

/**
 * Class Service.
 */
abstract class Service implements RouteProviderInterface
{
	private JSONResponse $json_response;

	private Context $context;

	/**
	 * Service constructor.
	 *
	 * @param \OZONE\Core\App\Context|\OZONE\Core\Router\RouteInfo $context
	 */
	public function __construct(Context|RouteInfo $context)
	{
		$this->context       = $context instanceof RouteInfo ? $context->getContext() : $context;
		$this->json_response = new JSONResponse();
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\Core\App\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Gets the service json response.
	 *
	 * @return \OZONE\Core\App\JSONResponse
	 */
	public function json(): JSONResponse
	{
		return $this->json_response;
	}

	/**
	 * Return service response.
	 *
	 * @return \OZONE\Core\Http\Response
	 */
	public function respond(): Response
	{
		$json_response = $this->json();
		$data          = $json_response->toArray();
		$now           = \time();
		$data['utime'] = $now;

		if ($this->context->hasAuthenticatedUser()) {
			$data['stime'] = $now + Session::lifetime();
		}

		return $this->context->getResponse()
			->withJson($data);
	}
}
