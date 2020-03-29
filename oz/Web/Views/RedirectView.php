<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Web\Views;

use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Web\WebViewBase;

\defined('OZ_SELF_SECURITY_CHECK') || die;

final class RedirectView extends WebViewBase
{
	private $compile_data = [];

	/**
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function mainRoute()
	{
		$context = $this->r->getContext();
		$request = $context->getRequest();
		$url     = $request->getAttribute('url');
		$status  = $request->getAttribute('status');

		if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
			throw new InternalErrorException('Invalid redirect url.');
		}

		$this->compile_data = [
			'oz_redirect_url' => \filter_var($url, \FILTER_SANITIZE_URL),
		];

		$response = $context->getResponse()
							->withRedirect($url, $status);

		return $this->renderTo($response);
	}

	/**
	 * @inheritdoc
	 */
	public function getCompileData()
	{
		return $this->compile_data;
	}

	/**
	 * @inheritdoc
	 */
	public function getTemplate()
	{
		return 'redirect.otpl';
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->map('*', '/oz:redirect', function (RouteInfo $r) {
			$view = new self($r);

			return $view->mainRoute();
		}, ['route:name' => 'oz:redirect']);
	}
}
