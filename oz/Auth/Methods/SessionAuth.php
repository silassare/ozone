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
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Utils\Hasher;

/**
 * Class SessionAuth.
 *
 * @psalm-suppress RedundantPropertyInitializationCheck
 */
class SessionAuth implements AuthenticationMethodStatefulInterface
{
	protected AuthenticationMethodScheme $type = AuthenticationMethodScheme::SESSION;
	protected ?string $session_id              = null;
	protected ?Session $session;
	protected AuthUserInterface $user;

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
		$this->session();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function authenticate(): void
	{
		$this->session();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws UnverifiedUserException
	 */
	public function user(): AuthUserInterface
	{
		if (!isset($this->user)) {
			$user = $this->session()
				->attachedAuthUser();

			if (!$user) {
				throw new UnverifiedUserException(null, [
					'_reason' => 'User not authenticated.',
					'_help'   => 'Please login first.',
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
	public function getAccessRights(): AuthAccessRightsInterface
	{
		return $this->user()->getAuthUserDataStore()->getAuthUserAccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isScoped(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function store(): StatefulAuthenticationMethodStore
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
		return $this->session()
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
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function destroy(): void
	{
		$this->session()
			->destroy();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function renew(): void
	{
		$this->session()
			->restart();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function attachAuthUser(AuthUserInterface $user): void
	{
		$this->session()
			->attachAuthUser($user);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	public function detachAuthUser(): void
	{
		$this->session()
			->detachAuthUser();
	}

	/**
	 * {@inheritDoc}
	 */
	public function lifetime(): int
	{
		return Session::lifetime();
	}

	/**
	 * Start current or new session.
	 *
	 * @return Session
	 *
	 * @throws ForbiddenException
	 */
	protected function session(): Session
	{
		$context            = $this->ri->getContext();
		$session_source_key = Settings::get('oz.sessions', 'OZ_SESSION_SOURCE_KEY');
		$source_key_value   = match ($session_source_key) {
			'user_agent' => 'User-Agent-Hash-' . Hasher::hash64($context->getRequest()->getHeaderLine('User-Agent')),
			default      => $context->getUserIP(),
		};

		if (!isset($this->session)) {
			$this->session = new Session($context, $source_key_value);

			$this->session->start($this->session_id);

			$this->session_id = $this->session->id();
		} elseif ($this->session->sourceKey() !== $source_key_value) {
			$force_same_source = (bool) Settings::get('oz.sessions', 'OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE');

			if ($force_same_source) {
				if ($this->session->attachedAuthUser()) {
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
