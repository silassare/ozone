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

namespace OZONE\Core\App;

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Utils\Hasher;
use OZONE\Core\Utils\Random;

/**
 * Class Keys.
 */
final class Keys
{
	/**
	 * Generate new file key.
	 *
	 * @return string
	 */
	public static function newFileKey(): string
	{
		// make sure to make differences between each cloned file key
		// if not, all clone will have the same file_key as the original file
		return Hasher::hash32(Random::string() . \microtime() . self::salt());
	}

	/**
	 * Generate new session id.
	 *
	 * @return string
	 */
	public static function newSessionID(): string
	{
		return Hasher::hash64(\serialize($_SERVER) . Random::string(128) . \microtime() . self::salt());
	}

	/**
	 * Generate new session token.
	 *
	 * @return string
	 */
	public static function newSessionToken(): string
	{
		return Hasher::hash64(\serialize($_SERVER) . Random::string(128) . \microtime() . self::salt());
	}

	/**
	 * Generate new auth code.
	 *
	 * @param int  $length    the auth code length
	 * @param bool $alpha_num whether to use digits or alpha_num
	 *
	 * @return string
	 */
	public static function newAuthCode(int $length = 4, bool $alpha_num = false): string
	{
		$min = 4;
		$max = 32;

		if ($length < $min || $length > $max) {
			throw new InvalidArgumentException(\sprintf('Auth code length must be between %d and %d.', $min, $max));
		}

		return $alpha_num ? Random::alphaNum($length) : Random::num($length);
	}

	/**
	 * Generate new auth token.
	 *
	 * @return string
	 */
	public static function newAuthToken(): string
	{
		return Hasher::hash64(Random::string() . self::salt());
	}

	/**
	 * Generate new auth refresh key.
	 *
	 * @param string $auth_ref the auth reference
	 *
	 * @return string
	 */
	public static function newAuthRefreshKey(string $auth_ref): string
	{
		return Hasher::hash64($auth_ref . Random::string() . self::salt());
	}

	/**
	 * Generate new auth ref.
	 *
	 * @return string
	 */
	public static function newAuthReference(): string
	{
		return Hasher::hash64(Random::string() . self::salt());
	}

	/**
	 * Generate new project salt.
	 *
	 * @return string
	 */
	public static function newSalt(): string
	{
		return Random::string(64);
	}

	/**
	 * Generate new project secret.
	 *
	 * @return string
	 */
	public static function newSecret(): string
	{
		return Random::string(64);
	}

	/**
	 * Get project salt.
	 *
	 * @return string
	 */
	public static function salt(): string
	{
		return self::b64Env('OZ_APP_SALT');
	}

	/**
	 * Get project secret.
	 *
	 * @return string
	 */
	public static function secret(): string
	{
		return self::b64Env('OZ_APP_SECRET');
	}

	/**
	 * Get base64 encoded env value.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private static function b64Env(string $key): string
	{
		static $decoded = [];

		if (!isset($decoded[$key])) {
			$secret = env($key);

			if (empty($secret) || !\is_string($secret)) {
				throw (new RuntimeException(\sprintf('Missing or invalid "%s" in env file.', $key)))->suspectEnv($key);
			}

			$value = \base64_decode($secret, true);

			if (false === $value) {
				throw (new RuntimeException(\sprintf('Invalid "%s" in env file.', $key)))->suspectEnv($key);
			}

			$decoded[$key] = $value;
		}

		return $decoded[$key];
	}
}
