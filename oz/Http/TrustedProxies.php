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

use OZONE\Core\App\Settings;

/**
 * Class TrustedProxies.
 *
 * Trusted reverse proxies, from the `oz.proxies` settings group: a map of IP address
 * or CIDR range to `true` (trusted) or `false` (never trusted, overriding any range).
 *
 * Forwarding headers are only honoured when the TCP peer is a trusted proxy;
 * otherwise any client could choose its own IP (or host) by sending them.
 */
final class TrustedProxies
{
	/**
	 * TrustedProxies constructor.
	 *
	 * @param array<string, bool> $map IP address or CIDR range => trusted
	 */
	public function __construct(private readonly array $map) {}

	/**
	 * Builds the list from the `oz.proxies` settings group.
	 */
	public static function fromSettings(): self
	{
		// Read twice per request (client IP, URI): one instance per process, rebuilt when the
		// settings change.
		static $map = null, $instance = null;

		$current = Settings::load('oz.proxies');

		if ($current !== $map) {
			$map      = $current;
			$instance = new self($current);
		}

		return $instance;
	}

	/**
	 * Whether an address is a trusted proxy. An explicit `false` entry wins over a
	 * matching trusted range.
	 */
	public function isTrusted(string $ip): bool
	{
		$packed = self::pack($ip);

		if (null === $packed) {
			return false;
		}

		$trusted = false;

		foreach ($this->map as $entry => $allowed) {
			if (!self::matches($packed, (string) $entry)) {
				continue;
			}

			if (true !== $allowed) {
				return false;
			}

			$trusted = true;
		}

		return $trusted;
	}

	/**
	 * The client address of a request whose TCP peer is `$remote_addr`.
	 *
	 * When the peer is trusted, a valid `$client_ip` wins. Otherwise the forwarding chain
	 * (`Forwarded` `for=` values, else `X-Forwarded-For`) is read from right to left,
	 * skipping trusted proxies: the first untrusted hop is the client. The left-most
	 * entries are written by the client itself, so they are never taken at face value.
	 * An unparsable hop stops the walk at the nearest trusted proxy.
	 *
	 * @param string      $remote_addr     the TCP peer (`REMOTE_ADDR`)
	 * @param null|string $forwarded       the `Forwarded` header (RFC 7239)
	 * @param null|string $x_forwarded_for the `X-Forwarded-For` header
	 * @param null|string $client_ip       the header in which the proxy gives the client IP
	 *                                     directly (`OZ_CLIENT_IP_HEADER`, e.g. `CF-Connecting-IP`)
	 */
	public function resolveClientIp(
		string $remote_addr,
		?string $forwarded,
		?string $x_forwarded_for,
		?string $client_ip = null
	): string {
		$client = $remote_addr;

		if (!$this->isTrusted($remote_addr)) {
			return $client;
		}

		if (null !== $client_ip && null !== ($ip = self::cleanHop($client_ip))) {
			return $ip;
		}

		$chain = (null !== $forwarded && '' !== \trim($forwarded))
			? self::parseForwarded($forwarded)
			: \explode(',', (string) $x_forwarded_for);

		for ($i = \count($chain) - 1; $i >= 0; --$i) {
			$hop = self::cleanHop($chain[$i]);

			if (null === $hop) {
				break;
			}

			$client = $hop;

			if (!$this->isTrusted($hop)) {
				break;
			}
		}

		return $client;
	}

