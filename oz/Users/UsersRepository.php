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

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\Utils\ORMClassKind;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\AuthUsersRepositoryInterface;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Columns\TypeUtils;
use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class UsersRepository.
 */
final class UsersRepository implements AuthUsersRepositoryInterface
{
	public const DEFAULT_USER_TYPE = 'user';

	/** @var array<string, 1> */
	private static array $users_tables = [];

	/**
	 * UsersRepository constructor.
	 */
	public function __construct(protected string $table_name) {}

	/**
	 * Check if a given user type is supported by this repository.
	 *
	 * @param string $type_name
	 *
	 * @return bool
	 */
	public static function supported(string $type_name): bool
	{
		return isset(self::$users_tables[$type_name]);
	}

	/**
	 * Add standard auth user columns to a table.
	 *
	 * @param TableBuilder $tb
	 *
	 * @throws DBALException
	 */
	public static function makeAuthUserTable(TableBuilder $tb): void
	{
		$table_name = $tb->getTable()->getName();

		// added once
		// this method may be called multiple times for the same table in different db schema collect hooks
		self::$users_tables[$table_name] = 1;

		// required columns
		$tb->id();
		$tb->column(AuthUserInterface::IDENTIFIER_NAME_PHONE, TypeUtils::userPhone($table_name));
		$tb->column(AuthUserInterface::IDENTIFIER_NAME_EMAIL, TypeUtils::userMailAddress($table_name));
		$tb->column('pass', new TypePassword());
		$tb->map('data')->default([]);

		// optional columns
		$tb->column('pic', (new TypeFile())->mimeTypes(['image/png', 'image/jpeg'])->nullable());
		$tb->timestamps();
		$tb->softDeletable();

		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('phone');
			$tb->unique('email');
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(string $user_type_name): self
	{
		return new self($user_type_name);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserByIdentifier(string $identifier): ?AuthUserInterface
	{
		return $this->withID($identifier);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthUserByNamedIdentifier(string $identifier_name, string $identifier_value): ?AuthUserInterface
	{
		return match ($identifier_name) {
			AuthUserInterface::IDENTIFIER_NAME_ID    => $this->withID($identifier_value),
			AuthUserInterface::IDENTIFIER_NAME_EMAIL => $this->withEmail($identifier_value),
			AuthUserInterface::IDENTIFIER_NAME_PHONE => $this->withPhone($identifier_value),
			default                                  => null,
		};
	}

	/**
	 * Search for registered user with a given phone number.
	 *
	 * No matter if user is valid or not.
	 *
	 * @psalm-suppress InvalidReturnStatement
	 * @psalm-suppress InvalidReturnType
	 *
	 * @param string $phone the phone number
	 *
	 * @return null|AuthUserInterface
	 */
	private function withPhone(string $phone): ?AuthUserInterface
	{
		try {
			return $this->qb()->where([AuthUserInterface::IDENTIFIER_NAME_PHONE, 'eq', $phone])
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by phone.', [
				'phone' => $phone,
				'type'  => $this->table_name,
			], $t);
		}
	}

	/**
	 * Search for registered user with a given email address.
	 *
	 * No matter if user is valid or not.
	 *
	 * @psalm-suppress InvalidReturnStatement
	 * @psalm-suppress InvalidReturnType
	 *
	 * @param string $email the email address
	 *
	 * @return null|AuthUserInterface
	 */
	private function withEmail(string $email): ?AuthUserInterface
	{
		try {
			return $this->qb()->where([AuthUserInterface::IDENTIFIER_NAME_EMAIL, 'eq', $email])
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by email.', [
				'email' => $email,
				'type'  => $this->table_name,
			], $t);
		}
	}

	/**
	 * Gets the user object with a given user id.
	 *
	 * @psalm-suppress InvalidReturnStatement
	 * @psalm-suppress InvalidReturnType
	 *
	 * @param string $uid the user id
	 *
	 * @return null|AuthUserInterface
	 */
	private function withID(string $uid): ?AuthUserInterface
	{
		try {
			return $this->qb()->where([AuthUserInterface::IDENTIFIER_NAME_ID, 'eq', $uid])
				->find(1)
				->fetchClass();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to load user by id.', [
				'uid'  => $uid,
				'type' => $this->table_name,
			], $t);
		}
	}

	private function table(): Table
	{
		return db()->getTableOrFail($this->table_name);
	}

	private function qb(): ORMTableQuery
	{
		/** @var ORMTableQuery $class */
		$class = ORMClassKind::QUERY->getClassFQN($this->table());

		return $class::new();
	}
}
