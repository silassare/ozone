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

namespace OZONE\Core\Auth\Interfaces;

use OZONE\Core\Auth\AuthUserDataStore;
use PHPUtils\Interfaces\ArrayCapableInterface;
use SensitiveParameter;

/**
 * Interface AuthUserInterface.
 *
 * This represent a user entity that can be authenticated.
 */
interface AuthUserInterface extends ArrayCapableInterface
{
	public const string IDENTIFIER_TYPE_ID         = 'id';
	public const string IDENTIFIER_TYPE_NAME       = 'username';
	public const string IDENTIFIER_TYPE_PHONE      = 'phone';
	public const string IDENTIFIER_TYPE_EMAIL      = 'email';
	public const string AUTH_USER_DATA_STORE_KEY   = '_auth_user_data_store';

	/**
	 * Get the entity auth type.
	 *
	 * Usually the entity table morph type {@see Table::getMorphType()}.
	 */
	public function getAuthUserType(): string;

	/**
	 * Get the entity auth main identifier.
	 *
	 * The auth main identifier is a unique string that identifies the entity.
	 * This is usually the primary key of the entity.
	 */
	public function getAuthIdentifier(): string;

	/**
	 * Get all identifiers.
	 *
	 * The identifiers are the fields that can be used to identify the entity.
	 *
	 * Example:
	 *
	 * ```php
	 * return [
	 *   'id' => $this->getID(),
	 *   'email' => $this->getEmail(),
	 *   'phone' => $this->getPhone(),
	 * ];
	 * ```
	 */
	public function getAuthIdentifiers(): array;

	/**
	 * Get the auth user password.
	 *
	 * The password must be hashed.
	 */
	public function getAuthPassword(): string;

	/**
	 * Set the auth user password.
	 */
	public function setAuthPassword(#[SensitiveParameter] string $password_hash): static;

	/**
	 * Get the auth user data store.
	 */
	public function getAuthUserDataStore(): AuthUserDataStore;

	/**
	 * Set the auth user data store.
	 */
	public function setAuthUserDataStore(AuthUserDataStore $store): static;

	/**
	 * Check if the auth user is valid.
	 */
	public function isAuthUserValid(): bool;

	/**
	 * Save the entity.
	 */
	public function save(): bool;
}
