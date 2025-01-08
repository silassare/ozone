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

namespace OZONE\Core\Crypt\Interfaces;

/**
 * Interface CryptInterface.
 */
interface CryptInterface
{
	/**
	 * CryptInterface constructor.
	 */
	public function __construct(string $cypher);

	/**
	 * Encrypts a message.
	 *
	 * @param string $message
	 * @param string $pass_phrase
	 *
	 * @return string
	 */
	public function encrypt(string $message, string $pass_phrase): string;

	/**
	 * Decrypts a message.
	 *
	 * @param string $message
	 * @param string $pass_phrase
	 *
	 * @return string
	 */
	public function decrypt(string $message, string $pass_phrase): string;
}
