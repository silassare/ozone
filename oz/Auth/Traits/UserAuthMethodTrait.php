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
use OZONE\Core\Auth\Providers\UserAuthProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;

/**
 * Trait UserAuthMethodTrait.
 */
trait UserAuthMethodTrait
{
	protected AuthUserInterface $user;
	protected UserAuthProvider $provider;

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
		/** @psalm-suppress RedundantPropertyInitializationCheck */
		if (!isset($this->user)) {
			$this->authenticate();
		}

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
	public function accessRights(): AuthAccessRightsInterface
	{
		/** @psalm-suppress RedundantPropertyInitializationCheck */
		if (!isset($this->provider)) {
			$this->authenticate();
		}

		return $this->provider->getScope()->getAccessRight();
	}

	/**
	 * Authenticate with token.
	 *
	 * @param string $token
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	protected function authenticateWithToken(string $token): void
	{
		$context = $this->ri->getContext();
		$auth    = Auth::getByTokenHash($token);

		if (!$auth) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid auth token.',
				'_token'  => $token,
			]);
		}

		$provider = Auth::provider($context, $auth);

		if (!$provider instanceof UserAuthProvider) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid auth provider.',
				'_token'  => $token,
			]);
		}

		$state = $provider->getState();

		if (AuthState::AUTHORIZED !== $state) {
			throw new ForbiddenException(null, [
				'_reason' => 'Referenced auth is not authorized.',
				'_token'  => $token,
			]);
		}

		$user = $provider->getUser();

		if (!$user->isValid()) {
			throw new ForbiddenException(null, [
				'_reason' => 'Disabled user.',
			]);
		}

		$this->user     = $user;
		$this->provider = $provider;
	}

	/**
	 * Try to get a user auth with a given auth ref.
	 *
	 * @param string $uid
	 * @param string $auth_ref
	 *
	 * @return OZAuth
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	protected function getUserAuthWithRef(string $uid, string $auth_ref): OZAuth
	{
		$context = $this->ri->getContext();
		$auth    = Auth::get($auth_ref);

		$user = AuthUsers::identify($uid);

		if (!$user) {
			// invalid username
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid username.',
			]);
		}

		if (!$user->isValid()) {
			throw new ForbiddenException(null, [
				'_reason' => 'Disabled user.',
			]);
		}

		if (!$auth) {
			throw new ForbiddenException(null, [
				'_reason'   => 'Invalid auth ref.',
				'_auth_ref' => $auth_ref,
			]);
		}

		$provider = Auth::provider($context, $auth);

		if (!$provider instanceof UserAuthProvider) {
			throw new ForbiddenException(null, [
				'_reason'   => 'Invalid auth provider.',
				'_auth_ref' => $auth_ref,
			]);
		}

		$state = $provider->getState();

		if (AuthState::AUTHORIZED !== $state) {
			throw new ForbiddenException(null, [
				'_reason'   => 'Referenced auth is not authorized.',
				'_auth_ref' => $auth_ref,
			]);
		}

		$auth_user = $provider->getUser();

		if ($auth_user->getID() !== $user->getID()) {
			throw new ForbiddenException(null, [
				'_reason'   => 'Referenced auth is not for this user.',
				'_auth_ref' => $auth_ref,
				'_uid'      => $uid,
				'_auth_uid' => $auth_user->getID(),
			]);
		}

		$this->user     = $auth_user;
		$this->provider = $provider;

		return $auth;
	}
}
