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

use Override;
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
	#[Override]
	public function encrypt(string $message, string $pass_phrase): false|string
	{
		$iv_len = \openssl_cipher_iv_length($this->cypher);

		if (false === $iv_len) {
			return false;
		}

		$iv        = $iv_len > 0 ? \random_bytes($iv_len) : '';
		$key       = \hash('sha256', $pass_phrase, true);
		$encrypted = \openssl_encrypt($message, $this->cypher, $key, \OPENSSL_RAW_DATA, $iv);

		if (false === $encrypted) {
			return false;
		}

		return \base64_encode($iv . $encrypted);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function decrypt(string $message, string $pass_phrase): false|string
	{
		$iv_len = \openssl_cipher_iv_length($this->cypher);

		if (false === $iv_len) {
			return false;
		}

		$data = \base64_decode($message, true);

		if (false === $data || \strlen($data) < $iv_len) {
			return false;
		}

		$iv         = $iv_len > 0 ? \substr($data, 0, $iv_len) : '';
		$ciphertext = \substr($data, $iv_len);
		$key        = \hash('sha256', $pass_phrase, true);

		return \openssl_decrypt($ciphertext, $this->cypher, $key, \OPENSSL_RAW_DATA, $iv);
	}
}
