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
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\Utils\ORMClassKind;
use InvalidArgumentException;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\AuthUsersRepositoryInterface;
use OZONE\Core\Columns\Types\TypeCC2;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Columns\Types\TypeGender;
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

	private const TABLE_MARKER_META_KEY = 'ozone.auth_users_repository.auth_user_type';

	/**
	 * UsersRepository constructor.
	 */
	private function __construct(
		private Table $table,
		private string $user_type,
	) {}

	/**
	 * Check if a given auth user table is supported by this repository.
	 *
	 * @param Table $table
	 *
	 * @return bool
	 *
	 * @internal
	 */
	public static function isTableSupported(Table $table): bool
	{
		$user_type = $table->getMorphType();
		$meta      = $table->getMeta();

		return $meta->get(self::TABLE_MARKER_META_KEY) === $user_type;
	}

	/**
	 * Add standard auth user columns to a table.
	 *
	 * @param TableBuilder $tb
	 * @param array{
	 *     with_gender?:bool,
	 *     with_birth_date?:bool,
	 *     with_country?:bool,
	 *     min_age?: int,
	 *     max_age?: int
	 * } $options
	 *
	 * @throws DBALException
	 */
	public static function makeAuthUserTable(TableBuilder $tb, array $options = []): void
	{
		$table     = $tb->getTable();
		$user_type = $table->getMorphType();

		$with_gender     = $options['with_gender'] ?? true;
		$with_birth_date = $options['with_birth_date'] ?? true;
		$with_country    = $options['with_country'] ?? true;
		$min_age         = $options['min_age'] ?? Settings::get('oz.users', 'OZ_USER_MIN_AGE');
		$max_age         = $options['max_age'] ?? Settings::get('oz.users', 'OZ_USER_MAX_AGE');

		$tb->meta(self::TABLE_MARKER_META_KEY, $user_type);

		// required columns
		$tb->id();
		$tb->column(AuthUserInterface::IDENTIFIER_NAME_PHONE, TypeUtils::userPhone($user_type));
		$tb->column(AuthUserInterface::IDENTIFIER_NAME_EMAIL, TypeUtils::userMailAddress($user_type));

		if ($with_gender) {
			$tb->column('gender', new TypeGender());
		}

		if ($with_birth_date) {
			$tb->column('birth_date', TypeUtils::birthDate($min_age, $max_age));
		}

		$tb->column('pass', new TypePassword());
		$tb->map('data')->default([]);

		// optional columns
		$tb->column('pic', (new TypeFile())->mimeTypes(['image/png', 'image/jpeg'])->nullable());
		$tb->bool('is_valid')->default(true);
		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->collectFk(static function () use ($tb, $with_country) {
			if ($with_country) {
				$tb->foreign('cc2', 'oz_countries', 'cc2', false, static function (Column $column) {
					/** @var TypeCC2 $cc2_type */
					$cc2_type = $column->getType();
					$cc2_type->authorized();
				})
					->onUpdateCascade()
					->onDeleteRestrict();
			}
		});

		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('phone');
			$tb->unique('email');
		});

		// relations
		$tb->collectRelation(static function () use ($tb, $with_country) {
			$tb->hasMany('roles')->from('oz_roles')->usingMorph('owner');
			$tb->hasMany('files')->from('oz_files')->usingMorph('for');
			$tb->hasMany('sessions')->from('oz_sessions')->usingMorph('owner');

			if ($with_country) {
				$tb->belongsTo('country')->from('oz_countries');
			}
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(string $user_type_name): self
	{
		$table = db()->getTableByMorphType($user_type_name);

		if (!$table || !self::isTableSupported($table)) {
			throw new InvalidArgumentException(\sprintf(
				'The auth user type "%s" is not supported.',
				$user_type_name
			));
		}

		return new self($table, $user_type_name);
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
				'type'  => $this->user_type,
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
				'type'  => $this->user_type,
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
				'type' => $this->user_type,
			], $t);
		}
	}

	private function qb(): ORMTableQuery
	{
		/** @var ORMTableQuery $class */
		$class = ORMClassKind::QUERY->getClassFQN($this->table);

		return $class::new();
	}
}
