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

namespace OZONE\OZ\Db\Base;

/**
 * Class OZCountriesController.
 *
 * @method \OZONE\OZ\Db\OZCountry      addItem(array|\OZONE\OZ\Db\OZCountry $item = [])
 * @method null|\OZONE\OZ\Db\OZCountry getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZCountry deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZCountry[]    getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = -1)
 * @method \OZONE\OZ\Db\OZCountry[]    getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, &$total = false)
 * @method null|\OZONE\OZ\Db\OZCountry updateOneItem(array $filters, array $new_values)
 */
abstract class OZCountriesController extends \Gobl\ORM\ORMController
{
	/**
	 * OZCountriesController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZCountry::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZCountry::TABLE_NAME
		);
	}
}
