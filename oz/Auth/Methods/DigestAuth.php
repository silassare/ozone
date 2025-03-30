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

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Traits\AskCredentialsByHTTPHeaderTrait;
use OZONE\Core\Auth\Traits\AuthUserKeyAuthenticationMethodTrait;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Utils\Hasher;

/**
 * Class DigestAuth.
 */
class DigestAuth implements AuthenticationMethodInterface
{
	use AskCredentialsByHTTPHeaderTrait;
	use AuthUserKeyAuthenticationMethodTrait;

	protected AuthenticationMethodScheme $scheme;

	protected string $digest = '';
	protected string $nonce;
	protected string $opaque;

	/**
	 * DigestAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm, protected bool $rfc2617 = false)
	{
		$this->scheme = $this->rfc2617 ? AuthenticationMethodScheme::DIGEST_RFC_2617 : AuthenticationMethodScheme::DIGEST;
		$this->newKeys();
	}

	/**
	 * DigestAuth destructor.
	 */
	public function __destruct()
	{
		unset($this->ri);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, string $realm): self
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
	 * @throws UnauthorizedActionException
	 */
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

		$username = $parsed['username'];

		[$auth_user_ref, $auth_key_ref] = \explode(':', $username, 2);

		$auth = Auth::get($auth_key_ref);

		if (!$auth) {
			throw new ForbiddenException(null, [
				'_reason'   => 'Invalid auth ref.',
				'_auth_ref' => $auth_key_ref,
			]);
		}

		$selector = AuthUsers::refToSelector($auth_user_ref);

		if (!$selector) {
			// invalid username
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid username.',
			]);
		}

		$user = AuthUsers::identifyBySelector($selector);

		if (!$user) {
			// invalid username
			throw new ForbiddenException(null, [
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
				. ':' . $parsed['nonce']
				. ':' . $parsed['nc']
				. ':' . $parsed['cnonce']
				. ':' . $parsed['qop']
				. ':' . $A2
			);
		} else {
			$expected_response = \md5($A1 . ':' . $parsed['nonce'] . ':' . $A2);
		}

		if ($expected_response !== $parsed['response']) {
			// invalid digest response
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid digest response.',
			]);
		}
	}

	/**
	 * Generates new keys.
	 */
	protected function newKeys(): void
	{
		$this->nonce  = Hasher::hash32();
		$this->opaque = Hasher::hash32($this->realm);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function askHeader(): string
	{
		if ($this->rfc2617) {
			return \sprintf(
				'Digest realm="%s",qop="auth",nonce="%s",opaque="%s"',
				$this->realm,
				$this->nonce,
				$this->opaque
			);
		}

		return \sprintf(
			'Digest realm="%s",nonce="%s",opaque="%s"',
			$this->realm,
			$this->nonce,
			$this->opaque
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function askInfo(): array
	{
		return [
			'scheme' => $this->scheme->value,
			'realm'  => $this->realm,
			'nonce'  => $this->nonce,
			'opaque' => $this->opaque,
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
			$data[$m[1]] = $m[3] ?: $m[4];
			unset($missing[$m[1]]);
		}

		// return false if there are missing props
		return $missing ? false : $data;
	}
}
