<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Db\OZClient;
	use OZONE\OZ\Db\OZClientsQuery;
	use OZONE\OZ\Db\OZClientsUsersQuery;
	use OZONE\OZ\Db\OZClientUser;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class ClientObject
	{
		/** @var \OZONE\OZ\Db\OZClient */
		private $client;

		/**
		 * ClientObject constructor.
		 *
		 * @param \OZONE\OZ\Db\OZClient $client
		 */
		public function __construct(OZClient $client)
		{
			$this->client = $client;
		}

		/**
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function getClientData()
		{
			return $this->client;
		}

		/**
		 * Checks if an user with a given id and token is using a given client
		 *
		 * @param string|int $uid   the user id
		 * @param string     $token the user token
		 *
		 * @return bool
		 */
		public function clientHasUser($uid, $token)
		{
			if (empty($uid) OR !self::isTokenLike($token)) {
				return false;
			}

			$cu     = new OZClientsUsersQuery();
			$result = $cu->filterByClientApiKey($this->client->getApiKey())
						 ->filterByUserId($uid)
						 ->filterByToken($token)
						 ->find(1);

			return ($result->count() === 1 ? true : false);
		}

		/**
		 * Remove the user with the given id from a given client
		 *
		 * @param string|int $uid the user id
		 *
		 * @return int
		 */
		public function removeClientUser($uid)
		{
			$clients_users = new OZClientsUsersQuery();

			return $clients_users->filterByClientApiKey($this->client->getApiKey())
								 ->filterByUserId($uid)
								 ->delete()
								 ->execute();
		}

		/**
		 * Checks if whether the client support multi user or not
		 *
		 * @return bool
		 */
		public function isMultiUserSupported()
		{
			return empty($this->client->getUserId());
		}

		/**
		 * Adds the user with the given id as using the current client
		 *
		 * @param string|int $uid the user id
		 *
		 * @return string
		 */
		public function addClientUser($uid)
		{
			if (!$this->isMultiUserSupported()) {
				Assert::assertAuthorizeAction($this->client->getUserId() === $uid, null, $uid);
			}

			$token = Hasher::genAuthToken($uid);
			// prevent foreign key constraint violation on session_id
			$sid   = SessionsHandler::persistActiveSession();

			$cu = new OZClientUser();
			$cu->setClientApiKey($this->client->getApiKey())
			   ->setUserId($uid)
			   ->setSessionId($sid)
			   ->setToken($token)
			   ->setLastCheck(time())
			   ->save();

			return $token;
		}

		/**
		 * Gets client instance with a given api key
		 *
		 * @param string $api_key The client api key
		 *
		 * @return null|\OZONE\OZ\Core\ClientObject
		 */
		public static function getInstanceWithApiKey($api_key)
		{
			$client_object = null;

			$c             = new OZClientsQuery();
			$client_object = $c->filterByApiKey($api_key)
							   ->filterByValid(1)
							   ->find(1)
							   ->fetchClass();

			if ($client_object) {
				return new self($client_object);
			}

			return null;
		}

		/**
		 * Gets client instance with a given session id
		 *
		 * @param string $sid The session id
		 *
		 * @return null|\OZONE\OZ\Core\ClientObject
		 */
		public static function getInstanceWithSessionId($sid)
		{
			$client_object = null;

			$cu_table = new OZClientsUsersQuery();
			$cu       = $cu_table->filterBySessionId($sid)
								 ->find(1)
								 ->fetchClass();

			if ($cu) {
				$client_object = $cu->getOZClient();

				if ($client_object) {
					return new self($client_object);
				}
			}

			return null;
		}

		/**
		 * Gets client instance with a given token
		 *
		 * @param string $token The token
		 *
		 * @return null|\OZONE\OZ\Core\ClientObject
		 */
		public static function getInstanceWithToken($token)
		{
			$client_object = null;

			$cu_table = new OZClientsUsersQuery();
			$cu       = $cu_table->filterByToken($token)
								 ->find(1)
								 ->fetchClass();
			if ($cu) {
				$client_object = $cu->getOZClient();

				if ($client_object) {
					return new self($client_object);
				}
			}

			return null;
		}

		/**
		 * Checks whether the given value is like a client token.
		 *
		 * @param mixed $value
		 *
		 * @return bool
		 */
		public static function isTokenLike($value)
		{
			$token_reg = "#^[a-zA-Z0-9]{32}$#";

			return is_string($value) AND preg_match($token_reg, $value);
		}

		/**
		 * Checks whether a given origin URL belongs to a valid client.
		 *
		 * @param string $url
		 *
		 * @return bool
		 */
		public static function checkSafeOriginUrl($url)
		{
			$c      = new OZClientsQuery();
			$result = $c->filterByUrl($url)
						->filterByValid(1)
						->find(1);

			return $result->count() > 0;
		}
	}