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

use Gobl\DBAL\Table;
use OZONE\Core\CRUD\BaseHandler;

/**
 * The `__PLH_TABLE__` table opened to administrators only, as OZone's strict default rules have it:
 * anyone else needs the right, given to them or to their role.
 */
final class AdminOnlyCrudHandler extends BaseHandler
{
	public function __construct(Table $table)
	{
		$this->adminOnlyRules();

		parent::__construct($table);
	}

	public static function register(): void
	{
		new self(db()->getTableOrFail('__PLH_TABLE__'));
	}
}
