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

use Gobl\CRUD\Events\BeforeReadAll;
use OZONE\Core\CRUD\TableCRUDListener;
use OZONE\Core\Db\OZFilesCrud;

/**
 * No one may read the whole files table: attached through the generated CRUD class, as a project's
 * listener may be, which loads it and its entity while the database is getting ready.
 */
final class FirstClassGeneratedCrudListener extends TableCRUDListener
{
	public static function register(): void
	{
		OZFilesCrud::new()->listen(new self());
	}

	public function onBeforeReadAll(BeforeReadAll $action): bool
	{
		return false;
	}
}
