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

namespace OZONE\Core\Exceptions\Views;

use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Http\Response;
use OZONE\Core\Web\WebView;

/**
 * Class ErrorView.
 */
final class ErrorView extends WebView
{
	/**
	 * @param \OZONE\Core\Exceptions\BaseException $error
	 *
	 * @return \OZONE\Core\Http\Response
	 */
	public function renderError(BaseException $error): Response
	{
		$context        = $this->getContext();
		$request        = $context->getRequest();
		$masked_message = BaseException::anErrorOccurredMessage();

		$back_url    = $context->getDefaultOrigin();
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
