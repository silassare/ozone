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
use OZONE\Core\Db\OZRole;
use OZONE\Core\Db\OZRolesController as OZRolesControllerReal;

/**
 * Class OZRolesController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZRole, \OZONE\Core\Db\OZRolesQuery, \OZONE\Core\Db\OZRolesResults>
 */
abstract class OZRolesController extends ORMController
{
	/**
	 * OZRolesController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZRole::TABLE_NAMESPACE,
			OZRole::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZRolesControllerReal();
	}
}
