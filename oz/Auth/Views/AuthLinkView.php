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

namespace OZONE\OZ\Auth\Views;

use OZONE\OZ\Auth\Auth;
use OZONE\OZ\Auth\AuthScope;
use OZONE\OZ\Auth\AuthSecretType;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Web\WebView;

/**
 * Class AuthLinkView.
 */
final class AuthLinkView extends WebView
{
	public const AUTH_LINK_ROUTE = 'oz:auth_link';

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function authorize(RouteInfo $ri): Response
	{
		$ref   = $ri->getParam('ref');
		$token = $ri->getParam('token');
		$auth  = Auth::getRequiredByRef($ref);

		$provider = Auth::getAuthProvider($auth->provider, $ri->getContext(), AuthScope::from($auth));

		$provider->getCredentials()
			->setReference($ref);

		$type = AuthSecretType::TOKEN;
		$provider->getCredentials()
			->setToken($token);

		$provider->authorize($type);

		return $this->setTemplate('oz.auth.link.view.otpl')
			->respond();
	}

	public static function registerRoutes(Router $router): void
	{
		$router->get('/auth/link/:ref/:token', static function (RouteInfo $ri) {
			return (new self($ri))->authorize($ri);
		})
			->name(self::AUTH_LINK_ROUTE);
	}
}
