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

/**
 * Interface AuthUserInterface.
 */
interface AuthUserInterface extends ArrayCapableInterface
{
	public const IDENTIFIER_NAME_ID    = 'id';
	public const IDENTIFIER_NAME_PHONE = 'phone';
	public const IDENTIFIER_NAME_EMAIL = 'email';

	/**
	 * Get the entity auth type name.
	 *
	 * Usually the entity table name.
	 *
	 * @return string
	 */
	public function getAuthUserTypeName(): string;

	/**
	 * Get the entity auth main identifier.
	 *
	 * The auth main identifier is a unique string that identifies the entity.
	 * This is usually the primary key of the entity.
	 *
	 * @return string
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
	 *
	 * @return array
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
	 *
	 * @param string $password_hash
	 *
	 * @return static
	 */
	public function setAuthPassword(string $password_hash): static;

	/**
	 * Get the auth user data store.
	 *
	 * @return AuthUserDataStore
	 */
	public function getAuthUserDataStore(): AuthUserDataStore;

	/**
	 * Set the auth user data store.
	 *
	 * @param AuthUserDataStore $store
	 *
	 * @return static
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
