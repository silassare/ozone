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

namespace OZONE\OZ\Web\Views;

use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Web\WebView;

/**
 * Class RedirectView.
 */
final class RedirectView extends WebView
{
	public const MAIN_ROUTE = 'oz:redirect';

	/**
	 * @return \OZONE\OZ\Http\Response
	 */
	public function mainRoute(): Response
	{
		$context = $this->getContext();
		$request = $context->getRequest();
		$url     = $request->getAttribute('url');
		$status  = $request->getAttribute('status');

		if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
			throw new RuntimeException('Invalid redirect url.', ['url' => $url]);
		}

		$this->injectKey('oz_redirect_url', \filter_var($url, \FILTER_SANITIZE_URL));

		return $this->setTemplate('redirect.otpl')
			->respond()
			->withRedirect($url, $status);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->map('*', '/oz:redirect', function (RouteInfo $ri) {
			$view = new self($ri);

			return $view->mainRoute();
		})->name(self::MAIN_ROUTE);
	}
}
