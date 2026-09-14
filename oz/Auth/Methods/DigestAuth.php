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

namespace OZONE\Core\Auth\Methods;

use Override;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\LoginThrottle;
use OZONE\Core\Auth\Traits\AskCredentialsByHTTPHeaderTrait;
use OZONE\Core\Auth\Traits\AuthUserKeyAuthenticationMethodTrait;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Stores\KeyValueStore;
use OZONE\Core\Stores\StateRegistry;
use OZONE\Core\Utils\Hasher;

/**
 * Class DigestAuth.
 *
 * Nonces are `issued_at.random.mac`, the MAC being an HMAC (app secret) of the issue time,
 * the random part and the realm: the server recognizes the nonces it issued without storing
 * them, and accepts them for `OZ_AUTH_DIGEST_NONCE_LIFETIME` seconds. An expired nonce gets
 * a new challenge flagged `stale=true`. Each nonce is accepted once (RFC 2617: once per
 * increasing `nc`), which the `oz:auth:digest:nonces` store tracks until the nonce expires,
 * so a captured `Authorization` header cannot be replayed.
 */
class DigestAuth implements AuthenticationMethodInterface
{
	use AskCredentialsByHTTPHeaderTrait;
	use AuthUserKeyAuthenticationMethodTrait;

	public const NONCE_CACHE_NAMESPACE = 'oz:auth:digest:nonces';

	protected AuthenticationMethodScheme $scheme;

	protected string $digest = '';
	protected string $nonce;
	protected string $opaque;

	/**
	 * Whether the client's nonce expired: the next challenge says so (`stale=true`), so the
	 * client can retry with the new nonce without asking the user again.
	 */
	protected bool $stale = false;

	/**
	 * DigestAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm, protected bool $rfc2617 = false)
	{
		$this->scheme = $this->rfc2617
			? AuthenticationMethodScheme::DIGEST_RFC_2617
			: AuthenticationMethodScheme::DIGEST;
		$this->newKeys();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self($ri, $realm);
	}

	/**
	 * Returns the digest.
	 *
	 * @return string
	 */
	public function getDigest(): string
	{
		return $this->digest;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function satisfied(): bool
	{
		$context       = $this->ri->getContext();
		$request       = $context->getRequest();
		$header_line   = $request->getHeaderLine('Authorization');

		if (empty($header_line) || !\str_starts_with(\strtolower($header_line), 'digest ')) {
			return false;
		}

		$env        = $context->getHTTPEnvironment();
		$req_digest = $env->get('PHP_AUTH_DIGEST');

		if (empty($req_digest)) {
			$req_digest = \explode(' ', $header_line, 2)[1];
		}

		if ($this->digestProperties($req_digest)) {
			$this->digest = $req_digest;

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedException when the nonce expired (a new, `stale` challenge)
	 */
	#[Override]
	public function authenticate(): void
	{
		if (empty($this->digest)) {
			throw new ForbiddenException();
		}

		$parsed = $this->digestProperties($this->digest);

		if (!$parsed) {
			// invalid digest
			throw new ForbiddenException();
		}

		$context    = $this->ri->getContext();
		$req_method = $context->getRequest()
			->getMethod();

		$username = (string) $parsed['username'];

		if (!\str_contains($username, ':')) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid username.',
			]);
		}

		[$auth_user_ref, $auth_key_ref] = \explode(':', $username, 2);

		// A digest response can be guessed like a password: same lockout, per auth key.
		$subject = 'digest:' . $auth_key_ref;

		if (LoginThrottle::isLocked($subject)) {
			throw new ForbiddenException(null, [
				'_reason' => 'OZ_AUTH_TOO_MUCH_ATTEMPT',
			]);
		}

		$nonce     = (string) $parsed['nonce'];
		$issued_at = $this->verifyNonce($nonce);

		if (null === $issued_at) {
			$this->reject($subject, [
				'_reason' => 'Invalid digest nonce.',
			]);
		}

		if (\time() - $issued_at > self::nonceLifetime()) {
			// A nonce we issued, expired: challenge again with a new one.
			$this->newKeys();
			$this->stale = true;
			$this->ask();
		}

		$nc = $this->rfc2617 ? self::parseNonceCount((string) $parsed['nc']) : 1;

		if (null === $nc) {
			$this->reject($subject, [
				'_reason' => 'Invalid digest nonce count.',
			]);
		}

		$nonce_key = self::nonceKey($nonce);

		if ($nc <= (int) self::nonceStore()->get($nonce_key, 0)) {
			$this->reject($subject, [
				'_reason' => 'Digest nonce already used.',
			]);
		}

		$auth = Auth::get($auth_key_ref);

		if (!$auth) {
			$this->reject($subject, [
				'_reason'   => 'Invalid auth ref.',
				'_auth_ref' => $auth_key_ref,
			]);
		}

		$selector = AuthUsers::refToSelector($auth_user_ref);

		if (!$selector) {
			// invalid username
			$this->reject($subject, [
				'_reason' => 'Invalid username.',
			]);
		}

		$user = AuthUsers::identifyBySelector($selector);

		if (!$user) {
			// invalid username
			$this->reject($subject, [
				'_reason' => 'Invalid username.',
			]);
		}

		$this->authenticateWithAuthEntity($auth, $user);

		$known_key = $auth->getTokenHash();

		$A1 = \md5($username . ':' . $this->realm . ':' . $known_key);
		$A2 = \md5($req_method . ':' . $parsed['uri']);

		if ($this->rfc2617) {
			$expected_response = \md5(
				$A1
					. ':' . $nonce
					. ':' . $parsed['nc']
					. ':' . $parsed['cnonce']
					. ':' . $parsed['qop']
					. ':' . $A2
			);
		} else {
			$expected_response = \md5($A1 . ':' . $nonce . ':' . $A2);
		}

		if (!\hash_equals($expected_response, (string) $parsed['response'])) {
			// invalid digest response
			$this->reject($subject, [
				'_reason' => 'Invalid digest response.',
			]);
		}

		// Consumed only once the response is verified: a wrong response must not burn the
		// nonce of the real client.
		self::nonceStore()->set($nonce_key, $nc, \max(1, $issued_at + self::nonceLifetime() - \time()));

		LoginThrottle::clear($subject);
	}

