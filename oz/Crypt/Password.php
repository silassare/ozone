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

namespace OZONE\Core\Crypt;

/**
 * Class Password.
 */
class Password
{
	private static array $algo_options = [
		\PASSWORD_BCRYPT => ['max_len' => 72],
	];

	/**
	 * Checks if a given string is a password hash.
	 *
	 * @param string $str
	 *
	 * @return bool
	 */
	public static function isHash(string $str): bool
	{
		$info = \password_get_info($str);

		return $info && isset($info['algo']);
	}

	/**
	 * Hashes password.
	 *
	 * @param string $pass The password to be hashed
	 * @param string $algo The algorithm to use
	 *
	 * @return string
	 */
	public static function hash(string $pass, string $algo = \PASSWORD_BCRYPT): string
	{
		return \password_hash(self::resizePass($pass, $algo), $algo);
	}

	/**
	 * Checks password validity.
	 *
	 * @param string $pass       The password to be checked
	 * @param string $known_hash The known password hash
	 *
	 * @return bool
	 */
	public static function verify(string $pass, string $known_hash): bool
	{
		$info = \password_get_info($known_hash);
		$algo = $info['algo'] ?? null;

		if ($algo) {
			return \password_verify(self::resizePass($pass, $algo), $known_hash);
		}

		return false;
	}

	/**
	 * Shortens password if it is longer than the supported max length.
	 * This is to avoid truncation of password by the algorithm.
	 * This will use sha256 of the password if it is longer than the supported max length.
	 *
	 * @param string $pass the password
	 * @param string $algo the algorithm
	 *
	 * @return string
	 */
	private static function resizePass(string $pass, string $algo): string
	{
		$max_len = self::$algo_options[$algo]['max_len'] ?? null;

		if ($max_len && \strlen($pass) > $max_len) {
			$pass = \hash('sha256', $pass);
		}

		return $pass;
	}
}
