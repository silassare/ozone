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

use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Relations\Relation;
use Gobl\ORM\ORMEntity;

/**
 * Class OZRolesController.
 *
 * @method \OZONE\Core\Db\OZRole      addItem(array|\OZONE\Core\Db\OZRole $item = [])
 * @method null|\OZONE\Core\Db\OZRole getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\Core\Db\OZRole deleteOneItem(array $filters)
 * @method \OZONE\Core\Db\OZRole[]    getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method \OZONE\Core\Db\OZRole[]    getAllItemsCustom(QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
 * @method \OZONE\Core\Db\OZRole      getRelative(ORMEntity $entity, Relation $relation, array $filters = [], array $order_by = [])
 * @method \OZONE\Core\Db\OZRole[]    getAllRelatives(ORMEntity $entity, Relation $relation, array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method null|\OZONE\Core\Db\OZRole updateOneItem(array $filters, array $new_values)
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
