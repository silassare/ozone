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
 * Class OZFilesController.
 *
 * @method \OZONE\OZ\Db\OZFile      addItem(array|\OZONE\OZ\Db\OZFile $item = [])
 * @method null|\OZONE\OZ\Db\OZFile getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZFile deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZFile[]    getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = -1)
 * @method \OZONE\OZ\Db\OZFile[]    getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, &$total = false)
 * @method null|\OZONE\OZ\Db\OZFile updateOneItem(array $filters, array $new_values)
 */
abstract class OZFilesController extends \Gobl\ORM\ORMController
{
	/**
	 * OZFilesController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZFile::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZFile::TABLE_NAME
		);
	}
}
