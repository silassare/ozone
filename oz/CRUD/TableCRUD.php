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

namespace OZONE\Core\CRUD;

use OZONE\Core\App\Settings;
use OZONE\Core\CRUD\Interfaces\TableCRUDListenerInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Migrations\Migrations;

/**
 * Class TableCRUD.
 */
final class TableCRUD
{
	/**
	 * Register CRUD event listeners, once per process, when the database is ready.
	 *
	 * Only on a database a migration was installed on (`OZ_MIGRATION_VERSION`, a settings read): the
	 * listeners attach to generated ORM classes, which a project that was never migrated may not
	 * have. It used to wait for {@see OZone::isInstalled()}, which also demands a super admin --
	 * something access control does not depend on -- and cost two queries: a worker started before
	 * the super admin was created ran without the CRUD listeners until it restarted.
	 */
	public static function registerListeners(): void
	{
		static $registered = false;

		if ($registered || Migrations::DB_NOT_INSTALLED_VERSION === Migrations::getInstalledDbVersion()) {
			return;
		}

		$registered = true;

		$gobl_crud = Settings::load('oz.gobl.crud');

		foreach ($gobl_crud as $listener => $enabled) {
			if (!$enabled) {
				continue;
			}

			if (!\is_subclass_of($listener, TableCRUDListenerInterface::class)) {
				throw new RuntimeException(
					\sprintf(
						'CRUD listener "%s" should extends "%s".',
						$listener,
						TableCRUDListenerInterface::class
					)
				);
			}

			/** @var TableCRUDListenerInterface $listener */
			$listener::register();
		}
	}
}
