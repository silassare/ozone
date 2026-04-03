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

namespace OZONE\Core\Auth\TwoFactor;

use Exception;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class TOTP.
 *
 * Time-Based One-Time Password implementation per RFC 6238 (TOTP) and RFC 4226 (HOTP).
 */
final class TOTP
{
	private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * Generates a cryptographically random base32-encoded TOTP secret.
	 *
	 * @param int $byte_length number of raw bytes before encoding (20 bytes = 160-bit secret, the RFC 4226 recommended minimum)
	 *
	 * @return string base32-encoded secret (no padding)
	 */
	public static function generateSecret(int $byte_length = 20): string
	{
		try {
			$bytes = \random_bytes($byte_length);
		} catch (Exception $e) {
			throw new RuntimeException('Unable to generate TOTP secret.', null, $e);
		}

		// strip trailing '=' padding — authenticator apps handle it either way
		return \rtrim(self::base32Encode($bytes), '=');
	}

	/**
	 * Builds an otpauth:// URI for use in QR codes shown to the user.
	 *
	 * @param string $issuer  the service name (e.g. "MyApp")
	 * @param string $account the user account label (e.g. email or username)
	 * @param string $secret  base32-encoded secret
	 * @param int    $digits  TOTP code length
	 * @param int    $step    time step in seconds
	 *
	 * @return string otpauth URI
	 */
	public static function buildUri(
		string $issuer,
		string $account,
		string $secret,
		int $digits = 6,
		int $step = 30
	): string {
		$label = \rawurlencode($issuer . ':' . $account);

		return 'otpauth://totp/' . $label
			. '?secret=' . \rawurlencode(\rtrim($secret, '='))
			. '&issuer=' . \rawurlencode($issuer)
			. '&algorithm=SHA1'
			. '&digits=' . $digits
			. '&period=' . $step;
	}

	/**
	 * Computes the TOTP code for a given time (or the current time by default).
	 *
	 * @param string $secret base32-encoded secret
	 * @param int    $time   Unix timestamp (defaults to current time)
	 * @param int    $digits number of digits in the output code
	 * @param int    $step   time step in seconds
	 *
	 * @return string zero-padded numeric code
	 */
	public static function compute(
		string $secret,
		int $time = 0,
		int $digits = 6,
		int $step = 30
	): string {
		$time    = $time ?: \time();
		$counter = (int) \floor($time / $step);

		return self::hotp($secret, $counter, $digits);
	}

	/**
	 * Verifies a TOTP code against the current time (or a given time).
	 *
	 * Checks the code across [$counter - $window, $counter + $window] to tolerate clock drift.
	 *
	 * @param string $secret base32-encoded secret
	 * @param string $code   the code submitted by the user
	 * @param int    $time   Unix timestamp (defaults to current time)
	 * @param int    $window number of time steps to check on each side of the current step
	 * @param int    $digits code length
	 * @param int    $step   time step in seconds
	 *
	 * @return bool true if the code is valid within the given window
	 */
	public static function verify(
		string $secret,
		string $code,
		int $time = 0,
		int $window = 1,
		int $digits = 6,
		int $step = 30
	): bool {
		$time    = $time ?: \time();
		$counter = (int) \floor($time / $step);

		for ($delta = -$window; $delta <= $window; ++$delta) {
			if (\hash_equals(self::hotp($secret, $counter + $delta, $digits), $code)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Computes a base32-encoded string.
	 *
	 * @param string $input raw binary string
	 *
	 * @return string base32-encoded string (with padding)
	 */
	public static function base32Encode(string $input): string
	{
		if ('' === $input) {
			return '';
		}

		$alphabet = self::BASE32_ALPHABET;
		$binary   = '';

		for ($i = 0, $len = \strlen($input); $i < $len; ++$i) {
			$binary .= \str_pad(\decbin(\ord($input[$i])), 8, '0', \STR_PAD_LEFT);
		}

		$padding = \strlen($binary) % 5;
		if ($padding > 0) {
			$binary .= \str_repeat('0', 5 - $padding);
		}

		$output = '';

		for ($i = 0, $len = \strlen($binary); $i < $len; $i += 5) {
			$output .= $alphabet[(int) \bindec(\substr($binary, $i, 5))];
		}

		$pad_len = (8 - (\strlen($output) % 8)) % 8;

		return $output . \str_repeat('=', $pad_len);
	}

	/**
	 * Decodes a base32-encoded string to raw binary.
	 *
	 * @param string $input base32-encoded string (padding optional, case-insensitive)
	 *
	 * @return string raw binary string
	 */
	public static function base32Decode(string $input): string
	{
		if ('' === $input) {
			return '';
		}

		$alphabet = self::BASE32_ALPHABET;
		$input    = \strtoupper(\rtrim($input, '= '));
		$binary   = '';

		for ($i = 0, $len = \strlen($input); $i < $len; ++$i) {
			$pos = \strpos($alphabet, $input[$i]);
			// Skip characters not in the alphabet (handles spaces/dashes users may type).
			if (false === $pos) {
				continue;
			}
			$binary .= \str_pad(\decbin($pos), 5, '0', \STR_PAD_LEFT);
		}

		$output = '';

		for ($i = 0, $len = \strlen($binary) - (\strlen($binary) % 8); $i < $len; $i += 8) {
			$output .= \chr((int) \bindec(\substr($binary, $i, 8)));
		}

		return $output;
	}

	/**
	 * Computes an HMAC-based One-Time Password (HOTP) per RFC 4226.
	 *
	 * @param string $secret  base32-encoded secret
	 * @param int    $counter HOTP counter value
	 * @param int    $digits  number of digits in the output code
	 *
	 * @return string zero-padded numeric code
	 */
	private static function hotp(string $secret, int $counter, int $digits): string
	{
		$key     = self::base32Decode($secret);
		$message = \pack('J', $counter); // 8 bytes, big-endian unsigned 64-bit
		$hmac    = \hash_hmac('sha1', $message, $key, true);

		$offset = \ord($hmac[19]) & 0x0F;

		$code = (
			((\ord($hmac[$offset]) & 0x7F) << 24)
			| ((\ord($hmac[$offset + 1]) & 0xFF) << 16)
			| ((\ord($hmac[$offset + 2]) & 0xFF) << 8)
			| (\ord($hmac[$offset + 3]) & 0xFF)
		) % (10 ** $digits);

		return \str_pad((string) $code, $digits, '0', \STR_PAD_LEFT);
	}
}
