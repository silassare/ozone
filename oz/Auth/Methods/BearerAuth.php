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

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Traits\AskCredentialsByHTTPHeaderTrait;
use OZONE\Core\Auth\Traits\AuthUserKeyAuthenticationMethodTrait;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class BearerAuth.
 */
class BearerAuth implements AuthenticationMethodInterface
{
	use AskCredentialsByHTTPHeaderTrait;
	use AuthUserKeyAuthenticationMethodTrait;

	protected AuthenticationMethodScheme $scheme = AuthenticationMethodScheme::BEARER;
	protected string $token                      = '';

	/**
	 * BearerAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm) {}

	/**
	 * BearerAuth destructor.
	 */
	public function __destruct()
	{
		unset($this->ri);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, string $realm): self
	{
		return new self($ri, $realm);
	}

	/**
	 * Returns the token.
	 *
	 * @return string
	 */
	public function getToken(): string
	{
		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	public function satisfied(): bool
	{
		$context       = $this->ri->getContext();
		$request       = $context->getRequest();
		$header_line   = $request->getHeaderLine('Authorization');

		if (empty($header_line) || !\str_starts_with(\strtolower($header_line), 'bearer ')) {
			return false;
		}

		$token = \substr($header_line, 7);

		if (!empty($token)) {
			$this->token = $token;

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function authenticate(): void
	{
		$auth = Auth::getByTokenHash($this->token);

		if (!$auth) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid auth token.',
				'_token'  => $this->token,
			]);
		}

		$this->authenticateWithAuthEntity($auth);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function askHeader(): string
	{
		return 'Bearer realm="' . $this->realm . '"';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function askInfo(): array
	{
		return [
			'scheme' => $this->scheme->value,
			'realm'  => $this->realm,
		];
	}
}
