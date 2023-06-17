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

namespace OZONE\Core\Auth\Views;

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthSecretType;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * Class AuthLinkView.
 */
final class AuthLinkView extends WebView
{
	public const AUTH_LINK_ROUTE = 'oz:auth_link';

	/**
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 *
	 * @return \OZONE\Core\Http\Response
	 *
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public function authorize(RouteInfo $ri): Response
	{
		$ref   = $ri->getParam('ref');
		$token = $ri->getParam('token');
		$auth  = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

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
