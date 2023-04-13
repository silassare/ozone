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
 * Class OZFilesResults.
 *
 * @method null|\OZONE\OZ\Db\OZFile current()
 * @method null|\OZONE\OZ\Db\OZFile fetchClass(bool $strict = true)
 * @method \OZONE\OZ\Db\OZFile[]    fetchAllClass(bool $strict = true)
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
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function createInstance(\Gobl\DBAL\Queries\QBSelect $query): static
	{
		return new \OZONE\OZ\Db\OZFilesResults($query);
	}
}
