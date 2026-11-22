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

namespace OZONE\Core\Http;

/**
 * Class IpRange.
 *
 * An IP address, or a CIDR range of them (`10.0.0.0/8`, `2001:db8::/32`), as a list of addresses
 * writes them: the trusted proxies of `oz.proxies` ({@see TrustedProxies}) and the addresses a
 * maintenance lets through. IPv4 and IPv6 never match each other.
 */
final class IpRange
{
	/**
	 * Whether an entry is an address or a range this class can match against.
	 */
	public static function isValid(string $range): bool
	{
		[$subnet, $bits] = \array_pad(\explode('/', $range, 2), 2, null);

		$packed = self::pack((string) $subnet);

		if (null === $packed) {
			return false;
		}

		return null === $bits || (\ctype_digit($bits) && (int) $bits <= \strlen($packed) * 8);
	}

	/**
	 * Whether an address is in a range, or is the address an entry names.
	 */
	public static function contains(string $range, string $ip): bool
	{
		$packed = self::pack($ip);

		return null !== $packed && self::containsPacked($range, $packed);
	}

	/**
	 * {@see self::contains()} for an address already packed ({@see self::pack()}), which a caller
	 * matching one address against many entries packs once.
	 */
	public static function containsPacked(string $range, string $packed): bool
	{
		[$subnet, $bits] = \array_pad(\explode('/', $range, 2), 2, null);

		$subnet_packed = self::pack((string) $subnet);

		if (null === $subnet_packed || \strlen($subnet_packed) !== \strlen($packed)) {
			return false;
		}

		if (null === $bits) {
			return $subnet_packed === $packed;
		}

		if (!\ctype_digit($bits) || (int) $bits > \strlen($packed) * 8) {
			return false;
		}

		$bits  = (int) $bits;
		$bytes = \intdiv($bits, 8);
		$rest  = $bits % 8;

		if (\substr($packed, 0, $bytes) !== \substr($subnet_packed, 0, $bytes)) {
			return false;
		}

		if (0 === $rest) {
			return true;
		}

		$mask = (0xFF << (8 - $rest)) & 0xFF;

		return (\ord($packed[$bytes]) & $mask) === (\ord($subnet_packed[$bytes]) & $mask);
	}

	/**
	 * The binary form of an IP address, or null when it is not one.
	 */
	public static function pack(string $ip): ?string
	{
		if (false === \filter_var($ip, \FILTER_VALIDATE_IP)) {
			return null;
		}

		$packed = \inet_pton($ip);

		return false === $packed ? null : $packed;
	}
}
