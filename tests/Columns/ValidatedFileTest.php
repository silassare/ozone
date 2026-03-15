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

namespace OZONE\Tests\Columns;

use InvalidArgumentException;
use OZONE\Core\Columns\ValidatedFile;
use PHPUnit\Framework\TestCase;

/**
 * Class ValidatedFileTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class ValidatedFileTest extends TestCase
{
	public function testIsPathReturnsTrueForPathInstance(): void
	{
		$vf = new ValidatedFile('/path/to/some/file.jpg', true);
		self::assertTrue($vf->isPath());
		self::assertFalse($vf->isFileID());
	}

	public function testIsFileIDReturnsTrueForIdInstance(): void
	{
		$vf = new ValidatedFile('42', false);
		self::assertFalse($vf->isPath());
		self::assertTrue($vf->isFileID());
	}

	public function testToStringReturnsValue(): void
	{
		$vf = new ValidatedFile('/uploads/foo.png', true);
		self::assertSame('/uploads/foo.png', (string) $vf);

		$vfId = new ValidatedFile('123', false);
		self::assertSame('123', (string) $vfId);
	}

	public function testNonNumericFileIdThrowsInvalidArgumentException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new ValidatedFile('not-numeric', false);
	}

	public function testNumericStringIsValidFileId(): void
	{
		$vf = new ValidatedFile('0', false);
		self::assertTrue($vf->isFileID());

		$vf2 = new ValidatedFile('999999', false);
		self::assertTrue($vf2->isFileID());
	}

	public function testPathCanBeAnything(): void
	{
		// When is_path=true, no validation on the value string
		$vf = new ValidatedFile('not-numeric-but-is-path', true);
		self::assertTrue($vf->isPath());
		self::assertSame('not-numeric-but-is-path', (string) $vf);
	}
}
