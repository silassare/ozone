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

namespace OZONE\Core\Auth\Providers;

use OZONE\Core\App\Context;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class AuthUserAuthorizationProvider.
 */
class AuthUserAuthorizationProvider extends AuthorizationProvider
{
	public const NAME = 'auth:provider:user';

	/**
	 * AuthUserAuthorizationProvider constructor.
	 */
	public function __construct(Context $context, protected AuthUserInterface $user)
	{
		parent::__construct($context);
	}

	/**
	 * Gets the user.
	 *
	 * @return AuthUserInterface
	 */
	public function getUser(): AuthUserInterface
	{
		return $this->user;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function resolve(Context $context, OZAuth $auth): self
	{
		$user = AuthUsers::identifyBySelector([
			AuthUsers::FIELD_AUTH_USER_TYPE => $auth->getOwnerType(),
			AuthUsers::FIELD_AUTH_USER_ID   => $auth->getOwnerId(),
		]);

		if (!$user) {
			throw (new RuntimeException('Unable to identify the owner of this auth key.'))->suspectObject($auth);
		}

		return new self($context, $user);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getName(): string
	{
		return self::NAME;
	}
}
