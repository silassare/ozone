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

use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthMethodInterface;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Users\Users;

/**
 * Class BasicAuth.
 *
 * @psalm-suppress RedundantPropertyInitializationCheck
 */
class BasicAuth implements AuthMethodInterface
{
	use HTTPAuthMethodTrait;

	protected AuthMethodType $type     = AuthMethodType::BASIC;
	protected string         $username = '';
	protected string         $password = '';
	protected OZUser         $user;

	/**
	 * BasicAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm)
	{
	}

	/**
	 * BasicAuth destructor.
	 */
	public function __destruct()
	{
		unset($this->ri);
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
		$authorization = $request->getHeaderLine('Authorization');

		if (empty($authorization) || !\str_starts_with(\strtolower($authorization), 'basic ')) {
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

		$parts = \explode(':', \base64_decode(\substr($authorization, 6), true), 2);

		if (2 === \count($parts)) {
			$this->username = $parts[0];
			$this->password = $parts[1];

			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, string $realm): self
	{
		return new self($ri, $realm);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 */
	public function authenticate(): void
	{
		$user = Users::identify($this->username);

		if (!$user) {
			// unknown user
			throw new ForbiddenException(null, [
				'_reason' => 'Unknown user.',
			]);
		}
		if (!$user->isValid()) {
			throw new ForbiddenException(null, [
				'_reason' => 'Disabled user.',
			]);
		}

		if (!Password::verify($this->password, $user->getPass())) {
			// invalid password
			throw new ForbiddenException(null, [
				'_reason' => 'Invalid password.',
			]);
		}

		$this->user = $user;
	}

	/**
	 * {@inheritDoc}
	 */
	public function user(): OZUser
	{
		if (!isset($this->user)) {
			$this->authenticate();
		}

		return $this->user;
	}

	/**
	 * {@inheritDoc}
	 */
	public function accessRights(): AuthAccessRightsInterface
	{
		return $this->user()
			->getAccessRights();
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
			'type'  => $this->type->value,
			'realm' => $this->realm,
		];
	}
}
