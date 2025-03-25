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

use OZONE\Core\App\Context;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Db\OZSession;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Cookie;
use OZONE\Core\Http\Cookies;
use OZONE\Core\OZone;
use OZONE\Core\Utils\Random;
use PHPUtils\Events\Event;
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
	 * Session constructor.
	 *
	 * @param Context $context
	 * @param string  $request_source_key
	 */
	public function __construct(protected Context $context, private readonly string $request_source_key) {}

	/**
	 * Session destructor.
	 */
	public function __destruct()
	{
		unset($this->context, $this->state, $this->session_entry);
	}

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
		$this->assertSessionStarted();

		return $this->session_entry->getID();
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
	 *
	 * @return $this
	 */
	public function start(?string $session_id = null): self
	{
		if ($session_id) {
			$this->session_entry = self::findSessionByID($session_id);
		}

		if (!$this->session_entry) {
			$session_id = Keys::newSessionID();

			$this->session_entry = new OZSession();
			$this->session_entry->setID($session_id)
				->setRequestSourceKey($this->request_source_key);
		}

		$data                = $this->session_entry->getData()->getData();
		$this->state         = StatefulAuthenticationMethodStore::getInstance($session_id, $data);
		$this->started       = true;
		$this->delete_cookie = false;

		return $this;
	}

	/**
	 * Restart the session.
	 *
	 * @return $this
	 */
	public function restart(): self
	{
		$this->assertSessionStarted();

		return $this->destroy()
			->start();
	}

	/**
	 * Destroy the session.
	 *
	 * @return $this
	 */
	public function destroy(): self
	{
		$this->assertSessionStarted();

		if (!$this->session_entry->isNew()) {
			self::delete($this->session_entry->getID());
		}

		$this->session_entry = null;
		$this->state         = null;
		$this->started       = false;
		$this->delete_cookie = true;

		return $this;
	}

	/**
	 * Returns session attached user.
	 */
	public function attachedAuthUser(): ?AuthUserInterface
	{
		$this->assertSessionStarted();

		$c_type = $this->session_entry->getOwnerType();
		$c_id   = $this->session_entry->getOwnerID();

		return $c_type && $c_id ? AuthUsers::identify($c_type, $c_id) : null;
	}

	/**
	 * Attach user to this session.
	 *
	 * @param AuthUserInterface $user
	 *
	 * @return $this
	 */
	public function attachAuthUser(AuthUserInterface $user): self
	{
		$this->assertSessionStarted();

		$current_user = $this->attachedAuthUser();

		$sid = $this->session_entry->getID();

		if ($current_user && !AuthUsers::same($current_user, $user)) {
			throw new RuntimeException('OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER', [
				'_session_id'    => $sid,
				'_owner_current' => AuthUsers::selector($current_user),
				'_owner_new'     => AuthUsers::selector($user),
			]);
		}

		$this->session_entry->setOwnerID($user->getAuthIdentifier());
		$this->session_entry->setOwnerType($user->getAuthUserTypeName());

		return $this;
	}

	/**
	 * Detach the current user from the session.
	 *
	 * @return $this
	 */
	public function detachAuthUser(): self
	{
		$this->assertSessionStarted();

		$this->session_entry->setOwnerID(null);
		$this->session_entry->setOwnerType(null);

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

		return $this->state;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		FinishHook::listen(static function () {
			self::gc();
		}, Event::RUN_LAST);

		if (\class_exists(OZSession::class)) {
			DbReadyHook::listen(static function () {
				OZSession::crud()
					->onBeforePKColumnWrite(static fn () => true);
			});
		}
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

		$factory = static function () use ($sid) {
			try {
				$sqb = new OZSessionsQuery();

				$result = $sqb->whereIdIs($sid)
					->whereIsValid()
					->find(1);

				$item = $result->fetchClass();

				if ($item && $item->getExpire() > \time()) {
					return $item;
				}
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load session with session ID.', ['_session_id' => $sid], $t);
			}

			return null;
		};

		return CacheManager::runtime(__METHOD__)
			->factory($sid, $factory)
			->get();
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

		/** @var null|Cookie $cookie */
		$cookie = null;

		if ($this->started) {
			$this->save();
			$cookie          = Cookie::create($this->context, $session_cookie_name, $this->session_entry->getID());
			$cookie->expires = \time() + self::lifetime();
		}

		if ($this->delete_cookie) {
			$cookie = Cookie::create($this->context, $session_cookie_name)->drop();
		}

		if ($cookie) {
			$cookies_jar = new Cookies();
			$cookies_jar->add($cookie);
			$response = $response->withHeader('Set-Cookie', $cookies_jar->toResponseHeaders());
		}

		$this->context->setResponse($response);
	}

	/**
	 * Assert if the session started.
	 */
	private function assertSessionStarted(): void
	{
		if (!$this->started || !isset($this->session_entry, $this->state)) {
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
		$sid = $this->session_entry->getID();

		try {
			$now    = \time();
			$expire = $now + self::lifetime();

			$data = $this->state->getData();

			$this->session_entry->setData($data)
				->setExpire($expire)
				->setLastSeen($now)
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
		if (Random::bool() && OZone::hasDbInstalled()) {
			try {
				$s_table = new OZSessionsQuery();
				$s_table->whereExpireIsLte(\time())
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
