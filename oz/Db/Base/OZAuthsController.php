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
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Db\OZAuthsController as OZAuthsControllerReal;

/**
 * Class OZAuthsController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZAuth, \OZONE\Core\Db\OZAuthsQuery, \OZONE\Core\Db\OZAuthsResults>
 */
abstract class OZAuthsController extends ORMController
{
	/**
	 * OZAuthsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZAuth::TABLE_NAMESPACE,
			OZAuth::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZAuthsControllerReal();
	}
}
