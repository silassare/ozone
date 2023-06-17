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
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Db\OZSession;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Cookies;
use OZONE\Core\OZone;
use OZONE\Core\Utils\Random;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Session.
 */
final class Session implements BootHookReceiverInterface
{
	private const SESSION_ID_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	private string $request_source_key;

	private ?SessionState $state = null;

	private ?OZSession $sess_entry = null;

	private bool $started = false;

	private bool $delete_cookie = false;

	/**
	 * Session constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 */
	public function __construct(protected Context $context)
	{
		$this->request_source_key = $this->context->getUserIP();
	}

	/**
	 * Session destructor.
	 */
	public function __destruct()
	{
		unset($this->context, $this->state, $this->sess_entry);
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
	 * Returns session attached user ID.
	 */
	public function attachedUserID(): ?string
	{
		$this->assertSessionStarted();

		return $this->sess_entry->getUserID();
	}

	/**
	 * Returns session ID.
	 *
	 * @return string
	 */
	public function id(): string
	{
		$this->assertSessionStarted();

		return $this->sess_entry->getID();
	}

	/**
	 * To checks if session is started.
	 *
	 * @return bool
	 */
	public function isStarted(): bool
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
			$this->sess_entry = self::findSessionByID($session_id);
		}

		if (!$this->sess_entry) {
			$sid = Keys::newSessionID();

			$this->sess_entry = new OZSession();
			$this->sess_entry->setID($sid)
				->setRequestSourceKey($this->request_source_key);
		}

		$this->state         = SessionState::getInstance($this->sess_entry);
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

		if (!$this->sess_entry->isNew()) {
			self::delete($this->sess_entry->getID());
		}

		$this->sess_entry    = null;
		$this->state         = null;
		$this->started       = false;
		$this->delete_cookie = true;

		return $this;
	}

	/**
	 * Attach user to this session.
	 *
	 * @param \OZONE\Core\Db\OZUser $user
	 *
	 * @return $this
	 */
	public function attachUser(OZUser $user): self
	{
		$this->assertSessionStarted();

		// it may be a new session, so we save first
		$uid              = $user->getID();
		$sid              = $this->sess_entry->getID();
		$session_owner_id = $this->sess_entry->getUserID();

		if ($session_owner_id && $uid !== $session_owner_id) {
			throw new RuntimeException('OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER', [
				'user_id'     => $uid,
				'_session_id' => $sid,
				'_owner'      => [
					'session_owner_id' => $session_owner_id,
				],
			]);
		}

		$this->sess_entry->setUserID($uid);

		return $this;
	}

	/**
	 * Gets the session data store.
	 *
	 * @return \OZONE\Core\Sessions\SessionState
	 */
	public function state(): SessionState
	{
		$this->assertSessionStarted();

		return $this->state;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		ResponseHook::handle(static function (ResponseHook $ev) {
			$context = $ev->getContext();
			if ($context->hasSession()) {
				$context->session()
					->responseReady();
			}
		}, Event::RUN_LAST);

		FinishHook::handle(static function () {
			self::gc();
		}, Event::RUN_LAST);
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
		if (!self::isSessionIdLike($sid)) {
			return null;
		}

		$factory = function () use ($sid) {
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
	 */
	private function responseReady(): void
	{
		$response    = $this->context->getResponse();
		$cookie_name = self::cookieName();

		if ($this->started) {
			$this->save();
			$params          = $this->getCookieParams();
			$params['value'] = $this->sess_entry->getID();
			$cookie          = new Cookies();
			$cookie->set($cookie_name, $params);

			$response = $response->withHeader('Set-Cookie', $cookie->toHeaders());
		}

		if ($this->delete_cookie) {
			$params            = $this->getCookieParams();
			$params['expires'] = \time() - 86400;
			$params['value']   = '';
			$cookie            = new Cookies();
			$cookie->set($cookie_name, $params);

			$response = $response->withHeader('Set-Cookie', $cookie->toHeaders());
		}

		$this->context->setResponse($response);
	}

	/**
	 * Assert if the session started.
	 */
	private function assertSessionStarted(): void
	{
		if (!$this->started || !isset($this->sess_entry, $this->state, $this->client)) {
			throw new RuntimeException('Session not yet started.');
		}
	}

	/**
	 * Save session data.
	 */
	private function save(): void
	{
		$sid = $this->sess_entry->getID();

		try {
			$expire = \time() + self::lifetime();

			$data = $this->state->getData();

			$this->sess_entry->setData($data)
				->setExpire($expire)
				->setLastSeen(\time())
				->setUpdatedAT(\time())
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save session.', ['_session_id' => $sid], $t);
		}
	}

	/**
	 * Gets cookie params to use for this request.
	 *
	 * @return array
	 */
	private function getCookieParams(): array
	{
		$cfg_domain   = Settings::get('oz.cookie', 'OZ_COOKIE_DOMAIN');
		$cfg_path     = Settings::get('oz.cookie', 'OZ_COOKIE_PATH');
		$cfg_lifetime = Settings::get('oz.cookie', 'OZ_COOKIE_LIFETIME');
		$samesite     = Settings::get('oz.cookie', 'OZ_COOKIE_SAMESITE');

		$context  = $this->context;
		$request  = $context->getRequest();
		$uri      = $request->getUri();
		$secure   = 'https' === $uri->getScheme();
		$lifetime = \max($cfg_lifetime, self::lifetime());
		$domain   = (empty($cfg_domain) || 'self' === $cfg_domain) ? $context->getHost() : $cfg_domain;
		$path     = (empty($cfg_path) || 'self' === $cfg_path) ? $uri->getBasePath() : $cfg_path;

		$path = empty($path) ? '/' : $path;

		return [
			'expires'  => \time() + $lifetime,
			'path'     => $path,
			'domain'   => $domain,
			'httponly' => true, // prevent access from javascript
			'secure'   => $secure,
			'samesite' => $samesite, // None, Lax or Strict
		];
	}

	/**
	 * Delete all expired sessions.
	 */
	private static function gc(): void
	{
		if (Random::bool() && OZone::hasDbAccess()) {
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
