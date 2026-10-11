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
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\OZone;
use OZONE\Core\Web\WebView;

/**
 * Class ErrorView.
 */
final class ErrorView extends WebView
{
	/**
	 * The statuses whose error page has a title of its own (`OZ_VIEW_ERROR_TITLE_<status>`).
	 */
	public const TITLED_STATUSES = [400, 401, 403, 404, 405, 429, 500, 503];

	/**
	 * @param BaseException $error
	 *
	 * @return Response
	 */
	public function renderError(BaseException $error): Response
	{
		$context = $this->getContext();
		$request = $context->getRequest();

		$back_url = $context->isApiContext() ? $context->getDefaultOrigin() : $context->buildUri('/');
		$status   = $error->getHTTPStatusCode();
		$message  = $error->getMessage();

		// An internal error says nothing of its cause to the visitor; a message that is not a lang key
		// (a developer's text) is not shown either.
		if ($error instanceof InternalErrorException || !Polyglot::isLangKey($message)) {
			$message = 'OZ_ERROR_INTERNAL';
		}

		$this->inject([
			'oz_error'             => $error,
			'oz_error_code'        => $error->getCode(),
			'oz_error_http_status' => $status,
			'oz_error_vars'        => ['oz_error_http_status' => $status],
			'oz_error_title'       => self::titleKey($status),
			'oz_error_message'     => $message,
			'oz_error_data'        => $error->getData(),
			'oz_error_url'         => $request->getUri(),
			'oz_error_back_url'    => $back_url,
			'oz_error_details'     => !OZone::inProductionMode(),
		]);

		return $this->setTemplate('oz.error.blate')
			->respond()
			->withStatus($status);
	}

	/**
	 * The lang key of the title of an error page: one per common status, a generic one otherwise.
	 */
	public static function titleKey(int $status): string
	{
		return \in_array($status, self::TITLED_STATUSES, true)
			? \sprintf('OZ_VIEW_ERROR_TITLE_%d', $status)
			: 'OZ_VIEW_ERROR_TITLE';
	}
}
