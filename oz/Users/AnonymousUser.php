<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\Users;

use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Crypt\Password;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AnonymousUser.
 *
 * @internal
 */
final class AnonymousUser implements AuthUserInterface
{
	use ArrayCapableTrait;

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserTypeName(): string
	{
		return 'anonymous';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthIdentifier(): string
	{
		return 'anonymous';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthIdentifiers(): array
	{
		return [
			self::IDENTIFIER_NAME_ID => 'anonymous',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthPassword(): string
	{
		return Password::hash('anonymous');
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAuthPassword(string $password_hash): self
	{
		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserDataStore(): AuthUserDataStore
	{
		return AuthUserDataStore::getInstance($this, []);
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAuthUserDataStore(AuthUserDataStore $store): self
	{
		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function save(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'id' => 'anonymous',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAuthUserVerified(): bool
	{
		return false;
	}
}
