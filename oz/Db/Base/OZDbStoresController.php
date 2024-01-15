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
use OZONE\Core\Db\OZDbStore;
use OZONE\Core\Db\OZDbStoresController as OZDbStoresControllerReal;

/**
 * Class OZDbStoresController.
 *
 * @extends \Gobl\ORM\ORMController<\OZONE\Core\Db\OZDbStore, \OZONE\Core\Db\OZDbStoresQuery, \OZONE\Core\Db\OZDbStoresResults>
 */
abstract class OZDbStoresController extends ORMController
{
	/**
	 * OZDbStoresController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			OZDbStore::TABLE_NAMESPACE,
			OZDbStore::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new OZDbStoresControllerReal();
	}
}
