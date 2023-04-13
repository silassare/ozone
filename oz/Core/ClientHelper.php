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

namespace OZONE\OZ\Core;

use OZONE\OZ\Db\OZClient;
use OZONE\OZ\Db\OZClientsQuery;
use OZONE\OZ\Db\OZSessionsQuery;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Http\Request;
use Throwable;

/**
 * Class ClientHelper.
 */
final class ClientHelper
{
	public const API_KEY_REG = '~^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$~';

	/**
	 * Gets the api key.
	 *
	 * @param \OZONE\OZ\Http\Request $request
	 *
	 * @return null|string
	 */
	public static function getApiKey(Request $request): ?string
	{
		$api_key_name = Configs::get('oz.config', 'OZ_API_KEY_HEADER_NAME');
		$header_name  = \sprintf('HTTP_%s', \strtoupper(\str_replace('-', '_', $api_key_name)));
		$api_key      = $request->getHeaderLine($header_name);

		if (empty($api_key) && \defined('OZ_OZONE_DEFAULT_API_KEY')) {
			$api_key = OZ_OZONE_DEFAULT_API_KEY;
		}

		return self::isApiKeyLike($api_key) ? $api_key : null;
	}

	/**
	 * Checks if a given string is like an API key.
	 *
	 * @param string $str
	 *
	 * @return bool
	 */
	public static function isApiKeyLike(string $str): bool
	{
		if (empty($str)) {
			return false;
		}

		return 1 === \preg_match(self::API_KEY_REG, $str);
	}

	/**
	 * Gets client instance with a given api key.
	 *
	 * @param string $api_key The client api key
	 *
	 * @return null|\OZONE\OZ\Db\OZClient
	 */
	public static function getClientWithApiKey(string $api_key): ?OZClient
	{
		try {
			$c = new OZClientsQuery();

			return $c->whereApiKeyIs($api_key)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to load client with API Key: %s', $api_key), null, $t);
		}
	}

	/**
	 * Gets client instance with a given session id.
	 *
	 * @param string $sid The session id
	 *
	 * @return null|\OZONE\OZ\Db\OZClient
	 */
	public static function getClientWithSessionID(string $sid): ?OZClient
	{
		try {
			$sc = new OZSessionsQuery();

			$session = $sc->whereIdIs($sid)
				->find(1)
				->fetchClass();

			if ($session) {
				return $session->getClient();
			}
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to load client with session id: %s', $sid), null, $t);
		}

		return null;
	}

	/**
	 * Gets client instance with a given token.
	 *
	 * @param string $token The token
	 *
	 * @return null|\OZONE\OZ\Db\OZClient
	 */
	public static function getClientWithSessionToken(string $token): ?OZClient
	{
		try {
			$sc      = new OZSessionsQuery();
			$session = $sc->whereTokenIs($token)
				->find(1)
				->fetchClass();

			if ($session) {
				return $session->getClient();
			}
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to load client with session token: %s', $token), null, $t);
		}

		return null;
	}
}
