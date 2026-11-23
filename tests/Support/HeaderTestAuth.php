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

namespace OZONE\Tests\Support;

use Override;
use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Router\RouteInfo;

/**
 * An authentication method of the suite: a request carrying `X-Test-User` is signed in as that user,
 * any other is not. It reads the request it is given and nothing else, so what it says about a
 * request is what that request carries.
 *
 * @internal
 */
final class HeaderTestAuth implements AuthenticationMethodInterface
{
	public const string HEADER = 'X-Test-User';

	private function __construct(private readonly string $user_id) {}

	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self($ri->getContext()->getRequest()->getHeaderLine(self::HEADER));
	}

	#[Override]
	public function satisfied(): bool
	{
		return '' !== $this->user_id;
	}

	#[Override]
	public function authenticate(): void {}

	#[Override]
	public function user(): AuthUserInterface
	{
		if ('' === $this->user_id) {
			throw new UnauthenticatedException();
		}

		return new TestAuthUser('user', $this->user_id);
	}

	#[Override]
	public function getAccessRights(): AccessRightsInterface
	{
		return new AccessRights();
	}

	#[Override]
	public function isScoped(): bool
	{
		return false;
	}

	#[Override]
	public function ask(): void {}
}
