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
 * Class OZUsersResults.
 * 
 * @method null|\OZONE\OZ\Db\OZUser current()
 * @method null|\OZONE\OZ\Db\OZUser fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZUser[] fetchAllClass(bool $strict = true)
 * @method null|\OZONE\OZ\Db\OZUser updateOneItem(array $filters, array $new_values)
 */
abstract class OZUsersResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZUsersResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\OZ\Db\OZUser::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZUser::TABLE_NAME,
			$query
		);

	}

	/**
	 * @inheritDoc
	 * 
	 * @return static
	 */
	public static function createInstance(\Gobl\DBAL\Queries\QBSelect $query): static
	{
		return new \OZONE\OZ\Db\OZUsersResults($query);
	}
}
