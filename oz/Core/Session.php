<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use Exception;
use Gobl\DBAL\Rule;
use OZONE\OZ\Db\OZSession;
use OZONE\OZ\Db\OZSessionsQuery;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Http\Cookies;
use OZONE\OZ\Http\Response;

final class Session
{
	const SESSION_ID_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	const SESSION_TOKEN_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	/**
	 * @var \OZONE\OZ\Core\SessionDataStore[]
	 */
	private static $store_cache = [];

	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var \OZONE\OZ\Core\SessionDataStore
	 */
	private $store;

	/**
	 * @var bool
	 */
	private $started = false;

	/**
	 * @var bool
	 */
	private $delete_cookie = false;

	/**
	 * Session constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	public function __construct(Context $context)
	{
		$this->context = $context;
		$this->store   = new SessionDataStore();
		$this->name    = SettingsManager::get('oz.config', 'OZ_API_SESSION_ID_NAME');
		$this->id      = $context->getRequest()
								 ->getCookieParam($this->name, null);

		$this->init();
	}

	/**
	 * Session destructor.
	 */
	public function __destruct()
	{
		unset($this->context, $this->store);
	}

	/**
	 * Start the session.
	 *
	 * @return $this
	 */
	public function start()
	{
		if (!$this->id) {
			$this->create();
		}

		$this->started       = true;
		$this->delete_cookie = false;

		return $this;
	}

	/**
	 * Restart the session.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return $this
	 */
	public function restart()
	{
		$this->assertSessionStarted();

		return $this->destroy()
					->create()
					->start();
	}

	/**
	 * Destroy the session.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return $this
	 */
	public function destroy()
	{
		$this->assertSessionStarted();

		$this->started       = false;
		$this->delete_cookie = true;
		$id                  = $this->id;
		$this->id            = null;

		$this->store->clear();

		self::removeSession($id);

		return $this;
	}

	/**
	 * Attach user to this session.
	 *
	 * @param \OZONE\OZ\Db\OZUser $user
	 *
	 * @throws \Throwable
	 *
	 * @return \OZONE\OZ\Db\OZSession
	 */
	public function attachUser(OZUser $user)
	{
		$this->assertSessionStarted();

		// it may be a new session, so we save first
		$session = $this->save();
		$uid     = $user->getId();

		if (!$session) {
			// the session is supposed to exists in the database but was not found
			throw new InternalErrorException('OZ_SESSION_NOT_FOUND_CANT_ATTACH_USER', [
				'user_id'     => $uid,
				'_session_id' => $this->id,
			]);
		}

		$session_owner_id = $session->getUserId();

		if ($session_owner_id && $uid !== $session_owner_id) {
			throw new InternalErrorException('OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER', [
				'user_id'     => $uid,
				'_session_id' => $this->id,
				'_owner'      => [
					'session_owner_id' => $session_owner_id,
				],
			]);
		}

		$client_owner_id = $session->getOZClient()
								   ->getUserId();

		if ($client_owner_id && $client_owner_id !== $uid) {
			throw new InternalErrorException('OZ_SESSION_PRIVATE_CLIENT_API_KEY_USED_CANT_ATTACH_ANOTHER_USER', [
				'user_id'     => $uid,
				'_session_id' => $this->id,
				'_owner'      => [
					'client_api_key'  => $session->getClientApiKey(),
					'client_owner_id' => $client_owner_id,
				],
			]);
		}

		$session->setUserId($uid)
				->save();

		return $session;
	}

	/**
	 * Gets the session data store.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return \OZONE\OZ\Core\SessionDataStore
	 */
	public function getStore()
	{
		$this->assertSessionStarted();

		return $this->store;
	}

	/**
	 * Gets session data.
	 *
	 * @return array
	 */
	public function getData()
	{
		if ($this->started) {
			return $this->store->getStoreData();
		}

		return [];
	}

	/**
	 * Gets session key value.
	 *
	 * @param string $key
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function get($key, $def = null)
	{
		if ($this->started) {
			return $this->store->get($key, $def);
		}

		return $def;
	}

	/**
	 * Sets session key value.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return $this
	 */
	public function set($key, $value)
	{
		$this->assertSessionStarted();

		$this->store->set($key, $value);

		return $this;
	}

