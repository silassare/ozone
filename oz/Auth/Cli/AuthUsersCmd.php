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

namespace OZONE\Core\Auth\Cli;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORM;
use Kli\KliArgs;
use Override;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\OZone;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use Throwable;

/**
 * Class AuthUsersCmd.
 */
final class AuthUsersCmd extends Command
{
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage auth users, of any type (oz.auth.users.repositories).');

		if (Utils::isProjectLoaded() && OZone::hasDbInstalled()) {
			// action: add a new auth user. Its options are the columns every table made by
			// makeAuthUserTable() has, named without the table's prefix and read off the default
			// type's table: options are declared before `--type` is read, so they cannot be the
			// table it names, and the standard columns are the same in all of them.
			$add = $this->action('add', 'Add a new auth user.');

			// A default type no repository serves is the doctor's to report: refusing it here would
			// stop every command, `oz doctor` among them, from being registered.
			try {
				$default = AuthUsersRepository::defaultType();
			} catch (RuntimeException) {
				$default = null;
			}

			$standard = null === $default ? null : self::table($default);

			if (null !== $standard) {
				$add->addOption(...Utils::buildTableCliOptions($standard, [], self::excluded($standard), true));
			}

			$type = $add->option('type', 't')
				->description('The auth user type (oz.auth.users.repositories).')
				->string(1);

			if (null !== $default) {
				$type->def($default);
			}

			$add->handler($this->add(...));

			// action: give a role to an existing auth user
			$grant = $this->action('grant', 'Give a role to an existing auth user.');

			$grant->option('user', 'u', [], 1)
				->required()
				->description('The auth user: its id, or the identifier --by names.')
				->string(1);
			$grant->option('role', 'r', [], 2)
				->required()
				->description('The role: a value of the OZ_ROLE_ENUM_CLASS enum (oz.roles), e.g. super-admin.')
				->string(1);
			$grant->option('by', 'b')
				->description(
					'What --user is: id, email, phone or username. Default: email when it holds an @, id otherwise.'
				)
				->string()
				->def('');
			$grant->option('type', 't')
				->description('The auth user type (oz.auth.users.repositories).')
				->string(1);

			if (null !== $default) {
				$grant->getOption('type')->getType()->def($default);
			}

			$grant->handler($this->grant(...));
		}
	}

	/**
	 * Gives a role to an existing user, restoring it when it was revoked.
	 */
	private function grant(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$cli   = $this->getCli();
		$value = (string) $args->get('user');
		$type  = (string) $args->get('type');
		$by    = (string) $args->get('by');

		if ('' === $by) {
			$by = \str_contains($value, '@')
				? AuthUserInterface::IDENTIFIER_TYPE_EMAIL
				: AuthUserInterface::IDENTIFIER_TYPE_ID;
		}

		$identifier_types = [
			AuthUserInterface::IDENTIFIER_TYPE_ID,
			AuthUserInterface::IDENTIFIER_TYPE_EMAIL,
			AuthUserInterface::IDENTIFIER_TYPE_PHONE,
			AuthUserInterface::IDENTIFIER_TYPE_NAME,
		];

		if (!\in_array($by, $identifier_types, true)) {
			$cli->error(\sprintf('--by must be one of: %s.', \implode(', ', $identifier_types)));

			return;
		}

		$role_name = (string) $args->get('role');

		try {
			$role = RolesUtils::normalize($role_name);
		} catch (Throwable) {
			$cli->error(\sprintf(
				'Unknown role "%s": use one of %s.',
				$role_name,
				\implode(', ', \array_map(
					static fn ($case): string => (string) $case->value,
					RolesUtils::getRoleEnumClass()::cases()
				))
			));

			return;
		}

		$user = AuthUsers::identify($type, $value, $by);

		if (null === $user) {
			$cli->error(\sprintf('No "%s" user with %s "%s".', $type, $by, $value));

			return;
		}

		Roles::assign($user, $role, true);

		$cli->success(\sprintf(
			'Role "%s" given to the "%s" user %s.',
			$role->value,
			$type,
			$user->getAuthIdentifier()
		));
	}

	/**
	 * Adds a new auth user of the type `--type` names, from the standard columns given by their
	 * short names.
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws ORMQueryException
	 * @throws GoblException
	 */
	private function add(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$cli   = $this->getCli();
		$named = $args->getNamedArgs();
		$type  = (string) ($named['type'] ?? AuthUsersRepository::defaultType());

		unset($named['type']);

		$table = self::table($type);

		if (null === $table) {
			$cli->error(\sprintf(
				'"%s" is not an auth user type whose table was made by makeAuthUserTable().',
				$type
			));

			return;
		}

		$values = [];

		foreach ($named as $name => $value) {
			if (null === $value) {
				continue;
			}

			$column = $table->getColumn((string) $name);

			if (null === $column) {
				$cli->error(\sprintf('The "%s" auth user type has no "%s" column.', $type, $name));

				return;
			}

			$values[$column->getFullName()] = $value;
		}

		$user = ORM::entity($table);

		$user->hydrate($values)
			->save();

		$cli->success(\sprintf('The "%s" auth user was added.', $type));
	}

	/**
	 * The table of an auth user type, where it was made by makeAuthUserTable(); null otherwise.
	 */
	private static function table(string $type): ?Table
	{
		$table = db()->getTableByMorphType($type);

		return $table && AuthUsersRepository::isTableSupported($table) ? $table : null;
	}

	/**
	 * The columns `add` does not offer: what the ORM or the auth user's own save writes.
	 *
	 * @return list<string>
	 */
	private static function excluded(Table $table): array
	{
		return \array_map(
			static fn (string $name): string => $table->getColumnOrFail($name)->getFullName(),
			['data', 'created_at', 'updated_at', 'is_valid']
		);
	}
}
