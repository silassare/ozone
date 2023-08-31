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

/**
 * Class OZRolesController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZRole, \OZONE\Core\Db\OZRolesQuery, \OZONE\Core\Db\OZRolesResults>
 */
abstract class OZRolesController extends \Gobl\ORM\ORMController
{
	/**
	 * OZRolesController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZRole::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZRole::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZRolesController();
	}
}
