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
 * Class HTTPEnvironment.
 */
final class HTTPEnvironment extends Collection
{
	/**
	 * Creates mock HTTP environment.
	 *
	 * @param array $env Array of custom HTTP environment keys and values
	 */
	public static function mock(array $env = []): static
	{
		$env = \array_merge([
			'SERVER_PROTOCOL'      => 'HTTP/1.1',
			'REQUEST_METHOD'       => 'GET',
			'SCRIPT_NAME'          => '/index.php',
			'REQUEST_URI'          => '/',
			'QUERY_STRING'         => '',
			'SERVER_NAME'          => 'localhost',
			'SERVER_PORT'          => 80,
			'HTTP_HOST'            => 'localhost',
			'HTTP_ACCEPT'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
			'HTTP_ACCEPT_CHARSET'  => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
			'HTTP_USER_AGENT'      => OZ_OZONE_VERSION_NAME,
			'REMOTE_ADDR'          => '127.0.0.1',
			'REQUEST_TIME'         => \time(),
			'REQUEST_TIME_FLOAT'   => \microtime(true),
		], $env);

		return new self($env);
	}

	/**
	 * Builds the environment of a request a worker server received as parts rather than in
	 * `$_SERVER` (RoadRunner, Swoole, ReactPHP).
	 *
	 * The CGI variables OZone reads are derived from the parts: `REQUEST_METHOD`, `REQUEST_URI`,
	 * `QUERY_STRING`, `HTTP_HOST` / `SERVER_NAME` / `SERVER_PORT`, `HTTPS`, one `HTTP_*` per header,
	 * and `CONTENT_TYPE` / `CONTENT_LENGTH`. `$server` is merged last, for what only the server
	 * knows: `REMOTE_ADDR` and `REMOTE_PORT` (the TCP peer, which the trusted-proxy check relies
	 * on), `SERVER_PROTOCOL`, `REQUEST_TIME_FLOAT`.
	 *
	 * @param string                             $method  the request method
	 * @param string                             $uri     an absolute URL, or a path with its query
	 * @param array<string, list<string>|string> $headers the request headers, any name casing
	 * @param array<string, mixed>               $server  server variables, which win over the derived ones
	 */
	public static function fromParts(string $method, string $uri, array $headers, array $server = []): self
	{
		$parts = \parse_url($uri);
		$parts = \is_array($parts) ? $parts : [];
		$path  = ($parts['path'] ?? '') ?: '/';
		$query = $parts['query'] ?? '';
		$now   = \microtime(true);

		$env = [
			'SERVER_PROTOCOL'    => 'HTTP/1.1',
			'REQUEST_METHOD'     => \strtoupper($method),
			// The front controller, as PHP-FPM and FrankenPHP report it: the path is then routed as
			// is (see Uri::createFromEnvironment(), which strips a script name the path starts with).
			'SCRIPT_NAME'        => '/index.php',
			'REQUEST_URI'        => '' === $query ? $path : $path . '?' . $query,
			'QUERY_STRING'       => $query,
			'REQUEST_TIME'       => (int) $now,
			'REQUEST_TIME_FLOAT' => $now,
		];

		foreach ($headers as $name => $value) {
			$key   = \strtoupper(\str_replace('-', '_', (string) $name));
			$value = \is_array($value) ? \implode(', ', $value) : $value;

			if ('CONTENT_TYPE' === $key || 'CONTENT_LENGTH' === $key) {
				$env[$key] = $value;
			} else {
				$env['HTTP_' . $key] = $value;
			}
		}

		$scheme = $parts['scheme'] ?? null;
		$host   = $parts['host'] ?? null;

		if ('https' === $scheme) {
			$env['HTTPS'] = 'on';
		}

		if (null !== $host) {
			$env['SERVER_NAME'] = $host;
			$env['SERVER_PORT'] = $parts['port'] ?? ('https' === $scheme ? 443 : 80);
			$env['HTTP_HOST'] ??= isset($parts['port']) ? $host . ':' . $parts['port'] : $host;
		} else {
			// A path alone: the host is the client's `Host` header (an HTTP/1.0 client may send none).
			$host_header        = $env['HTTP_HOST'] ?? null;
			$env['SERVER_NAME'] = \is_string($host_header) && '' !== $host_header
				? (string) \preg_replace('~:\d+\z~', '', $host_header)
				: 'localhost';
		}

		// A value the server does not know is left out rather than set to null.
		$server = \array_filter($server, static fn (mixed $value): bool => null !== $value);

		return new self(\array_merge($env, $server));
	}
}
