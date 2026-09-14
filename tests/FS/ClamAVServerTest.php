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

namespace OZONE\Tests\FS;

use Override;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Scan\Scanners\ClamAVScanner;
use OZONE\Tests\Support\RequiresClamAVTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class ClamAVServerTest.
 *
 * Runs against a real clamd: `make test-services` (see docker/compose.yaml).
 *
 * @internal
 *
 * @group clamav
 *
 * @covers \OZONE\Core\FS\Scan\Scanners\ClamAVScanner
 */
final class ClamAVServerTest extends TestCase
{
	use RequiresClamAVTrait;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		self::requireClamAV();
	}

	public function testFindsTheEicarTestFile(): void
	{
		// The EICAR test file, split so that an antivirus does not flag this source file.
		$eicar  = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
		$result = ClamAVScanner::fromSettings()->scan(FileStream::fromString($eicar));

		self::assertTrue($result->isInfected());
		self::assertStringContainsStringIgnoringCase('eicar', (string) $result->signature);
	}

	public function testPassesACleanFile(): void
	{
		$result = ClamAVScanner::fromSettings()->scan(FileStream::fromString(\str_repeat('clean content ', 2000)));

		self::assertFalse($result->isInfected());
	}
}
