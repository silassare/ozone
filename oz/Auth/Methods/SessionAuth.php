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

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Auth\Interfaces\StatefulAuthMethodInterface;
use OZONE\Core\Auth\StatefulAuthStore;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Users\Users;

/**
 * Class SessionAuth.
 *
 * @psalm-suppress RedundantPropertyInitializationCheck
 */
class SessionAuth implements StatefulAuthMethodInterface
{
	protected AuthMethodType $type = AuthMethodType::SESSION;
	protected ?string $session_id  = null;
	protected ?Session $session;
	protected OZUser $user;

	/**
	 * SessionAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm) {}

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
	 *
	 * @throws ForbiddenException
	 */
	public function ask(): void
	{
		$this->startCurrentOrNewSession();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function authenticate(): void
	{
		$this->startCurrentOrNewSession();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws UnverifiedUserException
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
	 * @return AuthAccessRightsInterface
	 *
	 * @throws ForbiddenException
	 * @throws UnverifiedUserException
	 */
	public function accessRights(): AuthAccessRightsInterface
	{
		return $this->user()
			->getAccessRights();
	}

	/**
	 * Returns the session instance.
	 *
	 * @throws ForbiddenException
	 */
	public function session(): Session
	{
		return $this->startCurrentOrNewSession();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function store(): StatefulAuthStore
	{
		return $this->session()
			->store();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function stateID(): string
	{
		return $this->startCurrentOrNewSession()
			->id();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function persist(): void
	{
		$this->session()
			->responseReady();
	}

	/**
	 * Start current or new session.
	 *
	 * @return Session
	 *
	 * @throws ForbiddenException
	 */
	protected function startCurrentOrNewSession(): Session
	{
		$context    = $this->ri->getContext();
		$source_key = $context->getUserIP();
		if (!isset($this->session)) {
			$this->session = new Session($context, $source_key);

			$this->session->start($this->session_id);

			$this->session_id = $this->session->id();
		} elseif ($this->session->sourceKey() !== $source_key) {
			$force_same_source = (bool) Settings::get('oz.sessions', 'OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE');

			if ($force_same_source) {
				if ($this->session->attachedUserID()) {
					// TODO: we may inform the user about this
					// we can't just restart the session
					// because the user may be doing something important
					// and we don't want to interrupt him
					// so we just throw an exception
					throw new ForbiddenException(null, [
						'_reason' => 'Session source key mismatch.',
						'_help'   => 'This is possible session hijacking attempt.'
							. ' It may also be that the user is using a proxy, a VPN'
							. ' or his IP address has changed, usual under mobile network.',
					]);
				}
				$this->session->restart();
			}
		}

		return $this->session;
	}
}
