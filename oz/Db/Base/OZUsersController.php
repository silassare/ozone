<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v2.0.0
 * Time: 2023-05-09T07:41:19+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZUsersController.
 * 
 * @method \OZONE\OZ\Db\OZUser addItem(array|\OZONE\OZ\Db\OZUser $item = [])
 * @method null|\OZONE\OZ\Db\OZUser getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZUser deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZUser[] getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method \OZONE\OZ\Db\OZUser[] getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
 * @method null|\OZONE\OZ\Db\OZUser updateOneItem(array $filters, array $new_values)
 */
abstract class OZUsersController extends \Gobl\ORM\ORMController
{
	/**
	 * OZUsersController constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\OZ\Db\OZUser::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZUser::TABLE_NAME
		);

	}

	/**
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZUsersController();
	}
}
