<?php
/**
 * Auto generated file
 * 
 * WARNING: please don't edit.
 * 
 * Proudly With: gobl v1.5.0
 * Time: 2022-11-21T17:43:33+00:00
 */
declare(strict_types=1);

namespace OZONE\OZ\Db\Base;

/**
 * Class OZCountriesResults.
 * 
 * @method null|\OZONE\OZ\Db\OZCountry current()
 * @method null|\OZONE\OZ\Db\OZCountry fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZCountry[] fetchAllClass(bool $strict = true)
 * @method null|\OZONE\OZ\Db\OZCountry updateOneItem(array $filters, array $new_values)
 */
abstract class OZCountriesResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZCountriesResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\OZ\Db\OZCountry::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZCountry::TABLE_NAME,
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
		return new \OZONE\OZ\Db\OZCountriesResults($query);
	}
}
