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

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthState;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Providers\AuthUserAccountKeyBasedAccessProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;

/**
 * Trait AuthUserKeyAuthenticationMethodTrait.
 */
trait AuthUserKeyAuthenticationMethodTrait
{
	protected ?AuthUserInterface $user                         = null;
	protected ?AuthUserAccountKeyBasedAccessProvider $provider = null;

	/**
	 * {@inheritDoc}
	 *
	 * @return AuthUserInterface
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function user(): AuthUserInterface
	{
		if (!isset($this->user)) {
			$this->authenticate();
		}

		/** @var AuthUserInterface $this->user */
		return $this->user;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return AuthAccessRightsInterface
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function getAccessRights(): AuthAccessRightsInterface
	{
		if (!isset($this->provider)) {
			$this->authenticate();
		}

		return $this->provider->getScope()->getAccessRight();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isScopedAuth(): bool
	{
		return true;
	}

	/**
	 * Authenticate with auth entity.
	 *
	 * @param OZAuth                 $auth
	 * @param null|AuthUserInterface $expected_user
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	protected function authenticateWithAuthEntity(OZAuth $auth, ?AuthUserInterface $expected_user = null): void
	{
		$context  = $this->ri->getContext();
		$provider = Auth::provider($context, $auth);

		if (!$provider instanceof AuthUserAccountKeyBasedAccessProvider) {
			throw (new ForbiddenException(null, [
				'_reason' => 'Invalid auth provider.',
			]))->suspectObject($auth);
		}

		$state = $provider->getState();

		if (AuthState::AUTHORIZED !== $state) {
			throw (new ForbiddenException(null, [
				'_reason' => 'Referenced auth is not authorized.',
			]))->suspectObject($auth);
		}

		$user = $provider->getUser();

		if (!$user->isAuthUserValid()) {
			throw (new ForbiddenException(null, [
				'_reason' => 'Auth user is not verified.',
			]))->suspectObject($user);
		}

		if ($expected_user) { // check if the expected user is the same as the authenticated user
			if (!AuthUsers::same($expected_user, $user)) {
				throw new ForbiddenException(null, [
					'_reason'        => 'Referenced auth is not for this user.',
					'_auth_user'     => AuthUsers::selector($user),
					'_expected_user' => AuthUsers::selector($expected_user),
				]);
			}
		}

		$this->user     = $user;
		$this->provider = $provider;
	}
}
