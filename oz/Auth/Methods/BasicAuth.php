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
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Traits\AskCredentialsByHTTPHeaderTrait;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class BasicAuth.
 *
 * @psalm-suppress RedundantPropertyInitializationCheck
 */
class BasicAuth implements AuthenticationMethodInterface
{
	use AskCredentialsByHTTPHeaderTrait;

	public const BASIC_AUTH_SEPARATOR               = ':';
	public const BASIC_AUTH_USERNAME_INFO_SEPARATOR = '|';

	protected AuthenticationMethodScheme $scheme = AuthenticationMethodScheme::BASIC;

	/**
	 * @var string The username
	 *
	 * Should be in this format: `auth_user_type|auth_user_identifier_type|auth_user_identifier_value`
	 */
	protected string $username = '';
	protected string $password = '';
	protected AuthUserInterface $user;

	/**
	 * BasicAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self($ri, $realm);
	}

	/**
	 * Returns the username.
	 *
	 * @return string
	 */
	public function getUsername(): string
	{
		return $this->username;
	}

	/**
	 * Returns the password.
	 *
	 * @return string
	 */
	public function getPassword(): string
	{
		return $this->password;
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

		if (empty($header_line) || !\str_starts_with(\strtolower($header_line), 'basic ')) {
			return false;
		}

		$env          = $context->getHTTPEnvironment();
		$req_user     = $env->get('PHP_AUTH_USER');
		$req_password = $env->get('PHP_AUTH_PW');

		if (null !== $req_user && null !== $req_password) {
			$this->username = $req_user;
			$this->password = $req_password;

			return true;
		}

		$decoded = \base64_decode(\substr($header_line, 6), true);
		$parts   = \explode(self::BASIC_AUTH_SEPARATOR, $decoded, 2);

		if (2 === \count($parts)) {
			$this->username = $parts[0];
			$this->password = $parts[1];

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	#[Override]
	public function authenticate(): void
	{
		$selector = AuthUsers::refToSelector($this->username, self::BASIC_AUTH_USERNAME_INFO_SEPARATOR);

		if (false === $selector) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid username format, expected '
					. '"auth_user_type|auth_user_identifier_type|auth_user_identifier_value".',
			]);
		}

		$user = AuthUsers::identifyBySelector($selector);

		// Same brute-force protection as the login route.
		$error = AuthUsers::checkPassword($user, $this->password, $this->username);

		if (null !== $error || null === $user) {
			throw new ForbiddenException(null, [
				'_reason' => $error ?? 'OZ_AUTH_INVALID_CREDENTIALS',
			]);
		}

		$this->user = $user;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	#[Override]
	public function user(): AuthUserInterface
	{
		if (!isset($this->user)) {
			$this->authenticate();
		}

		return $this->user;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	#[Override]
	public function getAccessRights(): AccessRightsInterface
	{
		return $this->user()->getAuthUserDataStore()->getAuthUserAccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isScoped(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function askHeader(): string
	{
		return 'Basic realm="' . $this->realm . '"';
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
		];
	}
}
