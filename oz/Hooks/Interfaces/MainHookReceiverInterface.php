<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Hooks\Interfaces;

use OZONE\OZ\Hooks\HookContext;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Router\RouteInfo;

interface MainHookReceiverInterface extends HookReceiverInterface
{
	/**
	 * Called when ozone is fully initialized.
	 *
	 * Just before processing the request.
	 *
	 * @param HookContext $hc
	 */
	public function onInit(HookContext $hc);

	/**
	 * Called when a request is initiated.
	 *
	 * @param HookContext $hc
	 */
	public function onRequest(HookContext $hc);

	/**
	 * Called when a sub request is initiated.
	 *
	 * @param HookContext $hc
	 */
	public function onSubRequest(HookContext $hc);

	/**
	 * Called when a route matches and will be executed.
	 *
	 * This is called until we get a route that returns a response.
	 *
	 * @param HookContext                $hc
	 * @param \OZONE\OZ\Router\RouteInfo $route_info
	 */
	public function onBeforeRouteRun(HookContext $hc, RouteInfo $route_info);

	/**
	 * Called when a route is found.
	 *
	 * @param HookContext                $hc
	 * @param \OZONE\OZ\Router\RouteInfo $route_info
	 */
	public function onRouteFound(HookContext $hc, RouteInfo $route_info);

	/**
	 * Called when no route was found for the requested resource.
	 *
	 * @param HookContext $hc
	 */
	public function onRouteNotFound(HookContext $hc);

	/**
	 * Called when the route was found but the request method is not allowed.
	 *
	 * @param HookContext $hc
	 */
	public function onMethodNotAllowed(HookContext $hc);

	/**
	 * Called when a route has been found and executed and we have a response.
	 *
	 * @param HookContext $hc
	 */
	public function onResponse(HookContext $hc);

	/**
	 * Called when the response is ready to be sent to the browser.
	 *
	 * @param HookContext $hc
	 */
	public function onFinish(HookContext $hc);

	/**
	 * Called when URL redirection is required.
	 *
	 * @param HookContext        $hc
	 * @param \OZONE\OZ\Http\Uri $target
	 */
	public function onRedirect(HookContext $hc, Uri $target);
}
