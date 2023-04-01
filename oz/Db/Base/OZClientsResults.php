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
 * Class OZClientsResults.
 * 
 * @method null|\OZONE\OZ\Db\OZClient current()
 * @method null|\OZONE\OZ\Db\OZClient fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZClient[] fetchAllClass(bool $strict = true)
 * @method null|\OZONE\OZ\Db\OZClient updateOneItem(array $filters, array $new_values)
 */
abstract class OZClientsResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZClientsResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\OZ\Db\OZClient::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZClient::TABLE_NAME,
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
		return new \OZONE\OZ\Db\OZClientsResults($query);
	}
}
