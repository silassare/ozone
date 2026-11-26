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

namespace OZONE\Core\Auth;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMOptions;
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
 * Class AuthUsersRepository.
 */
final class AuthUsersRepository implements AuthUsersRepositoryInterface
{
	/**
	 * The auth user type of OZone's default table, `oz_users`. What is used where no type is named is
	 * the project's setting, {@see self::defaultType()}: this is its default value.
	 */
	public const string DEFAULT_AUTH_USER_TYPE = 'user';

	private const string TABLE_MARKER_META_KEY = 'ozone.auth_users_repository.auth_user_type';

	/**
	 * AuthUsersRepository constructor.
	 */
	private function __construct(
		private Table $table,
		private string $user_type,
	) {}

	/**
	 * The auth user type used where none is named (`OZ_AUTH_USER_DEFAULT_TYPE`): `user` unless the
	 * project names another, as it must when it has no `oz_users`.
	 *
	 * @throws RuntimeException when no repository serves that type: every default would point at
	 *                          nothing, so it is refused where it is first read (the schema's build
	 *                          among them) rather than met as a missing account later
	 */
	public static function defaultType(): string
	{
		$type = Settings::get('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', self::DEFAULT_AUTH_USER_TYPE);
		$type = \is_string($type) && '' !== $type ? $type : self::DEFAULT_AUTH_USER_TYPE;

		if (null === AuthUsers::repositoryClass($type)) {
			throw new RuntimeException(\sprintf(
				'The default auth user type "%s" is served by no repository of oz.auth.users.repositories%s.',
				$type,
				self::DEFAULT_AUTH_USER_TYPE === $type && !self::hasDefaultTable()
					? ', OZ_AUTH_USER_DEFAULT_TABLE having left oz_users out: name one of the project\'s own types'
					: ''
			))->suspectConfig('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE');
		}

		return $type;
	}

	/**
	 * Whether OZone's default auth user table, `oz_users`, exists (`OZ_AUTH_USER_DEFAULT_TABLE`).
	 */
	public static function hasDefaultTable(): bool
	{
		return (bool) Settings::get('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TABLE', true);
	}

	/**
	 * Check if a given auth user table is supported by this repository.
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
		$min_age         = $options['min_age'] ?? Settings::get('oz.auth.users', 'OZ_AUTH_USER_MIN_AGE');
		$max_age         = $options['max_age'] ?? Settings::get('oz.auth.users', 'OZ_AUTH_USER_MAX_AGE');

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
		$tb->column('pic', new TypeFile()->mimeTypes(['image/png', 'image/jpeg'])->nullable())
			->setMetaKey('field.label', 'Profile Picture')
			->setMetaKey('api.doc.description', 'The profile picture of the auth user');

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether the auth user is active and valid');

		// The server's own: no form asks for them (sign-up, profile), no CRUD request writes them
		// unless a listener allows it, and no answer shows them.
		$tb->useColumn('data')->setPrivate();
		$tb->useColumn('is_valid')->setPrivate();

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->collectFk(static function () use ($tb, $with_country): void {
			$username_optional = !Settings::get('oz.auth.users', 'OZ_AUTH_USER_USERNAME_REQUIRED');

			$tb->foreign(AuthUserInterface::IDENTIFIER_TYPE_NAME, 'oz_usernames', 'name', $username_optional)
				->onUpdateCascade()
				->onDeleteRestrict();

			if ($with_country) {
				$tb->foreign('cc2', 'oz_countries', 'cc2')
					->onUpdateCascade()
					->onDeleteRestrict();

				/** @var TypeCC2 $cc2_type */
				$cc2_type = $tb->useColumn('cc2')->getType();
				$cc2_type->authorized();
			}
		});

		$tb->collectIndex(static function (TableBuilder $tb): void {
			$tb->unique(AuthUserInterface::IDENTIFIER_TYPE_NAME);
			$tb->unique(AuthUserInterface::IDENTIFIER_TYPE_PHONE);
			$tb->unique(AuthUserInterface::IDENTIFIER_TYPE_EMAIL);
		});

		// relations
		$tb->collectRelation(static function () use ($tb, $with_country): void {
			$tb->hasMany('roles')->from('oz_roles')->usingMorph('owner');
			$tb->hasMany('files')->from('oz_files')->usingMorph('for');
			$tb->hasMany('sessions')->from('oz_sessions')->usingMorph('owner');

			if ($with_country) {
				$tb->belongsTo('country')->from('oz_countries');
			}
		});
	}

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

	#[Override]
	public function getAuthUserByIdentifier(string $identifier): ?AuthUserInterface
	{
		return $this->getAuthUserByIdentifierType(AuthUserInterface::IDENTIFIER_TYPE_ID, $identifier);
	}

	#[Override]
	public function getAuthUserByIdentifierType(string $identifier_type, string $identifier_value): ?AuthUserInterface
	{
		$c_full_name = $this->table->getColumnOrFail($identifier_type)->getFullName();

		// check if this column can be used as unique identifier
		if ($this->table->isPrimaryKey([$c_full_name]) || $this->table->isUniqueKey([$c_full_name])) {
			try {
				$qb = ORM::query($this->table);
				$qb->where([$c_full_name, 'eq', $identifier_value]);
				$res = $qb->find(ORMOptions::makePaginated(1));

				/** @var null|AuthUserInterface */
				return $res->fetchClass();
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
}
