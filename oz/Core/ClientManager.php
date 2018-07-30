<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Db\OZClientsQuery;
	use OZONE\OZ\Db\OZSessionsQuery;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class ClientManager
	{
		/**
		 * Gets client instance with a given api key
		 *
		 * @param string $api_key The client api key
		 *
		 * @return null|\OZONE\OZ\Db\OZClient
		 */
		public static function getClientWithApiKey($api_key)
		{
			$c = new OZClientsQuery();

			return $c->filterByApiKey($api_key)
					 ->filterByValid(1)
					 ->find(1)
					 ->fetchClass();
		}

		/**
		 * Gets client instance with a given session id
		 *
		 * @param string $sid The session id
		 *
		 * @return null|\OZONE\OZ\Db\OZClient
		 */
		public static function getClientWithSessionId($sid)
		{
			$sc      = new OZSessionsQuery();
			$session = $sc->filterById($sid)
						  ->find(1)
						  ->fetchClass();

			if ($session) {
				return $session->getOZClient();
			}

			return null;
		}

		/**
		 * Gets client instance with a given token
		 *
		 * @param string $token The token
		 *
		 * @return null|\OZONE\OZ\Db\OZClient
		 */
		public static function getClientWithSessionToken($token)
		{
			$sc      = new OZSessionsQuery();
			$session = $sc->filterByToken($token)
						  ->find(1)
						  ->fetchClass();

			if ($session) {
				return $session->getOZClient();
			}

			return null;
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