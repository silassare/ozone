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
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Db\OZUser;
use OZONE\Core\OZone;

/**
 * Class UsersCmd.
 */
final class UsersCmd extends Command
{
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
		}
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
