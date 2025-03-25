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

namespace OZONE\Core\Auth;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Enums\AuthenticationMethodType;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthorizationProviderInterface;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Db\OZAuthsQuery;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\OZone;
use OZONE\Core\Utils\Random;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Auth.
 */
final class Auth implements BootHookReceiverInterface
{
	/**
	 * Get an auth entity by ref.
	 *
	 * @param string $ref
	 *
	 * @return null|OZAuth
	 */
	public static function get(string $ref): ?OZAuth
	{
		try {
			$qb = new OZAuthsQuery();
			$qb->whereRefIs($ref);

			return $qb
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load auth data.', null, $t);
		}
	}

	/**
	 * Get an auth entity by token.
	 *
	 * @param string $token_hash
	 *
	 * @return null|OZAuth
	 */
	public static function getByTokenHash(string $token_hash): ?OZAuth
	{
		try {
			$qb = new OZAuthsQuery();
			$qb->whereTokenHashIs($token_hash);

			return $qb
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load auth entity.', null, $t);
		}
	}

	/**
	 * Get an auth entity by ref.
	 *
	 * @param string $ref
	 *
	 * @return OZAuth
	 *
	 * @throws NotFoundException           when not found
	 * @throws UnauthorizedActionException auth is disabled
	 */
	public static function getRequired(string $ref): OZAuth
	{
		$auth = self::get($ref);

		if (!$auth) {
			throw new NotFoundException('OZ_AUTH_INVALID_OR_DELETED_REF');
		}

		if (!$auth->isValid()) {
			throw new UnauthorizedActionException('OZ_AUTH_DISABLED');
		}

		return $auth;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		FinishHook::listen(static function () {
			self::gc();
		}, Event::RUN_LAST);
	}

	/**
	 * Gets instance of the a given auth provider name.
	 *
	 * @param Context $context
	 * @param OZAuth  $auth
	 *
	 * @return AuthorizationProviderInterface
	 */
	public static function provider(Context $context, OZAuth $auth): AuthorizationProviderInterface
	{
		$name     = $auth->getProvider();
		$provider = Settings::get('oz.auth.providers', $name);

		if (!$provider) {
			throw new RuntimeException(\sprintf('Undefined auth provider "%s".', $name));
		}
		if (!\is_subclass_of($provider, AuthorizationProviderInterface::class)) {
			throw new RuntimeException(
				\sprintf(
					'Auth provider "%s" should implements "%s".',
					$provider,
					AuthorizationProviderInterface::class
				)
			);
		}

		/* @var AuthorizationProviderInterface $provider */
		return $provider::resolve($context, $auth)
			->setScope(AuthorizationScope::from($auth));
	}

	/**
	 * Gets the authentication method class from settings.
	 *
	 * @param AuthenticationMethodType|string $method
	 *
	 * @return class-string<AuthenticationMethodInterface>
	 */
	public static function method(AuthenticationMethodType|string $method): string
	{
		if (!\is_string($method)) {
			$method = $method->value;
		}

		$class = Settings::get('oz.auth.methods', $method);

		if (!$class) {
			throw (new RuntimeException(
				\sprintf(
					'Authentication method "%s" not found in settings.',
					$method
				)
			))->suspectConfig('oz.auth.methods', $method);
		}

		if (!\class_exists($class) || !\is_subclass_of($class, AuthenticationMethodInterface::class)) {
			throw (new RuntimeException(
				\sprintf(
					'Authentication method "%s" should be subclass of: %s',
					$class,
					AuthenticationMethodInterface::class
				)
			))->suspectConfig('oz.auth.methods', $method);
		}

		return $class;
	}

	/**
	 * Gets the list of enabled auth methods to use for api requests.
	 *
	 * @return AuthenticationMethodType[]
	 */
	public static function apiAuthMethods(): array
	{
		return Settings::get('oz.auth', 'OZ_AUTH_API_AUTH_METHODS');
	}

	/**
	 * Deletes expired auth entities.
	 */
	private static function gc(): void
	{
		if (Random::bool() && OZone::hasDbInstalled()) {
			try {
				// delete auth that expired more than an hour ago
				$an_hour_ago = \time() - 3600;

				$qb = new OZAuthsQuery();

				$qb->whereExpireIsGt(0)
					->whereExpireIsLte($an_hour_ago)
					->delete()
					->execute();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to delete expired auth entities.', null, $t);
			}
		}
	}
}
