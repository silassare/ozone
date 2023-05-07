<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v2.0.0
 * Time: 2023-05-06T15:46:01+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZFilesController.
 * 
 * @method \OZONE\OZ\Db\OZFile addItem(array|\OZONE\OZ\Db\OZFile $item = [])
 * @method null|\OZONE\OZ\Db\OZFile getItem(array $filters, array $order_by = [])
 * @method null|\OZONE\OZ\Db\OZFile deleteOneItem(array $filters)
 * @method \OZONE\OZ\Db\OZFile[] getAllItems(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = null)
 * @method \OZONE\OZ\Db\OZFile[] getAllItemsCustom(\Gobl\DBAL\Queries\QBSelect $qb, int $max = null, int $offset = 0, ?int &$total = null)
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

	/**
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(): static
	{
		return new \OZONE\OZ\Db\OZFilesController();
	}
}