	/**
	 * Generates new keys.
	 */
	protected function newKeys(): void
	{
		$this->nonce  = self::buildNonce($this->realm, \time());
		$this->opaque = Hasher::hash32($this->realm);
	}

	/**
	 * Builds a nonce issued at the given time for the realm.
	 */
	protected static function buildNonce(string $realm, int $issued_at): string
	{
		$payload = $issued_at . '.' . \bin2hex(\random_bytes(8));

		return $payload . '.' . self::nonceMac($payload, $realm);
	}

	/**
	 * The issue time of a nonce this server issued for the realm, or null for any other.
	 */
	protected function verifyNonce(string $nonce): ?int
	{
		$parts = \explode('.', $nonce);

		if (3 !== \count($parts) || !\ctype_digit($parts[0])) {
			return null;
		}

		[$issued_at, $random, $mac] = $parts;

		if (!\hash_equals(self::nonceMac($issued_at . '.' . $random, $this->realm), $mac)) {
			return null;
		}

		return (int) $issued_at;
	}

	/**
	 * The store tracking the highest nonce count accepted per nonce.
	 */
	protected static function nonceStore(): KeyValueStore
	{
		return StateRegistry::store(self::NONCE_CACHE_NAMESPACE);
	}

	/**
	 * The {@see self::nonceStore()} key of a nonce.
	 */
	protected static function nonceKey(string $nonce): string
	{
		return 'nonce:' . Hasher::hash32($nonce);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function askHeader(): string
	{
		$stale = $this->stale ? ',stale=true' : '';

		if ($this->rfc2617) {
			return \sprintf(
				'Digest realm="%s",qop="auth",nonce="%s",opaque="%s"%s',
				$this->realm,
				$this->nonce,
				$this->opaque,
				$stale
			);
		}

		return \sprintf(
			'Digest realm="%s",nonce="%s",opaque="%s"%s',
			$this->realm,
			$this->nonce,
			$this->opaque,
			$stale
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function askInfo(): array
	{
		return [
			'scheme' => $this->scheme->value,
			'realm'  => $this->realm,
			'nonce'  => $this->nonce,
			'opaque' => $this->opaque,
			'stale'  => $this->stale,
		];
	}

	/**
	 * Parse digest auth header string.
	 *
	 * @param string $digest
	 *
	 * @return array|false
	 */
	protected function digestProperties(string $digest): array|false
	{
		$required_props = [
			'nonce',
			'username',
			'uri',
			'response',
		];

		if ($this->rfc2617) {
			$required_props[] = 'nc';
			$required_props[] = 'cnonce';
			$required_props[] = 'qop';
		}

		$data    = [];
		$keys    = \implode('|', $required_props);
		$missing = \array_fill_keys($required_props, 1);

		\preg_match_all('~(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))~', $digest, $matches, \PREG_SET_ORDER);

		foreach ($matches as $m) {
			// An unquoted value (`nc`, `qop`) leaves the quoted group set to an empty string.
			$data[$m[1]] = '' !== $m[2] ? $m[3] : ($m[4] ?? '');
			unset($missing[$m[1]]);
		}

		// return false if there are missing props
		return $missing ? false : $data;
	}

	/**
	 * Counts a failed attempt against the throttle subject, then rejects the request.
	 *
	 * @throws ForbiddenException
	 */
	private function reject(string $subject, array $data): never
	{
		LoginThrottle::recordFailure($subject);

		throw new ForbiddenException(null, $data);
	}

	/**
	 * The RFC 2617 nonce count (8 hex digits), or null when malformed.
	 */
	private static function parseNonceCount(string $nc): ?int
	{
		return (8 === \strlen($nc) && \ctype_xdigit($nc)) ? (int) \hexdec($nc) : null;
	}

	private static function nonceMac(string $payload, string $realm): string
	{
		return \hash_hmac('sha256', $payload . '.' . $realm, Keys::secret());
	}

	private static function nonceLifetime(): int
	{
		return (int) Settings::get('oz.auth', 'OZ_AUTH_DIGEST_NONCE_LIFETIME', 300);
	}
}
