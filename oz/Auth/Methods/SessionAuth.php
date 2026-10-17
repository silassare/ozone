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

use ArrayObject;
use Override;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Events\SessionHijackingDetected;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\Hooks\Events\ResponseHook;
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
	 * {@inheritDoc}
	 */
	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self($ri, $realm);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	#[Override]
	public function ask(): void
	{
		$this->session();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	#[Override]
	public function authenticate(): void
	{
		$this->session();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws UnauthenticatedException
	 */
	#[Override]
	public function user(): AuthUserInterface
	{
		if (!isset($this->user)) {
			$user = $this->session()
				->attachedAuthUser();

			if (!$user) {
				throw new UnauthenticatedException(null, [
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
	 * @return AccessRightsInterface
	 *
	 * @throws ForbiddenException
	 * @throws UnauthenticatedException
	 */
	#[Override]
	public function getAccessRights(): AccessRightsInterface
	{
		return $this->user()->getAuthUserDataStore()->getAuthUserAccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isScoped(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
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
	#[Override]
	public function detachAuthUser(): void
	{
		$this->session()
			->detachAuthUser();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
		if (isset($this->session)) {
			return $this->session;
		}

		$context = $this->ri->getContext();

		$this->session = new Session($context, self::sourceKeyOf($context));

		// Not id() afterwards: asking for the ID binds something to it and keeps the session.
		$this->session->start($this->session_id);

		// The cookie reached us from another source than the one the session was opened from. Checked
		// once, here: the source of a request cannot change while it is served.
		if (
			$this->session->sourceChanged()
			&& (bool) Settings::get('oz.sessions', 'OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE')
		) {
			$user = $this->session->attachedAuthUser();

			if (null !== $user) {
				(new SessionHijackingDetected(
					$context,
					$user,
					$this->session->peekID(),
					$this->session->openedSourceKey(),
					$this->session->sourceKey()
				))->dispatch();

				// The refusal must take the cookie with it.
				$this->dropCookieOnResponse();

				throw new ForbiddenException('OZ_SESSION_HIJACKING_DETECTED', [
					'_reason' => 'Session source key mismatch.',
					'_help'   => 'This is possible session hijacking attempt.'
						. ' It may also be that the user is using a proxy, a VPN'
						. ' or his IP address has changed, usual under mobile network.',
				]);
			}

			// Nothing to steal yet: the request goes on with a session of its own.
			$this->session->restart();
		}

		return $this->session;
	}

	/**
	 * Drops the cookie of the refused session as the response goes out.
	 *
	 * Without this the refusal is a dead end. The cookie is still on the client, so every later
	 * request carries the same session ID and is refused in turn: the home page, the sign-out route
	 * and the sign-in form, until the session expires. The only way back is for the user to clear
	 * their cookies by hand, which is not something a browser offers to do on a phone -- and the
	 * source key changes for honest reasons too, a browser updating itself mid-session among them.
	 *
	 * The stored session is left alone ({@see Session::abandon()}): the request that is refused is
	 * precisely the one that may not act on it, so it may not end it either. A client replaying the
	 * cookie of a session it cannot use would otherwise sign its owner out by sending it.
	 *
	 * Deferred to the response because this runs inside `authenticate()`: the throw that follows
	 * leaves `ContextAuth` without a method, so nothing persists the session afterwards and
	 * `responseReady()` is called here instead. The listeners release each other, since the
	 * registry outlives a request in a worker: the response hook when it runs, the end of the
	 * request when it never did -- the request unwound without responding, or it was a sub-request
	 * answering its parent.
	 */
	private function dropCookieOnResponse(): void
	{
		$session = $this->session;

		if (null === $session) {
			return;
		}

		/** @var ArrayObject<int, \Closure():bool> $detachers */
		$detachers = new ArrayObject();

		$release = static function () use ($detachers): void {
			foreach ($detachers as $detach) {
				$detach();
			}

			$detachers->exchangeArray([]);
		};

		$detachers->append(ResponseHook::listen(static function () use ($session, $release): void {
			$release();

			$session->abandon()
				->responseReady();
		}));

		$detachers->append(EndRequestHook::listen($release));
	}

	/**
	 * The key identifying where the current request comes from.
	 *
	 * Stored with a session when it is opened (`oz_sessions`.`request_source_key`) and compared with
	 * it on every later request, so it is prefixed rather than raw: a key always says which
	 * `OZ_SESSION_SOURCE_KEY` produced it, and never ends up shorter than the column accepts.
	 */
	private static function sourceKeyOf(Context $context): string
	{
		return match (Settings::get('oz.sessions', 'OZ_SESSION_SOURCE_KEY')) {
			'user_ip' => 'User-IP-' . ($context->getUserIP() ?? 'unknown'),
			default   => 'User-Agent-Hash-' . Hasher::hash64($context->getRequest()->getHeaderLine('User-Agent')),
		};
	}
}
