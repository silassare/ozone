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
 * Class OZJobsController.
 *
 * @method \OZONE\Core\Db\OZJob      addItem(array|\OZONE\Core\Db\OZJob $item = [])
 * @method null|\OZONE\Core\Db\OZJob getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\Core\Db\OZJob deleteOneItem(array $filters)
 * @method \OZONE\Core\Db\OZJob[]    getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method \OZONE\Core\Db\OZJob[]    getAllItemsCustom(QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
 * @method \OZONE\Core\Db\OZJob      getRelative(ORMEntity $entity, Relation $relation, array $filters = [], array $order_by = [])
 * @method \OZONE\Core\Db\OZJob[]    getAllRelatives(ORMEntity $entity, Relation $relation, array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method null|\OZONE\Core\Db\OZJob updateOneItem(array $filters, array $new_values)
 */
abstract class OZJobsController extends \Gobl\ORM\ORMController
{
	/**
	 * OZJobsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZJob::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZJob::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\Core\Db\OZJobsController();
	}
}
