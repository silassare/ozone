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
 * Only administrators may read the files table: an anonymous read is refused, unless the rules were
 * never attached.
 */
final class FirstClassCrudHandler extends BaseHandler
{
	public function __construct(Table $table)
	{
		$this->adminOnlyRules();

		parent::__construct($table);
	}

	public static function register(): void
	{
		new self(db()->getTableOrFail('oz_files'));
	}
}
