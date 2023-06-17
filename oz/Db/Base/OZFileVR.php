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

namespace OZONE\Core\Db\Base;

use InvalidArgumentException;

/**
 * Class OZFileVR.
 */
abstract class OZFileVR extends \Gobl\DBAL\Relations\VirtualRelation
{
	/**
	 * {@inheritDoc}
	 */
	public function get(\Gobl\ORM\ORMEntity $target, \Gobl\ORM\ORMRequest $request, ?int &$total_records = null): mixed
	{
		if ($target instanceof \OZONE\Core\Db\OZFile) {
			return $this->getItemRelation($target, $request, $total_records);
		}

		throw new InvalidArgumentException('Target item should be an instance of: ' . \OZONE\Core\Db\OZFile::class);
	}

	/**
	 * Gets a relation for a given target item.
	 */
	abstract protected function getItemRelation(\Gobl\ORM\ORMEntity $target, \Gobl\ORM\ORMRequest $request, ?int &$total_records = null): mixed;
}
