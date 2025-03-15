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

use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\AuthUsersRepositoryInterface;
use OZONE\Core\Db\OZUsersQuery;
use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class UsersRepository.
 */
final class UsersRepository implements AuthUsersRepositoryInterface
{
	public const TYPE_NAME = 'oz_users';

	/**
	 * {@inheritDoc}
	 */
	public static function get(): self
	{
		return new self();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserByIdentifier(string $identifier): ?AuthUserInterface
	{
		return self::withID($identifier);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserByNamedIdentifier(string $identifier_name, string $identifier_value): ?AuthUserInterface
	{
		return match ($identifier_name) {
			AuthUserInterface::IDENTIFIER_NAME_ID    => self::withID($identifier_value),
			AuthUserInterface::IDENTIFIER_NAME_EMAIL => self::withEmail($identifier_value),
			AuthUserInterface::IDENTIFIER_NAME_PHONE => self::withPhone($identifier_value),
			default                                  => null,
		};
	}

	/**
	 * Search for registered user with a given phone number.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $phone the phone number
	 *
	 * @return null|AuthUserInterface
	 */
	private static function withPhone(string $phone): ?AuthUserInterface
	{
		try {
			$u_table = new OZUsersQuery();

			return $u_table->wherePhoneIs($phone)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by phone.', [
				'phone' => $phone,
			], $t);
		}
	}

	/**
	 * Search for registered user with a given email address.
	 *
	 * No matter if user is valid or not.
	 *
	 * @param string $email the email address
	 *
	 * @return null|AuthUserInterface
	 */
	private static function withEmail(string $email): ?AuthUserInterface
	{
		try {
			$u_table = new OZUsersQuery();

			return $u_table->whereEmailIs($email)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by email.', [
				'email' => $email,
			], $t);
		}
	}

	/**
	 * Gets the user object with a given user id.
	 *
	 * @param string $uid the user id
	 *
	 * @return null|AuthUserInterface
	 */
	private static function withID(string $uid): ?AuthUserInterface
	{
		try {
			$uq = new OZUsersQuery();

			return $uq->whereIdIs($uid)
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by id.', [
				'uid' => $uid,
			], $t);
		}
	}
}
