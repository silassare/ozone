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

namespace OZONE\Tests\Support;

use Override;
use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;

/**
 * Class TestAuthUser.
 *
 * An auth user of no repository, for what OZone must do without knowing the entity: it holds the
 * type and the identifier a caller gives it and nothing else. A framework that spoke `OZUser` here
 * would be tested against the one entity it is not allowed to assume.
 */
final class TestAuthUser implements AuthUserInterface
{
	private ?AuthUserDataStore $data_store = null;

	public function __construct(
		private readonly string $type = 'user',
		private readonly string $id = '1',
		private readonly string $hash = '',
		private readonly bool $valid = true,
	) {}

	#[Override]
	public function getAuthUserType(): string
	{
		return $this->type;
	}

	#[Override]
	public function getAuthIdentifier(): string
	{
		return $this->id;
	}

	#[Override]
	public function getAuthIdentifiers(): array
	{
		return [];
	}

	#[Override]
	public function getAuthPassword(): string
	{
		return $this->hash;
	}

	#[Override]
	public function setAuthPassword(string $password_hash): static
	{
		return $this;
	}

	#[Override]
	public function getAuthUserDataStore(): AuthUserDataStore
	{
		return $this->data_store ??= AuthUserDataStore::getInstance($this, []);
	}

	#[Override]
	public function setAuthUserDataStore(AuthUserDataStore $store): static
	{
		$this->data_store = $store;

		return $this;
	}

	#[Override]
	public function isAuthUserValid(): bool
	{
		return $this->valid;
	}

	#[Override]
	public function save(): bool
	{
		return true;
	}

	#[Override]
	public function toArray(): array
	{
		return ['type' => $this->type, 'id' => $this->id];
	}

	#[Override]
	public function jsonSerialize(): mixed
	{
		return $this->toArray();
	}
}
