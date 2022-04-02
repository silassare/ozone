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

namespace OZONE\OZ\Crypt;

use OZONE\OZ\Core\Hasher;

/**
 * Class DoCrypt.
 */
class DoCrypt implements CryptInterface
{
	/**
	 * BCRYPT algorithm max input length is 72.
	 *
	 * @var int
	 */
	public const BCRYPT_MAX_INPUT_LENGTH = 72;

	/**
	 * DoCrypt constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * {@inheritDoc}
	 */
	public function isHash(string $pass): bool
	{
		$pass_info = \password_get_info($pass);

		return \PASSWORD_BCRYPT === $pass_info['algo'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function passHash(string $pass): string
	{
		return \password_hash(self::toShort($pass), \PASSWORD_BCRYPT);
	}

	/**
	 * {@inheritDoc}
	 */
	public function passCheck(string $pass, string $known_hash): bool
	{
		return \password_verify(self::toShort($pass), $known_hash);
	}

	/**
	 * shorten password to comply with BCRYPT algorithm max input length (72).
	 *
	 * @param string $pass the password
	 *
	 * @return string
	 */
	private static function toShort(string $pass): string
	{
		if (\strlen($pass) > self::BCRYPT_MAX_INPUT_LENGTH) {
			$pass = Hasher::hash64($pass);
		}

		return $pass;
	}
}