	/**
	 * Removes session key value.
	 *
	 * @param string $key
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return $this
	 */
	public function remove($key)
	{
		$this->assertSessionStarted();

		$this->store->remove($key);

		return $this;
	}

	/**
	 * Response ready hook.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function responseReady(Response $response)
	{
		if ($this->context->getClient()) {
			if ($this->started) {
				$this->save();

				return $this->setCookie($response);
			}

			if ($this->delete_cookie) {
				return $this->deleteCookie($response);
			}
		}

		return $response;
	}

	/**
	 * Init the session.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	private function init()
	{
		try {
			/*
				1) if we have a cookie, we deal with it and only with it
					  - if the cookie is not valid ignore any other method
				2) when we don't have cookie, we can use token header if enabled and provided
			*/
			if (isset($this->id)) {
				if (self::isSessionIdLike($this->id)) {
					if (isset(self::$store_cache[$this->id])) {
						$this->store = self::$store_cache[$this->id];
					} else {
						$item = $this->load($this->id);

						if ($item) {
							$data = self::decode($item->getData());

							if (\is_array($data)) {
								self::$store_cache[$this->id] = $this->store->setStoreData($data);
							}
						}
					}
				}
			} elseif (SettingsManager::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_ENABLED')) {
				$token_name = SettingsManager::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_NAME');
				$token      = $this->context->getRequest()
											->getHeaderLine($token_name);

				if (self::isSessionTokenLike($token)) {
					$item = $this->loadWithToken($token);

					if ($item) {
						$this->id = $item->getId();

						if (isset(self::$store_cache[$this->id])) {
							$this->store = self::$store_cache[$this->id];
						} else {
							$data = self::decode($item->getData());

							if (\is_array($data)) {
								self::$store_cache[$this->id] = $this->store->setStoreData($data);
							}
						}
					}
				}
			}

