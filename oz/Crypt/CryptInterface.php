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

interface CryptInterface
{
	/**
	 * Checks if a given password is hashed with current algorithm.
	 *
	 * @param string $pass The password to check
	 *
	 * @return bool
	 */
	public function isHash(string $pass): bool;

	/**
	 * Gets password hash.
	 *
	 * @param string $pass The password to be hashed
	 *
	 * @return string
	 */
	public function passHash(string $pass): string;

	/**
	 * Checks password.
	 *
	 * @param string $pass       The password to be hashed
	 * @param string $known_hash The correct password hash
	 *
	 * @return bool
	 */
	public function passCheck(string $pass, string $known_hash): bool;

	// TODO implements methods
	// public function encrypt(string $pass_phrase, string $input): string;
	// public function decrypt(string $pass_phrase, string $input);
	// public function encryptFile(string $pass_phrase, string $file_path, string $destination_path): string;
	// public function decryptFile(string $pass_phrase, string $file_path, string $destination_path): string;
}
