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

use OZONE\Core\Auth\Enums\AuthMethodType;
use OZONE\Core\Auth\Interfaces\AuthMethodInterface;
use OZONE\Core\Auth\Traits\HTTPAuthMethodTrait;
use OZONE\Core\Auth\Traits\UserAuthMethodTrait;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class BearerAuth.
 */
class BearerAuth implements AuthMethodInterface
{
	use HTTPAuthMethodTrait;
	use UserAuthMethodTrait;

	protected AuthMethodType $type = AuthMethodType::BEARER;
	protected string $token        = '';

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
		$authorization = $request->getHeaderLine('Authorization');

		if (empty($authorization) || !\str_starts_with(\strtolower($authorization), 'bearer ')) {
			return false;
		}

		$token = \substr($authorization, 7);

		if (!empty($token)) {
			$this->token = $token;

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, string $realm): self
	{
		return new self($ri, $realm);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function authenticate(): void
	{
		$this->authenticateWithToken($this->token);
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
			'type'  => $this->type->value,
			'realm' => $this->realm,
		];
	}
}