			if (isset($this->id) && !isset(self::$store_cache[$this->id])) {
				$this->id = null;
			}
		} catch (Exception $e) {
			throw new InternalErrorException('OZ_SESSION_INIT_FAILED', null, $e);
		}
	}

	/**
	 * Assert if the session started.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	private function assertSessionStarted()
	{
		if (!$this->started) {
			throw new InternalErrorException('OZ_SESSION_NOT_STARTED');
		}
	}

	/**
	 * Create a new session data and id.
	 *
	 * @return $this
	 */
	private function create()
	{
		$this->id = Hasher::genSessionId();
		$this->store->clear();

		return $this;
	}

	/**
	 * Load session from database.
	 *
	 * @param string $id
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return null|OZSession
	 */
	private function load($id)
	{
		if (self::isSessionIdLike($id)) {
			try {
				$s_table = new OZSessionsQuery();

				$result = $s_table->filterById($id)
								  ->find(1);

				$item = $result->fetchClass();

				if ($item) {
					if ($item->getExpire() > \time()) {
						return $item;
					}
					// we are lazy
					$this->gc();
				}
			} catch (Exception $e) {
				throw new InternalErrorException('OZ_SESSION_UNABLE_TO_LOAD_WITH_ID', ['session_id' => $id], $e);
			}
		}

		return null;
	}

	/**
	 * Load session from database.
	 *
	 * @param string $token
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return null|OZSession
	 */
	private function loadWithToken($token)
	{
		if (self::isSessionTokenLike($token)) {
			try {
				$s_table = new OZSessionsQuery();

				$result = $s_table->filterByToken($token)
								  ->find(1);

				$item = $result->fetchClass();

				if ($item) {
					if ($item->getExpire() > \time()) {
						return $item;
					}
					// we are lazy
					$this->gc();
				}
			} catch (Exception $e) {
				throw new InternalErrorException(
					'OZ_SESSION_UNABLE_TO_LOAD_WITH_TOKEN',
					['session_token' => $token],
					$e
				);
			}
		}

		return null;
	}

	/**
	 * Save session data.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return null|\OZONE\OZ\Db\OZSession
	 */
	private function save()
	{
		$client  = $this->context->getClient();
		$session = null;

		if ($client) {
			try {
				$expire = (int) (\time() + (int) ($client->getSessionLifeTime()));
				$data   = self::encode($this->store->getStoreData());
				$sq     = new OZSessionsQuery();

				$result  = $sq->filterById($this->id)
							  ->find();
				$session = $result->fetchClass();

				if ($session) {
					$session->setData($data)
							->setExpire($expire)
							->save();
				} else {
					$token   = Hasher::genAuthToken($this->id);
					$session = new OZSession();
					$session->setId($this->id)
							->setClientApiKey($client->getApiKey())
							->setData($data)
							->setExpire($expire)
							->setLastSeen(\time())
							->setToken($token)
							->save();
				}
			} catch (Exception $e) {
				throw new InternalErrorException('OZ_SESSION_SAVING_FAILED', ['session_id' => $this->id], $e);
			}
		}

		return $session;
	}

	/**
	 * Sets session cookie.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	private function setCookie(Response $response)
	{
		$params          = $this->getCookieParams();
		$params['value'] = $this->id;
		$cookie          = new Cookies();
		$cookie->set($this->name, $params);

		return $response->withHeader('Set-Cookie', $cookie->toHeaders());
	}

	/**
	 * Delete session cookie.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	private function deleteCookie(Response $response)
	{
		$params            = $this->getCookieParams();
		$params['expires'] = \time() - 43200;
		$params['value']   = '';
		$cookie            = new Cookies();
		$cookie->set($this->name, $params);

		return $response->withHeader('Set-Cookie', $cookie->toHeaders());
	}

	/**
	 * Gets cookie params to use for this request.
	 *
	 * @return array
	 */
	private function getCookieParams()
	{
		$cfg_domain   = SettingsManager::get('oz.cookie', 'OZ_COOKIE_DOMAIN');
		$cfg_path     = SettingsManager::get('oz.cookie', 'OZ_COOKIE_PATH');
		$cfg_lifetime = SettingsManager::get('oz.cookie', 'OZ_COOKIE_LIFETIME');
		$samesite     = SettingsManager::get('oz.cookie', 'OZ_COOKIE_SAMESITE');

		$context  = $this->context;
		$secure   = $context->getRequest()
							->getUri()
							->getScheme() === 'https';
		$lifetime = 60 * 60;
		$client   = $context->getClient();

		if ($client) {
			$lifetime = $client->getSessionLifeTime();
		}

		$lifetime = \max($cfg_lifetime, $lifetime);
		$httponly = true;
		$domain   = (empty($cfg_domain) || $cfg_domain === 'self') ? $context->getHost() : $cfg_domain;
		$path     = (empty($cfg_path) || $cfg_path === 'self') ? $context->getRequest()
																		 ->getUri()
																		 ->getBasePath() : $cfg_path;

		$path = empty($path) ? '/' : $path;

		return [
			'expires'  => \time() + $lifetime,
			'path'     => $path,
			'domain'   => $domain,
			'httponly' => $httponly,
			'secure'   => $secure,
			'samesite' => $samesite, // None, Lax or Strict
		];
	}

	/**
	 * Delete all expired sessions.
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	private function gc()
	{
		try {
			$s_table = new OZSessionsQuery();
			$s_table->filterByExpire(\time(), Rule::OP_LTE)
					->delete()
					->execute();
		} catch (Exception $e) {
			throw new InternalErrorException('OZ_SESSION_EXPIRED_DELETION_FAILED', null, $e);
		}
	}

	/**
	 * Decode session data string.
	 *
	 * @param string $raw
	 *
	 * @return null|array is array on success, null otherwise
	 */
	public static function decode($raw)
	{
		try {
			return \json_decode($raw, true);
		} catch (Exception $e) {
		}

		return null;
	}

	/**
	 * Encode session data.
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	public static function encode(array $data)
	{
		return \json_encode($data);
	}

	/**
	 * Checks for session id string.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private static function isSessionIdLike($value)
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
	private static function isSessionTokenLike($value)
	{
		return \is_string($value) && \preg_match(self::SESSION_TOKEN_REG, $value);
	}

	/**
	 * Remove session from database.
	 *
	 * @param string $id
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return bool
	 */
	private static function removeSession($id)
	{
		try {
			$s_table = new OZSessionsQuery();

			$s_table->filterById($id)
					->delete()
					->execute();
		} catch (Exception $e) {
			throw new InternalErrorException('OZ_SESSION_DESTROY_FAILED', ['session_id' => $id], $e);
		}

		return true;
	}
}
