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

namespace OZONE\Core\Users\Traits;

use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Users\UsersRepository;

/**
 * Trait UserEntityTrait.
 */
trait UserEntityTrait
{
	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserTypeName(): string
	{
		return UsersRepository::TYPE_NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthIdentifier(): string
	{
		$id = $this->getID();

		if (empty($id)) {
			throw new RuntimeException('Trying to get auth identifier of an unsaved user.');
		}

		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthIdentifiers(): array
	{
		return [
			self::IDENTIFIER_NAME_ID    => $this->getID(),
			self::IDENTIFIER_NAME_EMAIL => $this->getEmail(),
			self::IDENTIFIER_NAME_PHONE => $this->getPhone(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthPassword(): string
	{
		return $this->getPass();
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAuthPassword(string $password_hash): static
	{
		$this->setPass($password_hash);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserDataStore(): AuthUserDataStore
	{
		return AuthUserDataStore::getInstance($this, $this->getData()->getData());
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAuthUserDataStore(AuthUserDataStore $store): static
	{
		$this->setData($store->getData());

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAuthUserValid(): bool
	{
		return $this->isValid();
	}

	/**
	 * {@inheritDoc}
	 */
	public function save(): bool
	{
		if (!$this->getID()) {
			// new user will be added
			$phone = $this->getPhone();
			$email = $this->getEmail();

			if (empty($phone) && empty($email)) {
				// Maybe "OZ_USER_PHONE_REQUIRED" and "OZ_USER_EMAIL_REQUIRED"
				// are both set to "false" in "oz.users" settings file.
				throw new RuntimeException('Both user Phone and Email should not be empty.');
			}
		}

		$pass = $this->getPass();

		// we should not store non-hashed password
		if (!Password::isHash($pass)) {
			$pass = Password::hash($pass);
			$this->setPass($pass);

			// when user password change, force login again
			// on all sessions associated with user
			if ($this->getID()) {
				AuthUsers::forceUserLogoutOnAllActiveSessions($this);
			}
		}

		return parent::save();
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray($hide_sensitive_data = true): array
	{
		$arr = parent::toArray($hide_sensitive_data);

		$arr[self::COL_PASS] = null;

		return $arr;
	}
}
