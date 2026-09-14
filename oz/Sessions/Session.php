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

namespace OZONE\Core\Sessions;

use Gobl\ORM\ORMOptions;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Db\OZSession;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Cookie;
use OZONE\Core\Http\Cookies;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\OZone;
use OZONE\Core\Stores\CacheRegistry;
use Throwable;

/**
 * Class Session.
 *
 * @internal
 */
final class Session implements BootHookReceiverInterface
{
	private const SESSION_ID_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	private ?StatefulAuthenticationMethodStore $state = null;

	private ?OZSession $session_entry = null;

	private bool $started = false;

	private bool $delete_cookie = false;

	/**
	 * Whether the session ID was handed out ({@see self::id()}): a CSRF token, a rate-limit key, a
	 * form-resume scope is bound to it, so the session has to exist on the next request.
	 */
	private bool $id_bound = false;

	/**
	 * The store's data when the session started, to tell a used session from an untouched one.
	 */
	private array $initial_data = [];

	/**
	 * Whether the session did not exist before this request.
	 *
	 * A new session has no `OZSession` entity until it is saved, only its ID (made when first needed)
	 * and its owner: an anonymous request, which never keeps its session, then builds no entity at
	 * all -- and so does not initialize the database either.
	 */
	private bool $is_new = false;

	private ?string $new_id = null;

	private ?string $new_owner_type = null;

	private ?string $new_owner_id = null;

	/**
	 * Distinguishes the data stores of successive new sessions in one request (see start()).
	 */
	private static int $new_sessions = 0;

	/**
	 * Session constructor.
	 *
	 * @param Context $context
	 * @param string  $request_source_key
	 */
	public function __construct(private Context $context, private readonly string $request_source_key) {}

	/**
	 * Returns session lifetime in seconds from settings.
	 *
	 * @return int
	 */
	public static function lifetime(): int
	{
		return (int) Settings::get('oz.sessions', 'OZ_SESSION_LIFE_TIME');
	}

	/**
	 * Returns session cookie name from setting.
	 */
	public static function cookieName(): string
	{
		return Settings::get('oz.sessions', 'OZ_SESSION_COOKIE_NAME');
	}

	/**
	 * Returns session ID.
	 *
	 * @return string
	 */
	public function id(): string
	{
		// Whoever asks for the ID binds something to it: the session is kept.
		$this->id_bound = true;

		return $this->ensureID();
	}

	/**
	 * Returns session source key.
	 *
	 * @return string
	 */
	public function sourceKey(): string
	{
		return $this->request_source_key;
	}

	/**
	 * To checks if session has started.
	 *
	 * @return bool
	 */
	public function hasStarted(): bool
	{
		return $this->started;
	}

	/**
	 * Start the session.
	 */
	public function start(?string $session_id = null): static
	{
		$entry = $session_id ? self::findSessionByID($session_id) : null;

		$this->session_entry  = $entry;
		$this->is_new         = null === $entry;
		$this->new_id         = null;
		$this->new_owner_type = null;
		$this->new_owner_id   = null;

		if (null !== $entry) {
			$data      = $entry->getData()->getData();
			$state_key = $entry->getID();
		} else {
			// No entity and no ID yet: see $is_new. ensureID() and save() make them when kept.
			$data      = [];
			$state_key = 'new:' . ++self::$new_sessions;
		}

		$this->state         = StatefulAuthenticationMethodStore::getInstance($state_key, $data);
		$this->initial_data  = $this->state->getData();
		$this->id_bound      = false;
		$this->started       = true;
		$this->delete_cookie = false;

		return $this;
	}

	/**
	 * Restart the session.
	 */
	public function restart(): static
	{
		$this->assertSessionStarted();

		return $this->destroy()
			->start();
	}

	/**
	 * Destroy the session.
	 */
	public function destroy(): static
	{
		$this->assertSessionStarted();

		if (!$this->is_new && null !== $this->session_entry) {
			self::delete($this->session_entry->getID());
		}

		$this->session_entry  = null;
		$this->new_id         = null;
		$this->new_owner_type = null;
		$this->new_owner_id   = null;
		$this->state          = null;
		$this->started        = false;
		$this->delete_cookie  = true;

		return $this;
	}

