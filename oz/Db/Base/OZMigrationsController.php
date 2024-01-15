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

namespace OZONE\Core\Db\Base;

use Gobl\ORM\ORMController;
use OZONE\Core\Db\OZMigration;
use OZONE\Core\Db\OZMigrationsController as OZMigrationsControllerReal;

/**
 * Class OZMigrationsController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZMigration, \OZONE\Core\Db\OZMigrationsQuery, \OZONE\Core\Db\OZMigrationsResults>
 */
abstract class OZMigrationsController extends ORMController
{
	/**
	 * OZMigrationsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZMigration::TABLE_NAMESPACE,
			OZMigration::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZMigrationsControllerReal();
	}
}
