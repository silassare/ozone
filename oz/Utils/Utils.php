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

namespace OZONE\Core\Utils;

/**
 * Class Utils.
 */
class Utils
{
	/**
	 * Cleans or flushes output buffers up to target level.
	 *
	 * Resulting level can be greater than target level if a non-removable buffer has been encountered.
	 */
	public static function closeOutputBuffers(int $target_level, bool $flush): void
	{
		$status = \ob_get_status(true);
		$level  = \count($status);
		$flags  = \PHP_OUTPUT_HANDLER_REMOVABLE
			| ($flush ? \PHP_OUTPUT_HANDLER_FLUSHABLE : \PHP_OUTPUT_HANDLER_CLEANABLE);

		while (
			$level-- > $target_level
			&& ($s = $status[$level])
			&& ($s['del'] ?? (!isset($s['flags']) || ($s['flags'] & $flags) === $flags))
		) {
			if ($flush) {
				\ob_end_flush();
			} else {
				\ob_end_clean();
			}
		}
	}
}
