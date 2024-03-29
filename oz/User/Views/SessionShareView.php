<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\User\Views;

use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Exceptions\BadRequestException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\User\Services\SessionShare;
use OZONE\OZ\Web\WebViewBase;

/**
 * Class SessionShareView
 */
final class SessionShareView extends WebViewBase
{
	/**
	 * @throws \Exception
	 * @throws \OZONE\OZ\Exceptions\BadRequestException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function mainRoute()
	{
		$use_cookie  = SettingsManager::get('oz.authenticator', 'OZ_AUTH_ACCOUNT_COOKIE_ENABLED');
		$cookie_name = SettingsManager::get('oz.authenticator', 'OZ_AUTH_ACCOUNT_COOKIE_NAME');
		$context     = $this->getContext();
		$request     = $context->getRequest();

		if (!$use_cookie || !($token = $request->getCookieParam($cookie_name, null))) {
			$token = $request->getFormField('token', false);
		}

		if (empty($token)) {
			throw new BadRequestException();
		}

		$a = new SessionShare($context);
		$a->actionCheck($context, $token);

		$response = $context->getResponse();

		if (!empty($next = $request->getFormField('next', null))) {
			$response = $response->withRedirect($next);
		}

		return $response;
	}

	/**
	 * @inheritdoc
	 */
	public function getCompileData()
	{
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public function getTemplate()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->get('/oz-session-share', function (RouteInfo $r) {
			$view = new static($r);

			return $view->mainRoute();
		});
	}
}
