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

namespace OZONE\Core\Forms;

use InvalidArgumentException;
use PHPUtils\DotPath;
use Throwable;

/**
 * Class FormUtils.
 */
final class FormUtils
{
	/**
	 * Asserts that a given field name is valid.
	 *
	 * Note: we accept any field name that can be parsed as a dot path.
	 * This allows for nested field names like "user.name" or "address.street".
	 * You can learn more about the exact syntax rules in {@see DotPath}.
	 *
	 * @param string $name the field name to validate
	 */
	public static function assertValidFieldName(string $name): void
	{
		try {
			/**
			 * @psalm-suppress UnusedMethodCall since we just want to check if the name can be parsed as a dot path,
			 * we don't care about the segments themselves
			 */
			DotPath::parse($name)->getSegments();
		} catch (Throwable $t) {
			throw new InvalidArgumentException(\sprintf('Invalid field name: "%s"', $name), 0, $t);
		}
	}
}
