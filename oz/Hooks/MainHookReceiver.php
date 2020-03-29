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

use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\MethodNotAllowedException;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Hooks\Interfaces\MainHookReceiverInterface;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Router\RouteInfo;

\defined('OZ_SELF_SECURITY_CHECK') || die;

final class MainHookReceiver implements MainHookReceiverInterface
{
	/**
	 * @inheritDoc
	 */
	public static function register()
	{
		MainHookProvider::registerHookReceiverClass(self::class, MainHookProvider::RUN_LAST);
	}

	/**
	 * @inheritDoc
	 */
	public function onInit(HookContext $context)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function onRequest(HookContext $hc)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function onSubRequest(HookContext $hc)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function onRouteFound(HookContext $hc, RouteInfo $route_info)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function onResponse(HookContext $hc)
	{
	}

	/**
	 * @inheritDoc
	 */
	public function onRedirect(HookContext $hc, Uri $target)
	{
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \Exception
	 */
	public function onRouteNotFound(HookContext $hc)
	{
		$context = $hc->getContext();
		$request = $hc->getRequest();
		$uri     = $request->getUri();

		// is it root
		if ($context->isApiContext() && $uri->getPath() === '/') {
			// TODO api doc
			// show api usage doc when this condition are met:
			//  - we are in api mode
			//	- debugging or allowed in settings
			// show welcome friendly page when this conditions are met:
			//  - we are in web mode
			throw new ForbiddenException();
		}

		throw new NotFoundException();
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \Exception
	 */
	public function onMethodNotAllowed(HookContext $hc)
	{
		throw new MethodNotAllowedException();
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	public function onFinish(HookContext $hc)
	{
		$ctx      = $hc->getContext();
		$response = $ctx->getResponse();
		$response = $ctx->getSession()
						->responseReady($response);

		$hc->setResponse($response);
	}
}
