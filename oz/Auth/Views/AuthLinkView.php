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

use Override;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * Class AuthLinkView.
 *
 * Authorization links are opened with a GET, which only shows a confirmation page;
 * the authorization happens on the POST it submits. Mail scanners and link previews
 * fetch links with GET, so authorizing there would let them consume the token
 * before the user does.
 */
final class AuthLinkView extends WebView
{
	public const AUTH_LINK_ROUTE         = 'oz:auth_link';
	public const AUTH_LINK_CONFIRM_ROUTE = 'oz:auth_link:confirm';

	/**
	 * Shows the confirmation page, without touching the token; an unknown reference shows the page
	 * of a link that no longer works.
	 */
	public function confirm(RouteInfo $ri): Response
	{
		try {
			Auth::getRequired($ri->param('ref'));
		} catch (NotFoundException $e) {
			return $this->invalid($e);
		}

		return $this->setTemplate('oz.auth.link.confirm.blate')
			->respond();
	}

	/**
	 * Authorizes with the link token; a link unknown, used or expired shows the page of a link that no
	 * longer works.
	 */
	public function authorize(RouteInfo $ri): Response
	{
		$ref   = $ri->param('ref');
		$token = $ri->param('token');

		try {
			$auth = Auth::getRequired($ref);

			$provider = Auth::provider($ri->getContext(), $auth);

			$provider->getCredentials()
				->setReference($ref);

			$type = AuthorizationSecretType::TOKEN;
			$provider->getCredentials()
				->setToken($token);

			$provider->authorize($type);
		} catch (NotFoundException|UnauthorizedException $e) {
			return $this->invalid($e);
		}

		return $this->setTemplate('oz.auth.link.view.blate')
			->inject(['oz_auth_link_done' => true])
			->respond();
	}

	/**
	 * The page of a link that no longer works (unknown, used or expired), with the status of why.
	 */
	private function invalid(NotFoundException|UnauthorizedException $e): Response
	{
		return $this->setTemplate('oz.auth.link.view.blate')
			->inject(['oz_auth_link_done' => false])
			->respond()
			->withStatus($e->getHTTPStatusCode());
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get('/auth/link/:ref/:token', static function (RouteInfo $ri) {
				return (new self($ri))->confirm($ri);
			})
			->name(self::AUTH_LINK_ROUTE);

		$router
			->post('/auth/link/:ref/:token', static function (RouteInfo $ri) {
				return (new self($ri))->authorize($ri);
			})
			->name(self::AUTH_LINK_CONFIRM_ROUTE);
	}
}
