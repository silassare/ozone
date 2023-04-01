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

namespace OZONE\OZ\Exceptions\Views;

use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Web\WebView;

/**
 * Class ErrorView.
 */
final class ErrorView extends WebView
{
	/**
	 * @param \OZONE\OZ\Exceptions\BaseException $error
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function renderError(BaseException $error): Response
	{
		$context        = $this->getContext();
		$request        = $context->getRequest();
		$masked_message = BaseException::anErrorOccurredMessage();

		$back_url    = $context->getMainUrl();
		$err_message = ($error instanceof InternalErrorException ? $masked_message : $error->getMessage());
		$err_data    = $error->getData();
		$status      = $error->getHTTPStatusCode();

		$this->inject([
			'oz_error'             => $error,
			'oz_error_code'        => $error->getCode(),
			'oz_error_http_status' => $status,
			'oz_error_title'       => $error->getHTTPReasonPhrase(),
			'oz_error_message'     => $err_message,
			'oz_error_data'        => $err_data,
			'oz_error_url'         => $request->getUri(),
			'oz_error_back_url'    => $back_url,
		]);

		return $this->setTemplate('oz.error.otpl')
			->respond()
			->withStatus($status);
	}
}
