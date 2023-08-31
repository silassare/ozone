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

namespace OZONE\Core\Web\Views;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Response;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * Class RedirectView.
 */
final class RedirectView extends WebView
{
	public const REDIRECT_ROUTE = 'oz:redirect';

	/**
	 * @return \OZONE\Core\Http\Response
	 */
	public function mainRoute(): Response
	{
		$context = $this->getContext();
		$request = $context->getRequest();
		$url     = $request->getAttribute('url');
		$status  = $request->getAttribute('status');

		if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
			throw new RuntimeException('Invalid redirect url.', ['_redirecting_to' => $url]);
		}

		$this->injectKey('oz_redirect_url', \filter_var($url, \FILTER_SANITIZE_URL));

		return $this->setTemplate('oz.redirect.otpl')
			->respond()
			->withRedirect($url, $status);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->map('*', OZone::INTERNAL_PATH_PREFIX . 'redirect', static function (RouteInfo $ri) {
				return (new self($ri))->mainRoute();
			})
			->name(self::REDIRECT_ROUTE);
	}
}