	/**
	 * Returns session attached user.
	 */
	public function attachedAuthUser(): ?AuthUserInterface
	{
		$this->assertSessionStarted();

		if ($this->is_new) {
			$c_type = $this->new_owner_type;
			$c_id   = $this->new_owner_id;
		} else {
			$c_type = $this->session_entry?->getOwnerType();
			$c_id   = $this->session_entry?->getOwnerID();
		}

		return $c_type && $c_id ? AuthUsers::identify($c_type, $c_id) : null;
	}

	/**
	 * Attach user to this session.
	 *
	 * @param AuthUserInterface $user
	 */
	public function attachAuthUser(AuthUserInterface $user): static
	{
		$this->assertSessionStarted();

		$current_user = $this->attachedAuthUser();

		$sid = $this->ensureID();

		if ($current_user && !AuthUsers::same($current_user, $user)) {
			throw new RuntimeException('OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER', [
				'_session_id'    => $sid,
				'_owner_current' => AuthUsers::selector($current_user),
				'_owner_new'     => AuthUsers::selector($user),
			]);
		}

		$this->setOwner($user->getAuthUserType(), $user->getAuthIdentifier());

		return $this;
	}

	/**
	 * Detach the current user from the session.
	 */
	public function detachAuthUser(): static
	{
		$this->assertSessionStarted();

		$this->setOwner(null, null);

		return $this;
	}

	/**
	 * Gets the session data store.
	 *
	 * @return StatefulAuthenticationMethodStore
	 */
	public function store(): StatefulAuthenticationMethodStore
	{
		$this->assertSessionStarted();

		// assertSessionStarted() has already ruled out null, for both the store and the entry.
		return $this->state;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		GarbageCollector::register('oz:sessions', self::gc(...));

		// The class is checked when the database is ready, not here: loading a generated ORM class
		// at boot cost every request, including the ones that never touch a session.
		DbReadyHook::listen(static function (): void {
			if (\class_exists(OZSession::class)) {
				OZSession::crud()
					->onBeforePKColumnWrite(static fn () => true);
			}
		});
	}

	/**
	 * Find session by ID.
	 *
	 * @param string $sid
	 *
	 * @return null|OZSession
	 */
	public static function findSessionByID(string $sid): ?OZSession
	{
		if (!OZone::hasDbInstalled() || !self::isSessionIdLike($sid)) {
			return null;
		}

		$factory = static function () use ($sid): ?OZSession {
			try {
				$sqb = new OZSessionsQuery();

				$result = $sqb->whereIdIs($sid)
					->whereIsValid()
					->find(ORMOptions::makePaginated(1));

				$item = $result->fetchClass();

				if ($item && $item->getExpireAT() > \time()) {
					return $item;
				}
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load session with session ID.', ['_session_id' => $sid], $t);
			}

			return null;
		};

		return CacheRegistry::runtime(__METHOD__)->remember($sid, $factory);
	}

	/**
	 * Response ready hook.
	 *
	 * @internal
	 */
	public function responseReady(): void
	{
		$response            = $this->context->getResponse();
		$session_cookie_name = self::cookieName();

		if ($this->started && !$this->isKept()) {
			// Nothing used this new session: no row, no cookie, so an anonymous request costs no write.
			// A cookie the request carried for a session that no longer exists is dropped, so the
			// client stops sending it.
			if (null !== $this->context->getRequest()->getCookieParam($session_cookie_name)) {
				$this->context->setResponse((new Cookies())
					->add(Cookie::create($this->context, $session_cookie_name)->drop())
					->applyTo($response));
			}

			return;
		}

		/** @var null|Cookie $cookie */
		$cookie = null;

		if ($this->started) {
			$this->save();
			$cookie          = Cookie::create($this->context, $session_cookie_name, $this->ensureID());
			$cookie->expires = \time() + self::lifetime();
		}

		if ($this->delete_cookie) {
			$cookie = Cookie::create($this->context, $session_cookie_name)->drop();
		}

		$cookies_jar = new Cookies();

		if ($cookie) {
			$cookies_jar->add($cookie);
		}

		$csrf_cookie = $this->csrfCookie();

		if ($csrf_cookie) {
			$cookies_jar->add($csrf_cookie);
		}

		// Added to the response's `Set-Cookie` lines, never in place of them: a handler's cookies stay.
		$this->context->setResponse($cookies_jar->applyTo($response));
	}

