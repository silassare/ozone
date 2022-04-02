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
 * Class OZClientsController.
 *
 * @method \OZONE\OZ\Db\OZClient      addItem(array|\OZONE\OZ\Db\OZClient $item = [])
 * @method null|\OZONE\OZ\Db\OZClient getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZClient deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZClient[]    getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = -1)
 * @method \OZONE\OZ\Db\OZClient[]    getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, &$total = false)
 * @method null|\OZONE\OZ\Db\OZClient updateOneItem(array $filters, array $new_values)
 */
abstract class OZClientsController extends \Gobl\ORM\ORMController
{
	/**
	 * OZClientsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZClient::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZClient::TABLE_NAME
		);
	}
}
