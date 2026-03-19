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
use OZONE\Core\App\Settings;
use OZONE\Core\Columns\Types\TypeUrl;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * Class LogoutAndRedirectView.
 */
final class LogoutAndRedirectView extends WebView
{
	public const LOGOUT_AND_REDIRECT_ROUTE = 'oz:logout:redirect';

	/**
	 * @param RouteInfo $ri
	 *
	 * @return never
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 *
	 * @psalm-suppress UnusedVariable
	 */
	public function logoutAndRedirect(RouteInfo $ri): never
	{
		$next    = $ri->getCleanFormField('next', '/');
		$context = $ri->getContext();

		if (Settings::get('oz.cache', 'OZ_CLEAR_SITE_DATA_HEADER_ON_LOGOUT')) {
			$rule = Settings::get('oz.cache', 'OZ_CLEAR_SITE_DATA_HEADER_VALUE');

			// Sending Clear-Site-Data on a 3xx response freezes Chrome.
			// Fix: respond with a 200 page that carries the header; the page
			// redirects the client via <meta refresh> + JS so no 3xx is involved.
			$response = $this->setTemplate('oz.redirect.blate')
				->inject(['oz_redirect_url' => $next])
				->respond()
				->withHeader('Clear-Site-Data', $rule);

			$context->setResponse($response);
			$context->respond();
		}

		$context->redirect($next);
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get('/logout', static function (RouteInfo $ri) {
				(new self($ri))->logoutAndRedirect($ri);
			})->form(static function () {
				$type = (new TypeUrl())->allowAbsolutePath();

				$form = new Form();
				$form->field('next')->type($type);

				return $form;
			})
			->name(self::LOGOUT_AND_REDIRECT_ROUTE);
	}
}
