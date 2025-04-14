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

namespace OZONE\Core\Auth\Traits;

use OZONE\Core\App\JSONResponse;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Router\Views\AccessGrantView;

/**
 * Trait AskCredentialsByHTTPHeaderTrait.
 */
trait AskCredentialsByHTTPHeaderTrait
{
	/**
	 * Ask the client for authentication.
	 *
	 * @throws UnauthorizedException
	 */
	public function ask(): void
	{
		$context = $this->ri->getContext();

		$exception = new UnauthorizedException();

		if ($context->shouldReturnJSON()) {
			$json = new JSONResponse();

			$json->setError($exception->getMessage())
				->setData($this->askInfo());

			$response = $context->getResponse()
				->withJson($json);
		} else {
			$view     = new AccessGrantView($context);
			$response = $view->renderAccessGrantAuth($this->askInfo());
		}

		throw $exception->setCustomResponse($response->withHeader('WWW-Authenticate', $this->askHeader()));
	}

	/**
	 * Should return the auth method header value to send to client.
	 *
	 * @return string
	 */
	abstract protected function askHeader(): string;

	/**
	 * Should return the auth method info used to build json response to be sent to client.
	 *
	 * Frontend developers can use this info to build the authentication form.
	 *
	 * @return array
	 */
	abstract protected function askInfo(): array;
}
