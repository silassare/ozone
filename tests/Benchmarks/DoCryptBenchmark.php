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

namespace OZONE\Tests\Benchmarks;

use OZONE\Core\Crypt\DoCrypt;

/**
 * Benchmarks for OZONE\Core\Crypt\DoCrypt.
 *
 * DoCrypt wraps OpenSSL symmetric encryption. Encrypt/decrypt overhead is
 * relevant for auth token storage, signed payloads, and secure cookie values.
 *
 * Add new entries here when new cipher modes or key derivation paths are added.
 */
class DoCryptBenchmark implements BenchmarkSuiteInterface
{
	public static function callables(): array
	{
		$crypt  = new DoCrypt('aes-256-cbc');
		$key    = 'bench-secret-key-32-bytes-padded';
		$cipher = $crypt->encrypt('benchmark plaintext payload', $key);

		return [
			'docrypt_encrypt_aes256' => static fn () => $crypt->encrypt('benchmark plaintext payload', $key),
			// Pre-computed ciphertext so decrypt always exercises a valid input.
			'docrypt_decrypt_aes256' => static fn () => $crypt->decrypt($cipher, $key),
		];
	}
}
