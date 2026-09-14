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

namespace OZONE\Tests\Support;

use OZONE\Core\FS\Scan\Scanners\ClamAVScanner;
use Throwable;

/**
 * Trait RequiresClamAVTrait.
 *
 * For tests of the `clamav` group: they are skipped without a reachable clamd (OZ_CLAMAV_* in the
 * environment, see docker/compose.yaml), and fail instead when `OZ_TEST_CLAMAV_REQUIRED` is set.
 */
trait RequiresClamAVTrait
{
	/**
	 * Skips (or fails) the test unless clamd answers.
	 */
	protected static function requireClamAV(): void
	{
		$reason = 'clamd did not answer PING';

		try {
			$alive = ClamAVScanner::fromSettings()->ping();
		} catch (Throwable $t) {
			$alive  = false;
			$reason = $t->getMessage();
		}

		if ($alive) {
			return;
		}

		if (\getenv('OZ_TEST_CLAMAV_REQUIRED')) {
			self::fail('ClamAV is unavailable: ' . $reason . ' (OZ_TEST_CLAMAV_REQUIRED is set)');
		}

		self::markTestSkipped('ClamAV is unavailable: ' . $reason);
	}
}
