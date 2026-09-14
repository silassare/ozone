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

use OZONE\Core\App\Keys;

/**
 * Class SignedSerializer.
 *
 * `serialize()` with an HMAC (keyed by the app secret) prepended, so a payload is
 * only unserialized when this app wrote it. Unserializing any class from storage
 * that someone else could write (a shared cache table, a cache directory) would
 * turn that write access into code execution through magic methods.
 */
final class SignedSerializer
{
	private const SEP = ':';

	/**
	 * Serializes and signs a value.
	 */
	public static function serialize(mixed $value): string
	{
		$payload = \serialize($value);

		return self::sign($payload) . self::SEP . $payload;
	}

	/**
	 * Verifies and unserializes a payload written by {@see self::serialize()}.
	 *
	 * An unsigned or tampered payload is never unserialized.
	 *
	 * @return array{0: bool, 1: mixed} whether the signature is valid, and the value (null when not)
	 */
	public static function unserialize(string $raw): array
	{
		$pos = \strpos($raw, self::SEP);

		if (false === $pos) {
			return [false, null];
		}

		$payload = \substr($raw, $pos + 1);

		if (!\hash_equals(self::sign($payload), \substr($raw, 0, $pos))) {
			return [false, null];
		}

		return [true, \unserialize($payload, ['allowed_classes' => true])];
	}

	private static function sign(string $payload): string
	{
		return \hash_hmac('sha256', $payload, Keys::secret());
	}
}
