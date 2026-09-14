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

namespace OZONE\Core\CSRF;

use OZONE\Core\App\Context;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Methods\SessionAuth;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\RouteSharedOptions;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Utils\Random;

/**
 * Class CSRF.
 *
 * Tokens are `id.issued_at.mac`: the MAC (keyed by the app secret) binds the random id
 * and the issue time to the scope, and a token is only accepted for
 * `OZ_CSRF_TOKEN_LIFETIME` seconds after it was issued.
 *
 * Unsafe requests authenticated by the session cookie need a (session-scoped) token by
 * default, see {@see self::isRequiredByDefault()}. Pages get it from the `csrf_token`
 * template global; scripts from the `XSRF-TOKEN` cookie (`OZ_CSRF_COOKIE_NAME`), to send
 * back in the `X-XSRF-TOKEN` header.
 */
class CSRF
{
	public const TOKEN_SEP    = '.';
	public const TOKEN_PARAM  = '_csrf';
	public const TOKEN_HEADER = 'X-XSRF-TOKEN';

	/**
	 * Tolerated clock difference, in seconds, between the server that issued a token
	 * and the one checking it.
	 */
	private const CLOCK_SKEW = 60;

	private string $scope_ref;

	/**
	 * CSRF constructor.
	 *
	 * @param null|string $scope_ref the scope ID when already known, else resolved from `$scope`
	 */
	public function __construct(private Context $context, RequestScope $scope, ?string $scope_ref = null)
	{
		$this->scope_ref = $scope_ref ?? $scope->resolveId($context);
	}

	/**
	 * Whether a request needs a session-scoped CSRF token without the route asking for one:
	 * `OZ_CSRF_SESSION_DEFAULT` is on, the method is unsafe, the request is authenticated by
	 * the session and carries its cookie, and the route neither opted out
	 * ({@see RouteSharedOptions::withoutCSRF()}) nor declared its own check
	 * ({@see RouteSharedOptions::withCSRF()}).
	 *
	 * Without the session cookie there is no session to ride, so first contacts and
	 * non-browser clients are not concerned; bearer and API-key requests never are.
	 */
	public static function isRequiredByDefault(
		RouteSharedOptions $options,
		Context $context,
		?AuthenticationMethodInterface $auth
	): bool {
		$request = $context->getRequest();

		if (
			!$auth instanceof SessionAuth
			|| !Settings::get('oz.request', 'OZ_CSRF_SESSION_DEFAULT', true)
			|| \in_array(\strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)
			|| null !== $options->getCSRFScope()
			|| $options->isCSRFDisabled()
		) {
			return false;
		}

		$sid = $request->getCookieParam(Session::cookieName());

		return \is_string($sid) && '' !== $sid;
	}

	/**
	 * The cookie handing the session token to scripts (`OZ_CSRF_COOKIE_NAME`), or null when
	 * disabled.
	 */
	public static function cookieName(): ?string
	{
		$name = Settings::get('oz.request', 'OZ_CSRF_COOKIE_NAME', 'XSRF-TOKEN');

		return (\is_string($name) && '' !== $name) ? $name : null;
	}

	/**
	 * Check a csrf token validity in a given route.
	 *
	 * The token is read from the `_csrf` form field, else the `X-XSRF-TOKEN` header.
	 *
	 * @param RouteInfo $ri
	 *
	 * @return bool
	 */
	public function check(RouteInfo $ri): bool
	{
		$token = $ri->getUnsafeFormData()->get(self::TOKEN_PARAM);

		if (!\is_string($token) || '' === $token) {
			$token = $this->context->getRequest()->getHeaderLine(self::TOKEN_HEADER);
		}

		return $this->isValid($token);
	}

	/**
	 * Whether a token was issued for this scope and has not expired.
	 *
	 * @param string   $token the token to check
	 * @param null|int $now   the current time (defaults to `time()`)
	 */
	public function isValid(string $token, ?int $now = null): bool
	{
		$parts = \explode(self::TOKEN_SEP, $token);

		if (3 !== \count($parts)) {
			return false;
		}

		[$id, $issued_at, $mac] = $parts;

		if ('' === $id || '' === $mac || !\ctype_digit($issued_at)) {
			return false;
		}

		$now ??= \time();
		$age   = $now - (int) $issued_at;

		if ($age < -self::CLOCK_SKEW || $age > self::lifetime()) {
			return false;
		}

		return \hash_equals($this->mac($id, $issued_at), $mac);
	}

	/**
	 * Generate a new CSRF Token.
	 *
	 * @return string
	 */
	public function generateToken(): string
	{
		$id        = Random::alphaNum(16);
		$issued_at = (string) \time();

		return $id . self::TOKEN_SEP . $issued_at . self::TOKEN_SEP . $this->mac($id, $issued_at);
	}

	private function mac(string $id, string $issued_at): string
	{
		return \hash_hmac(
			'sha256',
			$id . self::TOKEN_SEP . $issued_at . self::TOKEN_SEP . $this->scope_ref,
			Keys::secret()
		);
	}

	private static function lifetime(): int
	{
		return (int) Settings::get('oz.request', 'OZ_CSRF_TOKEN_LIFETIME', 86400);
	}
}
