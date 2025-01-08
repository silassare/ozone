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

use InvalidArgumentException;
use OZONE\Core\App\Context;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Users\Users;

/**
 * Class UserAuthProvider.
 */
class UserAuthProvider extends AuthProvider
{
	public const NAME = 'auth:provider:user';

	/**
	 * UserAuthProvider constructor.
	 *
	 * @param Context $context
	 * @param OZUser  $user
	 */
	public function __construct(Context $context, protected OZUser $user)
	{
		parent::__construct($context);
	}

	/**
	 * Gets the user.
	 *
	 * @return OZUser
	 */
	public function getUser(): OZUser
	{
		return $this->user;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(Context $context, array $payload): self
	{
		$id = $payload['user_id'] ?? null;

		if (empty($id)) {
			throw new InvalidArgumentException('Missing "user_id" in payload.');
		}

		$user = Users::identify($id);

		if (!$user) {
			throw new RuntimeException('Unable to load user using provided "user_id".', $payload);
		}

		return new self($context, $user);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [
			'user_id' => $this->user->getID(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getName(): string
	{
		return self::NAME;
	}
}
