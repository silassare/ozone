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
	 * Generate new 32-character unique ID (128 random bits, hex encoded).
	 */
	public static function id32(): string
	{
		return \bin2hex(\random_bytes(16));
	}

	/**
	 * Generate new 64-character unique ID (256 random bits, hex encoded).
	 */
	public static function id64(): string
	{
		return \bin2hex(\random_bytes(32));
	}

	/**
	 * Generate new file key.
	 */
	public static function newFileKey(): string
	{
		// make sure to make differences between each cloned file key
		// if not, all clone will have the same file_key as the original file
		return Hasher::hash32(Random::string() . \microtime() . self::salt());
	}

	/**
	 * Generate new session id.
	 */
	public static function newSessionID(): string
	{
		return Hasher::hash64(\serialize($_SERVER) . Random::string(128) . \microtime() . self::salt());
	}

	/**
	 * Generate new session token.
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
	 */
	public static function newAuthToken(): string
	{
		return Hasher::hash64(Random::string() . self::salt());
	}

	/**
	 * Generate new auth refresh key.
	 *
	 * @param string $auth_ref the auth reference
	 */
	public static function newAuthRefreshKey(string $auth_ref): string
	{
		return Hasher::hash64($auth_ref . Random::string() . self::salt());
	}

	/**
	 * Generate new auth ref.
	 */
	public static function newAuthReference(): string
	{
		return Hasher::hash64(Random::string() . self::salt());
	}

	/**
	 * Generate new project salt (512 random bits).
	 *
	 * Raw bytes, not characters drawn from an alphabet: the only callers base64-encode the result
	 * into the env file, so a printable alphabet would cost entropy and buy nothing.
	 */
	public static function newSalt(): string
	{
		return \random_bytes(64);
	}

	/**
	 * Generate new project secret (512 random bits).
	 *
	 * Raw bytes, for the same reason as the salt.
	 */
	public static function newSecret(): string
	{
		return \random_bytes(64);
	}

	/**
	 * Get project salt.
	 */
	public static function salt(): string
	{
		return self::b64SecretEnv('OZ_APP_SALT');
	}

	/**
	 * Get project secret.
	 */
	public static function secret(): string
	{
		return self::b64SecretEnv('OZ_APP_SECRET');
	}

	/**
	 * Get and decode a base64-encoded secret from env.
	 * Caches decoded values to avoid redundant decoding on subsequent calls.
	 */
	private static function b64SecretEnv(string $key): string
	{
		static $decoded = [];

		if (!isset($decoded[$key])) {
			$secret = env($key);

			if (empty($secret) || !\is_string($secret)) {
				throw new RuntimeException(\sprintf('Missing or invalid "%s" in env file.', $key))->suspectEnv($key);
			}

			$value = \base64_decode($secret, true);

			if (false === $value) {
				throw new RuntimeException(\sprintf('Invalid "%s" in env file.', $key))->suspectEnv($key);
			}

			$decoded[$key] = $value;
		}

		return $decoded[$key];
	}
}
