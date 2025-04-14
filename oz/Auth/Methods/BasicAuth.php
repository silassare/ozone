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

use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Traits\AskCredentialsByHTTPHeaderTrait;
use OZONE\Core\Crypt\Password;
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
	 * Should be in this format: `auth_user_type|auth_user_identifier_name|auth_user_identifier_value`
	 */
	protected string $username = '';
	protected string $password = '';
	protected AuthUserInterface $user;

	/**
	 * BasicAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm) {}

	/**
	 * BasicAuth destructor.
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
	public function authenticate(): void
	{
		$selector = AuthUsers::refToSelector($this->username, self::BASIC_AUTH_USERNAME_INFO_SEPARATOR);

		if (false === $selector) {
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid username format. Use auth_user_type|auth_user_identifier_name|auth_user_identifier_value for username.',
			]);
		}

		$user = AuthUsers::identifyBySelector($selector);

		if (!$user) {
			// unknown user
			throw new ForbiddenException(null, [
				'_reason' => 'Unknown auth user.',
			]);
		}
		if (!$user->isAuthUserValid()) {
			throw new ForbiddenException(null, [
				'_reason' => 'Disabled auth user.',
				'_user'   => AuthUsers::selector($user),
			]);
		}

		if (!Password::verify($this->password, $user->getAuthPassword())) {
			// invalid password
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid auth user password.',
			]);
		}

		$this->user = $user;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
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
	public function getAccessRights(): AccessRightsInterface
	{
		return $this->user()->getAuthUserDataStore()->getAuthUserAccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isScoped(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function askHeader(): string
	{
		return 'Basic realm="' . $this->realm . '"';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function askInfo(): array
	{
		return [
			'scheme' => $this->scheme->value,
			'realm'  => $this->realm,
		];
	}
}
