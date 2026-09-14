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
 * Class ClientInfo.
 *
 * What a request tells about its client and the address it used: client IP, host, origin.
 * Forwarding headers are only honoured from a trusted proxy (`oz.proxies`, see
 * {@see TrustedProxies}), so a client cannot choose these values. Computed once per request.
 */
final class ClientInfo
{
	private ?TrustedProxies $proxies = null;
	private ?string $host            = null;
	private string $host_with_port   = '';

	/**
	 * ClientInfo constructor.
	 */
	public function __construct(private readonly HTTPEnvironment $env, private readonly Request $request) {}

	/**
	 * The client IP address.
	 *
	 * The TCP peer (`REMOTE_ADDR`), unless it is a trusted proxy: the client is then read from
	 * the `OZ_CLIENT_IP_HEADER` header when configured, else from the forwarding headers, see
	 * {@see TrustedProxies::resolveClientIp()}.
	 *
	 * @param bool $with_port append the client port; only known when the client is the TCP peer
	 */
	public function ip(bool $with_port = false): ?string
	{
		$remote = $this->env('REMOTE_ADDR');

		if (null === $remote) {
			return null;
		}

		$client_ip     = null;
		$client_header = Settings::get('oz.request', 'OZ_CLIENT_IP_HEADER');

		if (\is_string($client_header) && '' !== $client_header) {
			$client_ip = $this->env('HTTP_' . \strtoupper(\str_replace('-', '_', $client_header)));
		}

		$ip = $this->proxies()->resolveClientIp(
			$remote,
			$this->env('HTTP_FORWARDED'),
			$this->env('HTTP_X_FORWARDED_FOR'),
			$client_ip
		);

		$port = $this->env('REMOTE_PORT');

		if ($with_port && $ip === $remote && null !== $port) {
			// IPv6 addresses are enclosed in brackets before appending the port.
			return (\str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . ':' . $port;
		}

		return $ip;
	}

	/**
	 * Whether the TCP peer is a trusted proxy (see `oz.proxies`).
	 */
	public function isFromTrustedProxy(): bool
	{
		$remote = $this->env('REMOTE_ADDR');

		return null !== $remote && $this->proxies()->isTrusted($remote);
	}

	/**
	 * The host the client requested: reported by a trusted proxy, else `Host`, the server
	 * name or address, else the default origin's.
	 *
	 * @param bool $with_port append the port, when not the default one
	 */
	public function host(bool $with_port = false): string
	{
		if (null === $this->host) {
			$remote = $this->env('REMOTE_ADDR');
			$found  = null === $remote ? null : $this->proxies()->resolveHost(
				$remote,
				$this->env('HTTP_FORWARDED'),
				$this->env('HTTP_X_FORWARDED_HOST')
			);

			$found ??= $this->env('HTTP_HOST') ?? $this->env('SERVER_NAME') ?? $this->env('SERVER_ADDR');
			$found ??= $this->defaultOrigin();

			if (!\preg_match('~^https?://~', $found)) {
				$found = 'https://' . $found;
			}

			$uri                  = Uri::createFromString($found);
			$port                 = $uri->getPort();
			$this->host           = $uri->getHost();
			$this->host_with_port = $this->host . ($port ? ':' . $port : '');
		}

		return $with_port ? $this->host_with_port : $this->host;
	}

	/**
	 * The default origin (`OZ_DEFAULT_ORIGIN`).
	 */
	public function defaultOrigin(): string
	{
		return (string) Uri::createFromString(Settings::get('oz.request', 'OZ_DEFAULT_ORIGIN'));
	}

	/**
	 * The request `Origin` header, when it is an http(s) origin.
	 *
	 * Unlike {@see self::originOrReferer()} it never falls back to the `Referer`: CORS
	 * decisions must only use the `Origin` the browser sets.
	 */
	public function origin(): ?string
	{
		$origin = $this->env('HTTP_ORIGIN');

		return (null !== $origin && \preg_match('~^https?://~', $origin)) ? $origin : null;
	}

	/**
	 * The request origin, else its referer, when http(s). Never trust it for security
	 * decisions.
	 */
	public function originOrReferer(): ?string
	{
		$origin = $this->env('HTTP_ORIGIN') ?? $this->env('HTTP_REFERER');

		// ignore android-app://com.google.android....
		return (null !== $origin && \preg_match('~^https?://~', $origin)) ? $origin : null;
	}

	/**
	 * The request header names allowed in CORS requests: the declared ones
	 * (`OZ_CORS_ALLOWED_HEADERS`, the API key, method override and form headers) and the
	 * ones the preflight asks for.
	 *
	 * @return list<string>
	 */
	public function corsAllowedHeaders(): array
	{
		$requested = $this->request->getHeaderLine('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
		$declared  = Settings::get('oz.request', 'OZ_CORS_ALLOWED_HEADERS');

		$declared[] = Settings::get('oz.auth', 'OZ_AUTH_API_KEY_HEADER_NAME');

		if (Settings::get('oz.request', 'OZ_REAL_METHOD_HEADER_ALLOWED')) {
			$declared[] = Settings::get('oz.request', 'OZ_REAL_METHOD_HEADER_NAME');
		}

		if (Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_ALLOWED')) {
			$declared[] = Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_NAME');
		}

		$declared[] = Settings::get('oz.request', 'OZ_FORM_RESUME_HEADER_NAME');
		$declared[] = Settings::get('oz.request', 'OZ_FORM_RESUME_REF_HEADER_NAME');
		$declared[] = Settings::get('oz.request', 'OZ_FORM_RESUME_ACTION_HEADER_NAME');

		$names = \array_merge($declared, '' === $requested ? [] : \explode(',', $requested));

		return \array_values(\array_unique(\array_map(
			static fn ($name): string => \strtolower(\trim((string) $name)),
			$names
		)));
	}

	private function proxies(): TrustedProxies
	{
		return $this->proxies ??= TrustedProxies::fromSettings();
	}

	/**
	 * A non-empty string value of the environment, or null.
	 */
	private function env(string $key): ?string
	{
		$value = $this->env->get($key);

		if (\is_int($value)) {
			$value = (string) $value;
		}

		return (\is_string($value) && '' !== $value) ? $value : null;
	}
}
