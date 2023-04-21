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

namespace OZONE\OZ\Sessions;

use OZONE\OZ\Cache\CacheManager;
use OZONE\OZ\Core\ClientHelper;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\OZClient;
use OZONE\OZ\Db\OZSession;
use OZONE\OZ\Db\OZSessionsQuery;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Hooks\Events\FinishHook;
use OZONE\OZ\Hooks\Events\ResponseHook;
use OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\OZ\Http\Cookies;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Session.
 */
final class Session implements BootHookReceiverInterface
{
	public const SESSION_ID_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	public const SESSION_TOKEN_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	private ?OZClient $client = null;

	private ?SessionDataStore $store = null;

	private ?OZSession $sess_entry = null;

	private string $cookie_name;

	private bool $started = false;

	private bool $delete_cookie = false;

	/**
	 * Session constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(protected Context $context)
	{
		$this->cookie_name = Configs::get('oz.config', 'OZ_API_SESSION_ID_NAME');
	}

	/**
	 * Session destructor.
	 */
	public function __destruct()
	{
		unset($this->context, $this->client, $this->store, $this->sess_entry);
	}

	/**
	 * Returns request client.
	 *
	 * @return \OZONE\OZ\Db\OZClient
	 */
	public function getClient(): OZClient
	{
		$this->assertSessionStarted();

		return $this->client;
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
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 */
	public function start(): self
	{
		if (!$this->client) {
			$this->init();
		}

		if (!$this->sess_entry) {
			$sid   = Hasher::genSessionID();
			$token = Hasher::genSessionToken();

			$this->sess_entry = new OZSession();
			$this->sess_entry->setID($sid)
				->setClientID($this->client->getID())
				->setToken($token);
		}

		$this->store         = SessionDataStore::getInstance($this->sess_entry);
		$this->started       = true;
		$this->delete_cookie = false;

		if ($this->client->getUserID()) {
			$this->context->getUsersManager()
				->tryLogOnAsApiClientOwner();
		}

		return $this;
	}

	/**
	 * Restart the session.
	 *
	 * @return $this
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
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
			self::removeSession($this->sess_entry->getID());
		}

		$this->sess_entry    = null;
		$this->store         = null;
		$this->started       = false;
		$this->delete_cookie = true;

		return $this;
	}

	/**
	 * Attach user to this session.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user
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

		$client          = $this->sess_entry->getClient();
		$client_owner_id = $client?->getUserID();

		if ($client_owner_id && $client_owner_id !== $uid) {
			throw new RuntimeException('OZ_SESSION_PRIVATE_CLIENT_API_KEY_USED_CANT_ATTACH_ANOTHER_USER', [
				'user_id'     => $uid,
				'_session_id' => $sid,
				'_owner'      => [
					'client_id'       => $sid,
					'client_owner_id' => $client_owner_id,
				],
			]);
		}

		$this->sess_entry->setUserID($uid);

		return $this;
	}

	/**
	 * Gets the session data store.
	 *
	 * @return \OZONE\OZ\Sessions\SessionDataStore
	 */
	public function getDataStore(): SessionDataStore
	{
		$this->assertSessionStarted();

		return $this->store;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		ResponseHook::handle(static function (ResponseHook $ev) {
			$ev->getContext()
				->getSession()
				->responseReady();
		}, Event::RUN_LAST);

		FinishHook::handle(static function () {
			self::gc();
		}, Event::RUN_LAST);
	}

	/**
	 * Initialize session.
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 */
	private function init(): void
	{
		$request = $this->context->getRequest();

		// 1) if we have a cookie, we deal with it and only with it
		//    - if the cookie is not valid ignore any other method
		// 2) else we can use token header if enabled and provided

		$sid = $request->getCookieParam($this->cookie_name);

		if ($sid) {
			$this->sess_entry = self::loadWithSessionID($sid);
		} elseif (Configs::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_ENABLED')) {
			$token_header_name = Configs::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_NAME');
			$token             = $request->getHeaderLine($token_header_name);

			$this->sess_entry = self::loadWithSessionToken($token);
		}

		$client = null;

		if ($api_key = ClientHelper::getApiKey($request)) {
			$client = ClientHelper::getClientWithApiKey($api_key);

			if (!$client) {
				throw new ForbiddenException('OZ_YOUR_API_KEY_IS_NOT_VALID', [
					'url'     => (string) $request->getUri(),
					'api_key' => $api_key,
				]);
			}
		} elseif ($this->sess_entry) {
			$client = $this->sess_entry->getClient();
		}

		if (!$client) {
			throw new ForbiddenException('OZ_API_CLIENT_IS_REQUIRED');
		}

		if (!$client->getValid()) {
			throw new ForbiddenException('OZ_YOUR_API_CLIENT_IS_DISABLED', [
				'_url'     => (string) $request->getUri(),
				'_api_key' => $api_key,
			]);
		}

		if ($this->sess_entry && $this->sess_entry->getClientID() !== $client->getID()) {
			throw new ForbiddenException(null, [
				'_reason'                 => 'The current api client is trying to use a session ID started with another api client.',
				'_session_id'             => $this->sess_entry->getID(),
				'_expected_api_client_id' => $this->sess_entry->getClientID(),
				'_api_client_id'          => $client->getID(),
			]);
		}

		$this->client = $client;
	}

	/**
	 * Response ready hook.
	 */
	private function responseReady(): void
	{
		$response = $this->context->getResponse();

		if ($this->started) {
			$this->save();
			$params          = $this->getCookieParams();
			$params['value'] = $this->sess_entry->getID();
			$cookie          = new Cookies();
			$cookie->set($this->cookie_name, $params);

			$response = $response->withHeader('Set-Cookie', $cookie->toHeaders());
		}

		if ($this->delete_cookie) {
			$params            = $this->getCookieParams();
			$params['expires'] = \time() - 86400;
			$params['value']   = '';
			$cookie            = new Cookies();
			$cookie->set($this->cookie_name, $params);

			$response = $response->withHeader('Set-Cookie', $cookie->toHeaders());
		}

		$this->context->setResponse($response);
	}

	/**
	 * Assert if the session started.
	 */
	private function assertSessionStarted(): void
	{
		if (!$this->started || !isset($this->sess_entry, $this->store, $this->client)) {
			throw new RuntimeException('OZ_SESSION_NOT_STARTED');
		}
	}

	/**
	 * Load session from database.
	 *
	 * @param string $sid
	 *
	 * @return null|OZSession
	 */
	private static function loadWithSessionID(string $sid): ?OZSession
	{
		if (!self::isSessionIdLike($sid)) {
			return null;
		}

		$factory = function () use ($sid) {
			try {
				$sqb = new OZSessionsQuery();

				$result = $sqb->whereIdIs($sid)
					->whereValidIsTrue()
					->find(1);

				$item = $result->fetchClass();

				if ($item && $item->getExpire() > \time()) {
					return $item;
				}
			} catch (Throwable $t) {
				throw new RuntimeException('OZ_SESSION_UNABLE_TO_LOAD_WITH_ID', ['_session_id' => $sid], $t);
			}

			return null;
		};

		return CacheManager::runtime(__METHOD__)
			->factory($sid, $factory)
			->get();
	}

	/**
	 * Load session from database.
	 *
	 * @param string $token
	 *
	 * @return null|OZSession
	 */
	private static function loadWithSessionToken(string $token): ?OZSession
	{
		if (!self::isSessionTokenLike($token)) {
			return null;
		}

		$factory = function () use ($token) {
			try {
				$s_table = new OZSessionsQuery();

				$result = $s_table->whereTokenIs($token)
					->whereValidIsTrue()
					->find(1);

				$item = $result->fetchClass();

				if ($item && $item->getExpire() > \time()) {
					return $item;
				}
			} catch (Throwable $t) {
				throw new RuntimeException(
					'OZ_SESSION_UNABLE_TO_LOAD_WITH_TOKEN',
					['_session_token' => $token],
					$t
				);
			}

			return null;
		};

		return CacheManager::runtime(__METHOD__)
			->factory($token, $factory)
			->get();
	}

	/**
	 * Save session data.
	 */
	private function save(): void
	{
		$sid = $this->sess_entry->getID();

		try {
			$expire = (\time() + ((int) $this->client->getSessionLifeTime()));

			$data = $this->store->getData();

			$this->sess_entry->setData($data)
				->setExpire($expire)
				->setLastSeen(\time())
				->setUpdatedAT(\time())
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_SESSION_SAVING_FAILED', ['_session_id' => $sid], $t);
		}
	}

	/**
	 * Gets cookie params to use for this request.
	 *
	 * @return array
	 */
	private function getCookieParams(): array
	{
		$cfg_domain   = Configs::get('oz.cookie', 'OZ_COOKIE_DOMAIN');
		$cfg_path     = Configs::get('oz.cookie', 'OZ_COOKIE_PATH');
		$cfg_lifetime = Configs::get('oz.cookie', 'OZ_COOKIE_LIFETIME');
		$samesite     = Configs::get('oz.cookie', 'OZ_COOKIE_SAMESITE');

		$context  = $this->context;
		$request  = $context->getRequest();
		$uri      = $request->getUri();
		$secure   = 'https' === $uri->getScheme();
		$lifetime = \max($cfg_lifetime, $this->client->getSessionLifeTime());
		$domain   = (empty($cfg_domain) || 'self' === $cfg_domain) ? $context->getHost() : $cfg_domain;
		$path     = (empty($cfg_path) || 'self' === $cfg_path) ? $uri->getBasePath() : $cfg_path;

		$path = empty($path) ? '/' : $path;

		return [
			'expires'  => \time() + $lifetime,
			'path'     => $path,
			'domain'   => $domain,
			'httponly' => true,
			'secure'   => $secure,
			'samesite' => $samesite, // None, Lax or Strict
		];
	}

	/**
	 * Delete all expired sessions.
	 */
	private static function gc(): void
	{
		try {
			$s_table = new OZSessionsQuery();
			$s_table->whereExpireIsLte(\time())
				->delete()
				->execute();
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_SESSION_EXPIRED_DELETION_FAILED', null, $t);
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
	 * Checks for session token string.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private static function isSessionTokenLike(mixed $value): bool
	{
		return \is_string($value) && \preg_match(self::SESSION_TOKEN_REG, $value);
	}

	/**
	 * Remove session from database.
	 *
	 * @param string $sid
	 */
	private static function removeSession(string $sid): void
	{
		try {
			$s_table = new OZSessionsQuery();

			$s_table->whereIdIs($sid)
				->delete()
				->execute();
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_SESSION_REMOVE_FAILED', ['_session_id' => $sid], $t);
		}
	}
}
