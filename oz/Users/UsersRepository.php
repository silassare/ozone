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
use Override;
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

		$tb->string('civility')->min(0)->max(16)
			->setMetaKey('field.label', 'Civility')
			->setMetaKey('api.doc.description', 'The civility of the auth user (e.g. Mr, Mrs, Miss, etc.)');

		$tb->string('display_name')->max(60)
			->setMetaKey('field.label', 'Display Name')
			->setMetaKey('api.doc.description', 'The display name of the auth user');
		// In old project user entity has only 'name' column representing the Full Name,
		// we had no first_name/last_name/display_name/username logic before.
		// To avoid loosing data or breaking old code, we set the old name as 'name' so Gobl can
		// keep detect diff key and rename 'name' column to 'display_name'.
		$tb->useColumn('display_name')->oldName('name');

		$tb->string('first_name')->max(60)
			->setMetaKey('field.label', 'First Name')
			->setMetaKey('api.doc.description', 'The first name of the auth user');

		$tb->string('last_name')->max(60)
			->setMetaKey('field.label', 'Last Name')
			->setMetaKey('api.doc.description', 'The last name of the auth user');

		$tb->column(AuthUserInterface::IDENTIFIER_TYPE_NAME, TypeUtils::authUserName()->notRegistered())
			->setMetaKey('field.label', 'Username')
			->setMetaKey('api.doc.description', 'The unique username of the auth user');

		$tb->column(AuthUserInterface::IDENTIFIER_TYPE_PHONE, TypeUtils::authUserPhone()->notRegistered($user_type))
			->setMetaKey('field.label', 'Phone')
			->setMetaKey('api.doc.description', 'The phone number of the auth user');

		$tb->column(AuthUserInterface::IDENTIFIER_TYPE_EMAIL, TypeUtils::authUserEmail()->notRegistered($user_type))
			->setMetaKey('field.label', 'Email')
			->setMetaKey('api.doc.description', 'The email address of the auth user');

		if ($with_gender) {
			$tb->column('gender', new TypeGender())
				->setMetaKey('field.label', 'Gender')
				->setMetaKey('api.doc.description', 'The gender of the auth user');
		}

		if ($with_birth_date) {
			$tb->column('birth_date', TypeUtils::birthDate($min_age, $max_age))
				->setMetaKey('field.label', 'Birth Date')
				->setMetaKey('api.doc.description', 'The birth date of the auth user');
		}

		$tb->column('pass', new TypePassword())
			->setMetaKey('field.label', 'Password')
			->setMetaKey('api.doc.description', 'The password of the auth user');

		$tb->map('data')->default([])
			->setMetaKey('field.label', 'Data')
			->setMetaKey('api.doc.description', 'Additional data of the auth user');

		// optional columns
		$tb->column('pic', (new TypeFile())->mimeTypes(['image/png', 'image/jpeg'])->nullable())
			->setMetaKey('field.label', 'Profile Picture')
			->setMetaKey('api.doc.description', 'The profile picture of the auth user');

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether the auth user is active and valid');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->collectFk(static function () use ($tb, $with_country) {
			$tb->foreign(AuthUserInterface::IDENTIFIER_TYPE_NAME, 'oz_usernames', 'name', !Settings::get('oz.users', 'OZ_USER_USERNAME_REQUIRED'))
				->onUpdateCascade()
				->onDeleteRestrict();

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
			$tb->unique(AuthUserInterface::IDENTIFIER_TYPE_NAME);
			$tb->unique(AuthUserInterface::IDENTIFIER_TYPE_PHONE);
			$tb->unique(AuthUserInterface::IDENTIFIER_TYPE_EMAIL);
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
	#[Override]
	public static function get(string $user_type): static
	{
		$table = db()->getTableByMorphType($user_type);

		if (!$table || !self::isTableSupported($table)) {
			throw new InvalidArgumentException(\sprintf(
				'The auth user type "%s" is not supported.',
				$user_type
			));
		}

		return new self($table, $user_type);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAuthUserByIdentifier(string $identifier): ?AuthUserInterface
	{
		return $this->getAuthUserByIdentifierType(AuthUserInterface::IDENTIFIER_TYPE_ID, $identifier);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAuthUserByIdentifierType(string $identifier_type, string $identifier_value): ?AuthUserInterface
	{
		$c_full_name = $this->table->getColumnOrFail($identifier_type)->getFullName();

		// check if this column can be used as unique identifier
		if ($this->table->isPrimaryKey([$c_full_name]) || $this->table->isUniqueKey([$c_full_name])) {
			try {
				$sel = $this->qb()->where([$c_full_name, 'eq', $identifier_value])
					->find(1);

				/** @var null|AuthUserInterface */
				return $sel->fetchClass();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load user.', [
					$identifier_type  => $identifier_value,
					'type'            => $this->user_type,
				], $t);
			}
		}

		throw new RuntimeException(\sprintf(
			'"%s" is not a unique identifier for table "%s".',
			$identifier_type,
			$this->table->getName()
		));
	}

	private function qb(): ORMTableQuery
	{
		/** @var ORMTableQuery $class */
		$class = ORMClassKind::QUERY->getClassFQN($this->table);

		return $class::new();
	}
}
