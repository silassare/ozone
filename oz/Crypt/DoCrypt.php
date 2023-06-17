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

use OZONE\Core\Crypt\Interfaces\CryptInterface;
use RuntimeException;

/**
 * Class DoCrypt.
 */
class DoCrypt implements CryptInterface
{
	protected string $cypher;

	/**
	 * DoCrypt constructor.
	 */
	public function __construct(string $cypher = 'aes-256-cbc')
	{
		$cypher = \strtolower($cypher);

		if (!\in_array($cypher, \openssl_get_cipher_methods(), true)) {
			throw new RuntimeException('Unsupported cypher method: ' . $cypher);
		}

		$this->cypher = $cypher;
	}

	/**
	 * {@inheritDoc}
	 */
	public function encrypt(string $message, string $pass_phrase): string
	{
		// TODO: rewrite this
		return \openssl_encrypt($message, $this->cypher, $pass_phrase);
	}

	/**
	 * {@inheritDoc}
	 */
	public function decrypt(string $message, string $pass_phrase): string
	{
		// TODO: rewrite this
		return \openssl_decrypt($message, $this->cypher, $pass_phrase);
	}
}
