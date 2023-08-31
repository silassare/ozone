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
 * Class OZDbStoresCrud.
 *
 * @extends \Gobl\CRUD\CRUDEventProducer<\OZONE\Core\Db\OZDbStore>
 */
abstract class OZDbStoresCrud extends \Gobl\CRUD\CRUDEventProducer
{
	/**
	 * OZDbStoresCrud constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZDbStore::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZDbStore::TABLE_NAME
		);
	}
}
