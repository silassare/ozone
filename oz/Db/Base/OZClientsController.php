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
 * Class OZClientsController.
 * 
 * @method \OZONE\OZ\Db\OZClient addItem(array|\OZONE\OZ\Db\OZClient $item = [])
 * @method null|\OZONE\OZ\Db\OZClient getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZClient deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZClient[] getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method \OZONE\OZ\Db\OZClient[] getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
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

	/**
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZClientsController();
	}
}
