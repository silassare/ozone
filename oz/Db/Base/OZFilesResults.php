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
 * Class OZFilesResults.
 * 
 * @method null|\OZONE\OZ\Db\OZFile current()
 * @method null|\OZONE\OZ\Db\OZFile fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZFile[] fetchAllClass(bool $strict = true)
 * @method null|\OZONE\OZ\Db\OZFile updateOneItem(array $filters, array $new_values)
 */
abstract class OZFilesResults extends \Gobl\ORM\ORMResults
{
	/**
	 * OZFilesResults constructor.
	 */
	public function __construct(\Gobl\DBAL\Queries\QBSelect $query)
	{
		parent::__construct(
			\OZONE\OZ\Db\OZFile::TABLE_NAMESPACE,
			\OZONE\OZ\Db\OZFile::TABLE_NAME,
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
		return new \OZONE\OZ\Db\OZFilesResults($query);
	}
}
