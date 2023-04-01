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

namespace OZONE\OZ\Auth;

use OZONE\OZ\Auth\Interfaces\AuthProviderInterface;
use OZONE\OZ\Auth\Interfaces\AuthScopeInterface;
use OZONE\OZ\Cli\Cli;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\OZAuth;
use OZONE\OZ\Db\OZAuthsQuery;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Exceptions\UnauthorizedActionException;
use OZONE\OZ\Hooks\Events\FinishHook;
use OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Auth.
 */
final class Auth implements BootHookReceiverInterface
{
	/**
	 * Get an auth by ref.
	 *
	 * @param string $ref
	 *
	 * @return null|\OZONE\OZ\Db\OZAuth
	 */
	public static function getByRef(string $ref): ?OZAuth
	{
		try {
			$qb = new OZAuthsQuery();

			return $qb->whereRefIs($ref)
					  ->find(1)
					  ->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load auth data.', null, $t);
		}
	}

	/**
	 * Get an auth by ref.
	 *
	 * @param string $ref
	 *
	 * @return \OZONE\OZ\Db\OZAuth
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException           when not found
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException auth is disabled
	 */
	public static function getRequiredByRef(string $ref): OZAuth
	{
		$auth = self::getByRef($ref);

		if (!$auth) {
			throw new NotFoundException('OZ_AUTH_INVALID_OR_DELETED_REF');
		}

		if (!$auth->valid) {
			throw new UnauthorizedActionException('OZ_AUTH_DISABLED');
		}

		return $auth;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		FinishHook::handle(static function () {
			self::gc();
		}, Event::RUN_LAST);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bootCli(Cli $cli): void
	{
	}

	/**
	 * Gets instance of the a given auth provider name.
	 *
	 * @param string                                            $name
	 * @param \OZONE\OZ\Core\Context                            $context
	 * @param null|\OZONE\OZ\Auth\Interfaces\AuthScopeInterface $scope
	 *
	 * @return \OZONE\OZ\Auth\Interfaces\AuthProviderInterface
	 */
	public static function getAuthProvider(string $name, Context $context, ?AuthScopeInterface $scope = null): AuthProviderInterface
	{
		$provider = Configs::get('oz.auth.providers', $name);

		if (!$provider) {
			throw new RuntimeException(\sprintf('Undefined auth provider "%s".', $name));
		}
		if (!\is_subclass_of($provider, AuthProviderInterface::class)) {
			throw new RuntimeException(\sprintf(
				'Auth provider "%s" should implements "%s".',
				$provider,
				AuthProviderInterface::class
			));
		}

		/* @var AuthProviderInterface $provider */
		return $provider::getInstance($context, $scope);
	}

	/**
	 * Deletes expired authorization process.
	 */
	private static function gc(): void
	{
		if (Hasher::randomBool()) {
			try {
				$an_hour_ago = \time() - 3600;

				$qb = new OZAuthsQuery();

				$qb->whereExpireIsGt(0)
				   ->whereExpireIsLte($an_hour_ago)
				   ->delete()
				   ->execute();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to delete expired authorization.', null, $t);
			}
		}
	}
}
