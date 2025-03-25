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

namespace OZONE\Core\Router\Guards;

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Interfaces\AuthorizationProviderInterface;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class AuthorizationProviderRouteGuard.
 */
class AuthorizationProviderRouteGuard extends AbstractRouteGuard
{
	/**
	 * AuthorizationProviderRouteGuard constructor.
	 *
	 * @param string[] $allowed_providers The allowed providers names
	 */
	public function __construct(private array $allowed_providers) {}

	/**
	 * {@inheritDoc}
	 */
	public function toRules(): array
	{
		return [
			'allowed_providers' => $this->allowed_providers,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		return new self($rules['allowed_providers']);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{provider:AuthorizationProviderInterface, auth:OZAuth}
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function check(RouteInfo $ri): array
	{
		$context = $ri->getContext();

		$auth_ref = $context->getRequest()
			->getUnsafeFormField(OZAuth::COL_REF);

		if (empty($auth_ref)) {
			throw new ForbiddenException('OZ_AUTH_REF_NOT_PROVIDED');
		}

		$auth = Auth::getRequired($auth_ref);

		if (!\in_array($auth->provider, $this->allowed_providers, true)) {
			throw new ForbiddenException(null, [
				// don't reveal the provider to attacker,
				// it's like sending the attacker in the right direction
				'_reason'            => 'Auth provider is not allowed.',
				'_allowed_providers' => $this->allowed_providers,
				'_provider'          => $auth->provider,
			]);
		}

		$provider = Auth::provider($context, $auth);

		if (AuthorizationState::AUTHORIZED !== $provider->getState()) {
			throw new UnauthorizedActionException();
		}

		return [
			'provider' => $provider,
			'auth'     => $auth,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{provider:AuthorizationProviderInterface, auth:OZAuth}
	 */
	public static function resolveResults(RouteInfo $ri): array
	{
		return $ri->getGuardStoredResults(static::class);
	}
}
