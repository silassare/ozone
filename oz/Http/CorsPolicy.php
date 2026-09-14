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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use Throwable;

/**
 * Class CorsPolicy.
 *
 * Decides which origins may read responses cross-origin, from the
 * `OZ_CORS_ALLOWED_ORIGIN` setting:
 *
 * - `'self'` (default): only the app's own origin (`OZ_DEFAULT_ORIGIN`);
 * - an origin, or a list of origins (`'self'` allowed as an entry): those origins,
 *   with credentials;
 * - `'*'`: any origin, but without credentials, as browsers require.
 *
 * The request's own origin is always allowed: a same-origin request is not
 * cross-origin. Origins are compared on scheme, host and port.
 */
final class CorsPolicy
{
	/**
	 * CorsPolicy constructor.
	 *
	 * @param null|list<string> $allowed normalized origins; null allows any origin, without credentials
	 */
	public function __construct(private readonly ?array $allowed) {}

	/**
	 * Builds the policy of the current request.
	 */
	public static function fromContext(Context $context): self
	{
		return self::fromSetting(
			Settings::get('oz.request', 'OZ_CORS_ALLOWED_ORIGIN', 'self'),
			(string) $context->getRequest()->getUri(),
			$context->getDefaultOrigin()
		);
	}

	/**
	 * Builds a policy from an `OZ_CORS_ALLOWED_ORIGIN` value.
	 *
	 * @param list<string>|string $setting        the setting value
	 * @param string              $request_url    the request URL, whose origin is always allowed
	 * @param string              $default_origin what `'self'` stands for
	 */
	public static function fromSetting(array|string $setting, string $request_url, string $default_origin): self
	{
		$entries = \is_array($setting) ? $setting : [$setting];

		if (\in_array('*', $entries, true)) {
			return new self(null);
		}

		$allowed = [self::normalize($request_url)];

		foreach ($entries as $entry) {
			$allowed[] = self::normalize('self' === $entry ? $default_origin : (string) $entry);
		}

		return new self(\array_values(\array_unique(\array_filter($allowed))));
	}

	/**
	 * Reduces a URL to its origin (`scheme://host[:port]`, default ports omitted),
	 * or null when it is not an http(s) URL.
	 */
	public static function normalize(string $url): ?string
	{
		try {
			$uri = Uri::createFromString($url);
		} catch (Throwable) {
			return null;
		}

		$scheme = \strtolower($uri->getScheme());
		$host   = \strtolower($uri->getHost());

		if (('http' !== $scheme && 'https' !== $scheme) || '' === $host) {
			return null;
		}

		$port = $uri->getPort();

		return $scheme . '://' . $host . (null === $port ? '' : ':' . $port);
	}

	/**
	 * Whether any origin is allowed (`'*'`).
	 */
	public function allowsAnyOrigin(): bool
	{
		return null === $this->allowed;
	}

	/**
	 * Whether a request carrying this `Origin` may be served.
	 */
	public function allows(string $origin): bool
	{
		if (null === $this->allowed) {
			return true;
		}

		$normalized = self::normalize($origin);

		return null !== $normalized && \in_array($normalized, $this->allowed, true);
	}

	/**
	 * CORS headers for the response to a request carrying this `Origin` (or none).
	 *
	 * An allowed origin is reflected with credentials; a disallowed one gets no
	 * `Access-Control-Allow-Origin`, so the browser withholds the response.
	 *
	 * @return array<string, string>
	 */
	public function headers(?string $origin): array
	{
		if (null === $this->allowed) {
			return ['Access-Control-Allow-Origin' => '*'];
		}

		$headers = ['Vary' => 'Origin'];

		if (null !== $origin && $this->allows($origin)) {
			$headers['Access-Control-Allow-Origin']      = $origin;
			$headers['Access-Control-Allow-Credentials'] = 'true';
		}

		return $headers;
	}
}
