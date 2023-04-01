<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v2.0.0
 * Time: 2023-03-31T23:29:45+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZSessionsResults.
 * 
 * @method null|\OZONE\OZ\Db\OZSession current()
 * @method null|\OZONE\OZ\Db\OZSession fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZSession[] fetchAllClass(bool $strict = true)
 * @method null|\OZONE\OZ\Db\OZSession updateOneItem(array $filters, array $new_values)
 */
abstract class OZSessionsResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZSessionsResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\OZ\Db\OZSession::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZSession::TABLE_NAME,
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
		return new \OZONE\OZ\Db\OZSessionsResults($query);
	}
}
