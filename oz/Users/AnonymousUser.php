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
use SensitiveParameter;

/**
 * Class AnonymousUser.
 *
 * @internal
 */
final class AnonymousUser implements AuthUserInterface
{
	use ArrayCapableTrait;

	#[Override]
	public function getAuthUserType(): string
	{
		return 'anonymous';
	}

	#[Override]
	public function getAuthIdentifier(): string
	{
		return 'anonymous';
	}

	#[Override]
	public function getAuthIdentifiers(): array
	{
		return [
			self::IDENTIFIER_TYPE_ID => 'anonymous',
		];
	}

	#[Override]
	public function getAuthPassword(): string
	{
		return Password::hash('anonymous');
	}

	#[Override]
	public function setAuthPassword(#[SensitiveParameter] string $password_hash): static
	{
		return $this;
	}

	#[Override]
	public function getAuthUserDataStore(): AuthUserDataStore
	{
		return AuthUserDataStore::getInstance($this, []);
	}

	#[Override]
	public function setAuthUserDataStore(AuthUserDataStore $store): static
	{
		return $this;
	}

	#[Override]
	public function save(): bool
	{
		return true;
	}

	#[Override]
	public function toArray(): array
	{
		return [
			'id' => 'anonymous',
		];
	}

	#[Override]
	public function isAuthUserValid(): bool
	{
		return false;
	}
}
