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

namespace OZONE\Core\Users;

use Override;
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
	#[Override]
	public function getAuthUserType(): string
	{
		return 'anonymous';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAuthIdentifier(): string
	{
		return 'anonymous';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAuthIdentifiers(): array
	{
		return [
			self::IDENTIFIER_NAME_ID => 'anonymous',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAuthPassword(): string
	{
		return Password::hash('anonymous');
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setAuthPassword(string $password_hash): self
	{
		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAuthUserDataStore(): AuthUserDataStore
	{
		return AuthUserDataStore::getInstance($this, []);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setAuthUserDataStore(AuthUserDataStore $store): self
	{
		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function save(): bool
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'id' => 'anonymous',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAuthUserValid(): bool
	{
		return false;
	}
}
