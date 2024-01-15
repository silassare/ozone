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
use OZONE\Core\Db\OZUser;
use OZONE\Core\Db\OZUsersController as OZUsersControllerReal;

/**
 * Class OZUsersController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZUser, \OZONE\Core\Db\OZUsersQuery, \OZONE\Core\Db\OZUsersResults>
 */
abstract class OZUsersController extends ORMController
{
	/**
	 * OZUsersController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZUser::TABLE_NAMESPACE,
			OZUser::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZUsersControllerReal();
	}
}
