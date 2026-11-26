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

namespace __PLH_NAMESPACE__;

use OZONE\Core\App\Settings;
use OZONE\Core\CRUD\TableCRUD;
use OZONE\Core\CRUD\TableCRUDListener;

/**
 * Opens the bulk create of the `__PLH_TABLE__` table while the project's `oz.test.bulk` `OPEN` says so,
 * as a project's listener would open it for whom it chooses.
 */
final class BulkCreateOpener extends TableCRUDListener
{
	public static function register(): void
	{
		TableCRUD::events('__PLH_TABLE__')->onBeforeCreateBulk(
			static fn (): bool => (bool) Settings::get('oz.test.bulk', 'OPEN', false)
		);
	}
}