	/**
	 * The session's ID, made when first needed: a new session has none until it is kept.
	 */
	private function ensureID(): string
	{
		$this->assertSessionStarted();

		if (!$this->is_new && null !== $this->session_entry) {
			return $this->session_entry->getID();
		}

		return $this->new_id ??= Keys::newSessionID();
	}

	/**
	 * Sets the owner, on the entity of an existing session or in memory for a new one.
	 */
	private function setOwner(?string $type, ?string $id): void
	{
		if ($this->is_new) {
			$this->new_owner_type = $type;
			$this->new_owner_id   = $id;
		} else {
			$this->session_entry?->setOwnerType($type);
			$this->session_entry?->setOwnerID($id);
		}
	}

	/**
	 * Whether the session has to outlive this request: it existed already (its expiry slides), its ID
	 * was handed out, a user is attached, or its data changed since it started.
	 */
	private function isKept(): bool
	{
		return !$this->is_new
			|| $this->id_bound
			|| null !== $this->new_owner_id
			|| $this->store()->getData() !== $this->initial_data;
	}

	/**
	 * The cookie handing the session's CSRF token to scripts (`OZ_CSRF_COOKIE_NAME`, not
	 * HttpOnly), unless the request already carries a valid one; dropped with the session.
	 */
	private function csrfCookie(): ?Cookie
	{
		$name = CSRF::cookieName();

		if (null === $name) {
			return null;
		}

		if ($this->delete_cookie) {
			return Cookie::create($this->context, $name)->drop();
		}

		if (!$this->started) {
			return null;
		}

		$csrf    = new CSRF($this->context, RequestScope::STATE, $this->ensureID());
		$current = $this->context->getRequest()->getCookieParam($name);

		if (\is_string($current) && $csrf->isValid($current)) {
			return null;
		}

		$cookie           = Cookie::create($this->context, $name, $csrf->generateToken());
		$cookie->httponly = false;
		$cookie->expires  = \time() + self::lifetime();

		return $cookie;
	}

	/**
	 * Asserts that the session started.
	 */
	private function assertSessionStarted(): void
	{
		if (!$this->started || null === $this->state) {
			throw new RuntimeException('Session not yet started.');
		}
	}

	/**
	 * Save session data.
	 */
	private function save(): void
	{
		if (!OZone::hasDbInstalled()) {
			return;
		}

		$sid = $this->ensureID();

		if ($this->is_new) {
			// The entity of a new session exists only once it is kept.
			$this->session_entry ??= (new OZSession())
				->setID($sid)
				->setRequestSourceKey($this->request_source_key);

			$this->session_entry->setOwnerType($this->new_owner_type)
				->setOwnerID($this->new_owner_id);
		}

		$entry = $this->session_entry;

		if (null === $entry) {
			throw new RuntimeException('Session not yet started.');
		}

		try {
			$now    = \time();
			$expire = $now + self::lifetime();

			$data = $this->store()->getData();

			$entry->setData($data)
				->setExpireAT($expire)
				->setLastSeenAT($now)
				->setUpdatedAT($now)
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save session.', ['_session_id' => $sid], $t);
		}
	}

	/**
	 * Delete all expired sessions.
	 */
	private static function gc(): void
	{
		if (OZone::hasDbInstalled()) {
			try {
				$s_table = new OZSessionsQuery();
				$s_table->whereExpireAtIsLte(\time())
					->delete()
					->execute();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to delete expired session.', null, $t);
			}
		}
	}

	/**
	 * Checks for session id string.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private static function isSessionIdLike(mixed $value): bool
	{
		return \is_string($value) && \preg_match(self::SESSION_ID_REG, $value);
	}

	/**
	 * Delete session from database.
	 *
	 * @param string $sid
	 */
	private static function delete(string $sid): void
	{
		if (!OZone::hasDbInstalled()) {
			return;
		}

		try {
			$s_table = new OZSessionsQuery();

			$s_table->whereIdIs($sid)
				->delete()
				->execute();
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_SESSION_DELETION_FAILED', ['_session_id' => $sid], $t);
		}
	}
}
