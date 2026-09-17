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

namespace OZONE\Core\Users\Cli;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Kli\KliArgs;
use Override;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Db\OZUser;
use OZONE\Core\OZone;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Users\UsersRepository;
use Throwable;

/**
 * Class UsersCmd.
 */
final class UsersCmd extends Command
{
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage users.');

		if (Utils::isProjectLoaded() && OZone::hasDbInstalled()) {
			// action: add a new user
			$add = $this->action('add', 'Add a new user.');

			$db       = db();
			$user_tbl = $db->getTableOrFail(OZUser::TABLE_NAME);

			$add->addOption(
				...Utils::buildTableCliOptions($user_tbl, [], [
					OZUser::COL_DATA,
					OZUser::COL_CREATED_AT,
					OZUser::COL_UPDATED_AT,
					OZUser::COL_IS_VALID,
				])
			);

			$add->handler($this->add(...));

			// action: give a role to an existing user
			$grant = $this->action('grant', 'Give a role to an existing user.');

			$grant->option('user', 'u', [], 1)
				->required()
				->description('The user: its id, or the identifier --by names.')
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
				->description('The user type (oz.auth.users.repositories).')
				->string(1)
				->def(UsersRepository::DEFAULT_USER_TYPE);

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

		try {
			$role = RolesUtils::normalize((string) $args->get('role'));
		} catch (Throwable) {
			$cli->error(\sprintf(
				'Unknown role "%s": use one of %s.',
				$args->get('role'),
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
	 * Adds new user.
	 *
	 * @param KliArgs $args
	 *
	 * @throws CRUDException
	 * @throws ORMException
	 * @throws ORMQueryException
	 * @throws GoblException
	 */
	private function add(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$user = new OZUser();

		$user->hydrate($args->getNamedArgs())
			->save();
	}
}
