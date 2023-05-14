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

namespace OZONE\OZ\Cli\Cmd;

use Kli\KliArgs;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Db\OZUser;

/**
 * Class User.
 */
final class User extends Command
{
	/**
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage users.');

		if (Utils::isProjectRootDir()) {
			// action: add a new user
			$add = $this->action('add', 'Add a new user.');

			$db       = DbManager::getDb();
			$user_tbl = $db->getTableOrFail(OZUser::TABLE_NAME);

			$add->addOption(...Utils::buildTableCliOptions($user_tbl, [], [
				OZUser::COL_DATA,
				OZUser::COL_CREATED_AT,
				OZUser::COL_UPDATED_AT,
				OZUser::COL_IS_VALID,
			]));

			$add->handler($this->add(...));
		}
	}

	/**
	 * Adds new user.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private function add(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$user = new OZUser();

		$user->hydrate($args->getNamedArgs())
			->save();
	}
}
