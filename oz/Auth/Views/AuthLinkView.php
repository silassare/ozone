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
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
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
	 * @param RouteInfo $ri
	 *
	 * @return Response
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function authorize(RouteInfo $ri): Response
	{
		$ref   = $ri->param('ref');
		$token = $ri->param('token');
		$auth  = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

		$provider->getCredentials()
			->setReference($ref);

		$type = AuthSecretType::TOKEN;
		$provider->getCredentials()
			->setToken($token);

		$provider->authorize($type);

		return $this->setTemplate('oz.auth.link.view.blate')
			->respond();
	}

	public static function registerRoutes(Router $router): void
	{
		$router
			->get('/auth/link/:ref/:token', static function (RouteInfo $ri) {
				return (new self($ri))->authorize($ri);
			})
			->name(self::AUTH_LINK_ROUTE);
	}
}
