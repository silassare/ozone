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

namespace OZONE\Core\Auth\Methods;

use OZONE\Core\App\JSONResponse;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Router\Views\AccessGrantView;

/**
 * Trait HTTPAuthMethodTrait.
 */
trait HTTPAuthMethodTrait
{
	/**
	 * Ask the client for authentication.
	 *
	 * @throws UnauthorizedActionException
	 */
	public function ask(): void
	{
		$context = $this->ri->getContext();

		$exception = new UnauthorizedActionException();

		if ($context->shouldReturnJSON()) {
			$json = new JSONResponse();

			$json->setError($exception->getMessage())
				->setData($this->askInfo());

			$response = $context->getResponse()
				->withJson($json);
		} else {
			$view     = new AccessGrantView($context);
			$response = $view->renderAccessGrantAuth($this->realm);
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
	 * @return array
	 */
	abstract protected function askInfo(): array;
}
