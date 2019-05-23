<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use Gobl\DBAL\Rule;
	use OZONE\OZ\Db\OZClient;
	use OZONE\OZ\Db\OZSession;
	use OZONE\OZ\Db\OZSessionsQuery;
	use OZONE\OZ\Db\OZUser;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Http\Cookies;
	use OZONE\OZ\Http\Response;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class Session
	{
		const SESSION_ID_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

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
		 * @var \OZONE\OZ\Core\SessionDataStore[]
		 */
		private static $store_cache = [];

		/**
		 * @var bool
		 */
		private $started = false;

		/**
		 * @var bool
		 */
		private $delete_cookie = false;

		/**
		 * OZoneSession constructor.
		 *
		 * @param \OZONE\OZ\Core\Context $context
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
		 * Init the session.
		 */
		private function init()
		{
			try {
				if (isset($this->id) AND self::isSessionIdLike($this->id)) {
					if (isset(self::$store_cache[$this->id])) {
						$this->store = self::$store_cache[$this->id];
					} else {
						$item = $this->load($this->id);
						$data = null;
						if ($item) {
							$data = self::decode($item->getData());
						}
						if (is_array($data)) {
							self::$store_cache[$this->id] = $this->store->setStoreData($data);
						} else {
							// the session is invalid
							$this->id = null;
						}
					}
				}
			} catch (\Exception $e) {
				throw new \RuntimeException('Session init failed.', null, $e);
			}
		}

		/**
		 * Assert if the session started.
		 */
		private function assertSessionStarted()
		{
			if (!$this->started) {
				throw new \RuntimeException('Session not started.');
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
		 * @return $this
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
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
		 * @return $this
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
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
		 * @return \OZONE\OZ\Db\OZSession
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function attachUser(OZUser $user)
		{
			$this->assertSessionStarted();

			// it may be a new session, so we save first
			$item = $this->save();

			if (!$item) {
				// the session is supposed to exists in the database but was not found
				throw new InternalErrorException(sprintf('Unable to save the session (sid: %s).', $this->id));
			}

			$uid = $user->getId();

			if ($item->getUserId()) {
				throw new InternalErrorException(sprintf('The session (sid: %s) is already in use by (user: %s) and should not be attached to (user: %s).', $this->id, $item->getUserId(), $uid));
			}

			$item->setUserId($uid)
				 ->save();

			return $item;
		}

		/**
		 * Gets the session data store.
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
			$this->assertSessionStarted();

			return $this->store->getStoreData();
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
			$this->assertSessionStarted();

			return $this->store->get($key, $def);
		}

		/**
		 * Sets session key value.
		 *
		 * @param string $key
		 * @param mixed  $value
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
		 * @return $this
		 */
		public function remove($key)
		{
			$this->assertSessionStarted();

			$this->store->remove($key);

			return $this;
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
			return is_string($value) AND preg_match(self::SESSION_ID_REG, $value);
		}

		/**
		 * Persist session data into database.
		 *
		 * @param string                $id
		 * @param array                 $data
		 * @param \OZONE\OZ\Db\OZClient $client
		 *
		 * @return bool|\OZONE\OZ\Db\OZSession|null
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private static function persist($id, array $data, OZClient $client)
		{
			$expire = intval(time() + intval($client->getSessionLifeTime()));
			$s      = null;
			try {
				$data = self::encode($data);
				$sq   = new OZSessionsQuery();

				$result = $sq->filterById($id)
							 ->find();
				$s      = $result->fetchClass();

				if ($s) {
					$s->setData($data)
					  ->setExpire($expire)
					  ->save();
				} else {
					$token = Hasher::genAuthToken($id);
					$s     = new OZSession();
					$s->setId($id)
					  ->setClientApiKey($client->getApiKey())
					  ->setData($data)
					  ->setExpire($expire)
					  ->setLastSeen(time())
					  ->setToken($token)
					  ->save();
				}
			} catch (\Exception $e) {
				throw new InternalErrorException('Unable to save session data.', null, $e);
			}

			return $s;
		}

		/**
		 * Remove session from database.
		 *
		 * @param string $id
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private static function removeSession($id)
		{
			try {
				$s_table = new OZSessionsQuery();

				$s_table->filterById($id)
						->delete()
						->execute();
			} catch (\Exception $e) {
				throw new InternalErrorException("Unable to destroy session.", ["session_id" => $id], $e);
			}

			return true;
		}

		/**
		 * Load session from database.
		 *
		 * @param string $id
		 *
		 * @return OZSession|null
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
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
						if ($item->getExpire() > time()) {
							return $item;
						} else {
							// we are lazy
							$this->gc();
						}
					}
				} catch (\Exception $e) {
					throw new InternalErrorException('Unable to load session data.', ['session_id' => $id], $e);
				}
			}

			return null;
		}

		/**
		 * Response ready hook.
		 *
		 * @param \OZONE\OZ\Http\Response $response
		 *
		 * @return \OZONE\OZ\Http\Response
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function responseReadyHook(Response $response)
		{
			if ($this->started) {
				$this->save();

				return $this->setCookie($response);
			} elseif ($this->delete_cookie) {
				return $this->deleteCookie($response);
			}

			return $response;
		}

		/**
		 * Save session data.
		 *
		 * @return \OZONE\OZ\Db\OZSession|null
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private function save()
		{
			$client = $this->context->getClient();

			return self::persist($this->id, $this->store->getStoreData(), $client);
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
			$params['expires'] = time() - 43200;
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
			$cfg_domain   = SettingsManager::get("oz.cookie", "OZ_COOKIE_DOMAIN");
			$cfg_lifetime = SettingsManager::get("oz.cookie", "OZ_COOKIE_LIFETIME");

			$context       = $this->context;
			$cookie_params = session_get_cookie_params();
			$lifetime      = min($cfg_lifetime, $context->getClient()
														->getSessionLifeTime());
			$httponly      = true;
			$domain        = ($cfg_domain === 'self') ? $context->getHost() : $cfg_domain;

			$path = $context->getRequest()
							->getUri()
							->getBasePath();
			$path = empty($path) ? '/' : $path;

			return [
				'expires'  => time() + $lifetime,
				'path'     => $path,
				'domain'   => $domain,
				'httponly' => $httponly,
				'secure'   => $cookie_params['secure']
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
				$s_table->filterByExpire(time(), Rule::OP_LTE)
						->delete()
						->execute();
			} catch (\Exception $e) {
				throw new InternalErrorException('Unable to delete expired sessions.', null, $e);
			}
		}

		/**
		 * Decode session data string.
		 *
		 * @param string $raw
		 *
		 * @return array|null is array on success, null otherwise.
		 */
		static function decode($raw)
		{
			try {
				$data = json_decode($raw, true);

				return $data;
			} catch (\Exception $e) {
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
		static function encode(array $data)
		{
			return json_encode($data);
		}
	}