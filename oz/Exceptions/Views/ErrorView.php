<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Exceptions\Views;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Utils\StringUtils;
use OZONE\OZ\Web\WebViewBase;

\defined('OZ_SELF_SECURITY_CHECK') || die;

final class ErrorView extends WebViewBase
{
	private $compileData = [];

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->map('*', '/oz:error', function (RouteInfo $r) {
			$view = new self($r);

			return $view->mainRoute();
		}, ['route:name' => 'oz:error']);
	}

	/**
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function mainRoute()
	{
		$context          = $this->r->getContext();
		$request          = $context->getRequest();
		$e                = $request->getAttribute('exception');
		$original_context = $request->getAttribute('context');
		$masked_message   = BaseException::MESSAGE_INTERNAL_ERROR;

		if ($e instanceof BaseException && $original_context instanceof Context) {
			$request = $original_context->getRequest();
		} else {
			$e = new InternalErrorException($masked_message);
		}

		$back_url             = $context->getMainUrl();
		$http_response_header = $e->getHeaderString();
		$err_title            = StringUtils::removePrefix($http_response_header, 'HTTP/1.1 ');
		$err_desc             = $e instanceof InternalErrorException ? $masked_message : $e->getMessage();
		$err_data             = $e->getData();
		$status               = $e->getResponseStatusCode();

		$this->compileData = [
			'oz_error'           => $e,
			'oz_error_code'      => $status,
			'oz_error_code_real' => $e->getCode(),
			'oz_error_title'     => $err_title,
			'oz_error_desc'      => $err_desc,
			'oz_error_data'      => $err_data,
			'oz_error_url'       => $request->getUri(),
			'oz_error_back_url'  => $back_url,
		];
		$response          = $context->getResponse();

		return $this->renderTo($response->withStatus($status));
	}

	/**
	 * @inheritdoc
	 */
	public function getCompileData()
	{
		return $this->compileData;
	}

	/**
	 * @inheritdoc
	 */
	public function getTemplate()
	{
		return 'error.otpl';
	}
}
