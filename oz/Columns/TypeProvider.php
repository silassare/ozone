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

namespace OZONE\Core\Columns;

use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeProviderInterface;
use OZONE\Core\App\Settings;

/**
 * Class TypeProvider.
 */
class TypeProvider implements TypeProviderInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function getTypeInstance(string $name, array $options): ?TypeInterface
	{
		/** @var null|TypeInterface $type_class */
		$type_class = Settings::get('oz.db.columns.types', $name);

		if ($type_class) {
			return $type_class::getInstance($options);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasType(string $name): bool
	{
		return null !== Settings::get('oz.db.columns.types', $name);
	}
}