	/**
	 * The scheme the client used, as reported by a trusted proxy (`Forwarded` `proto=`,
	 * else `X-Forwarded-Proto`), or null when the peer is not trusted or reports none.
	 *
	 * The first reported value is taken: it comes from the proxy the client reached.
	 *
	 * @param string      $remote_addr       the TCP peer (`REMOTE_ADDR`)
	 * @param null|string $forwarded         the `Forwarded` header (RFC 7239)
	 * @param null|string $x_forwarded_proto the `X-Forwarded-Proto` header
	 *
	 * @return null|'http'|'https'
	 */
	public function resolveScheme(string $remote_addr, ?string $forwarded, ?string $x_forwarded_proto): ?string
	{
		if (!$this->isTrusted($remote_addr)) {
			return null;
		}

		$proto = '';

		if (null !== $forwarded) {
			foreach (self::parseForwarded($forwarded, 'proto') as $value) {
				if ('' !== $value) {
					$proto = $value;

					break;
				}
			}
		}

		if ('' === $proto && null !== $x_forwarded_proto) {
			$proto = \explode(',', $x_forwarded_proto)[0];
		}

		$proto = \strtolower(\trim($proto, " \t\""));

		return ('http' === $proto || 'https' === $proto) ? $proto : null;
	}

	/**
	 * The host (with its port, when given) the client requested, as reported by a trusted
	 * proxy (`Forwarded` `host=`, else `X-Forwarded-Host`), or null when the peer is not
	 * trusted or reports no valid host.
	 *
	 * The last reported value is taken: it comes from the nearest proxy, while earlier
	 * values may have been sent by the client. A spoofed host would end up in generated
	 * links (e.g. password reset URLs).
	 *
	 * @param string      $remote_addr      the TCP peer (`REMOTE_ADDR`)
	 * @param null|string $forwarded        the `Forwarded` header (RFC 7239)
	 * @param null|string $x_forwarded_host the `X-Forwarded-Host` header
	 */
	public function resolveHost(string $remote_addr, ?string $forwarded, ?string $x_forwarded_host): ?string
	{
		if (!$this->isTrusted($remote_addr)) {
			return null;
		}

		$host = '';

		if (null !== $forwarded) {
			foreach (\array_reverse(self::parseForwarded($forwarded, 'host')) as $value) {
				if ('' !== $value) {
					$host = $value;

					break;
				}
			}
		}

		if ('' === $host && null !== $x_forwarded_host) {
			$parts = \explode(',', $x_forwarded_host);
			$host  = (string) \end($parts);
		}

		$host = \strtolower(\trim($host, " \t\""));

		return \preg_match('~^(\[[0-9a-f:.]+]|[a-z0-9.-]+)(:\d{1,5})?$~', $host) ? $host : null;
	}

	/**
	 * Extracts a parameter (`for`, `proto`, ...) of each element of a `Forwarded`
	 * header, in order; an element without it gives an empty string.
	 *
	 * @return list<string>
	 */
	private static function parseForwarded(string $header, string $param = 'for'): array
	{
		$values = [];

		foreach (\explode(',', $header) as $element) {
			$found = '';

			foreach (\explode(';', $element) as $pair) {
				[$name, $value] = \array_pad(\explode('=', $pair, 2), 2, '');

				if ($param === \strtolower(\trim($name))) {
					$found = \trim($value);

					break;
				}
			}

			$values[] = $found;
		}

		return $values;
	}

	/**
	 * Reduces a forwarding hop (`1.2.3.4`, `1.2.3.4:5678`, `"[2001:db8::1]:443"`) to its
	 * IP address, or null when it is not one.
	 */
	private static function cleanHop(string $hop): ?string
	{
		$hop = \trim($hop, " \t\"");

		if (\preg_match('~^\[([^]]+)](?::\d+)?$~', $hop, $m)) {
			$hop = $m[1];
		} elseif (\preg_match('~^(\d{1,3}(?:\.\d{1,3}){3}):\d+$~', $hop, $m)) {
			$hop = $m[1];
		}

		return false !== \filter_var($hop, \FILTER_VALIDATE_IP) ? $hop : null;
	}

	/**
	 * Whether a packed address matches an entry: an address, or a CIDR range.
	 */
	private static function matches(string $packed, string $entry): bool
	{
		[$subnet, $bits] = \array_pad(\explode('/', $entry, 2), 2, null);

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
	private static function pack(string $ip): ?string
	{
		if (false === \filter_var($ip, \FILTER_VALIDATE_IP)) {
			return null;
		}

		$packed = \inet_pton($ip);

		return false === $packed ? null : $packed;
	}
}
