<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v1.5.0
 * Time: 2022-11-30T17:07:12+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZSessionsController.
 * 
 * @method \OZONE\OZ\Db\OZSession addItem(array|\OZONE\OZ\Db\OZSession $item = [])
 * @method null|\OZONE\OZ\Db\OZSession getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZSession deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZSession[] getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method \OZONE\OZ\Db\OZSession[] getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
 * @method null|\OZONE\OZ\Db\OZSession updateOneItem(array $filters, array $new_values)
 */
abstract class OZSessionsController extends \Gobl\ORM\ORMController
{
	/**
	 * OZSessionsController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZSession::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZSession::TABLE_NAME
		);

	}

	/**
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZSessionsController();
	}
}
