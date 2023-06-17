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

use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Auth\Interfaces\SessionBasedAuthMethodInterface;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Sessions\SessionState;
use OZONE\Core\Users\Users;

/**
 * Class SessionAuth.
 *
 * @psalm-suppress RedundantPropertyInitializationCheck
 */
class SessionAuth implements SessionBasedAuthMethodInterface
{
	protected AuthMethodType $type       = AuthMethodType::SESSION;
	protected ?string        $session_id = null;
	protected ?Session       $session;
	protected OZUser         $user;

	/**
	 * SessionAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm)
	{
	}

	/**
	 * SessionAuth destructor.
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
	 * {@inheritDoc}
	 */
	public function satisfied(): bool
	{
		$request = $this->ri->getContext()
			->getRequest();

		// 1) if we have a cookie, we deal with it and only with it
		//    - if the cookie is not valid ignore any other method
		// 2) else we can use token header if enabled and provided

		$sid = $request->getCookieParam(Session::cookieName());

		if ($sid && null !== Session::findSessionByID($sid)) {
			$this->session_id = $sid;

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function ask(): void
	{
		$this->currentOrNewSession();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 */
	public function authenticate(): void
	{
		if (!$this->session_id) {
			throw new ForbiddenException(null, [
				'_reason' => 'Missing session id.',
				'_help'   => 'Please login first.',
			]);
		}

		$entry = Session::findSessionByID($this->session_id);

		if (!$entry) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid session id.',
				'_help'   => 'Please login first.',
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public function user(): OZUser
	{
		if (!isset($this->user)) {
			$uid = $this->session()
				->attachedUserID();
			if (!$uid || !($user = Users::identify($uid))) {
				throw new UnverifiedUserException(null, [
					'_reason' => 'User not authenticated.',
					'_help'   => 'Please login first.',
				]);
			}

			if (!$user->isValid()) {
				Users::forceUserLogoutOnAllActiveSessions($user->getID());

				throw new ForbiddenException(null, [
					'_reason' => 'User not valid.',
					// may be the user was invalidated after session started
					// we should normally clear all sessions for user when invalidating the user
					'_help'   => 'Logic error.',
				]);
			}

			$this->user = $user;
		}

		return $this->user;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\UnverifiedUserException
	 */
	public function accessRights(): AuthAccessRightsInterface
	{
		return $this->user()
			->getAccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	public function session(): Session
	{
		return $this->currentOrNewSession();
	}

	/**
	 * {@inheritDoc}
	 */
	public function state(): SessionState
	{
		return $this->session()
			->state();
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string
	{
		return $this->currentOrNewSession()
			->id();
	}

	/**
	 * Start current or new session.
	 *
	 * @return \OZONE\Core\Sessions\Session
	 */
	protected function currentOrNewSession(): Session
	{
		if (!$this->session) {
			$this->session = new Session($this->ri->getContext());

			$this->session->start($this->session_id);

			$this->session_id = $this->session->id();
		}

		return $this->session;
	}
}
