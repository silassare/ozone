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
	protected ?string $session_id        = null;
	protected ?Session $session;
	protected OZUser $user;

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

		// get session id from cookie
		// if session id is not found in cookie
		// or session is not found in database
		// just ignore it we will create a new session later
		$sid = $request->getCookieParam(Session::cookieName());
		if ($sid && null !== Session::findSessionByID($sid)) {
			$this->session_id = $sid;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function ask(): void
	{
		$this->startCurrentOrNewSession();
	}

	/**
	 * {@inheritDoc}
	 */
	public function authenticate(): void
	{
		$this->startCurrentOrNewSession();
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
					// maybe the user was invalidated after session started
					// we should normally clear all sessions for user when invalidating the user
					'_help'   => 'Logic error: all user session should be cleared when user is invalidated.',
					'_uid'    => $user->getID(),
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
		return $this->startCurrentOrNewSession();
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
		return $this->startCurrentOrNewSession()
			->id();
	}

	/**
	 * Start current or new session.
	 *
	 * @return \OZONE\Core\Sessions\Session
	 */
	protected function startCurrentOrNewSession(): Session
	{
		if (!isset($this->session)) {
			$this->session = new Session($this->ri->getContext());

			$this->session->start($this->session_id);

			$this->session_id = $this->session->id();
		}

		return $this->session;
	}
}
