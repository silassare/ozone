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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Columns\ValidatedFile;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeFileTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class TypeFileTest extends TestCase
{
	private RDBMSInterface $rdbms;

	protected function setUp(): void
	{
		$this->rdbms = $this->createMock(RDBMSInterface::class);
	}

	// -------------------------------------------------------------------------
	// dbToPhp
	// -------------------------------------------------------------------------

	public function testDbToPhpNullReturnsNull(): void
	{
		$type = new TypeFile();
		self::assertNull($type->dbToPhp(null, $this->rdbms));
	}

	public function testDbToPhpStringIdReturnsPersisted(): void
	{
		$type = new TypeFile();
		$vf   = $type->dbToPhp('42', $this->rdbms);
		self::assertInstanceOf(ValidatedFile::class, $vf);
		self::assertTrue($vf->isPersisted());
		self::assertSame('42', $vf->getId());
	}

	public function testDbToPhpTempStringReturnsTemporary(): void
	{
		$type = (new TypeFile())->temp();
		$vf   = $type->dbToPhp('/tmp/some/file.jpg', $this->rdbms);
		self::assertInstanceOf(ValidatedFile::class, $vf);
		self::assertTrue($vf->isTemporary());
		self::assertSame('/tmp/some/file.jpg', $vf->getPath());
	}

	public function testDbToPhpMultipleJsonReturnsArray(): void
	{
		$type   = (new TypeFile())->multiple();
		$result = $type->dbToPhp('["1","2","3"]', $this->rdbms);
		self::assertIsArray($result);
		self::assertCount(3, $result);

		foreach ($result as $i => $vf) {
			self::assertInstanceOf(ValidatedFile::class, $vf);
			self::assertTrue($vf->isPersisted());
			self::assertSame((string) ($i + 1), $vf->getId());
		}
	}

	public function testDbToPhpMultipleNullReturnsNull(): void
	{
		$type = (new TypeFile())->multiple()->nullable();
		self::assertNull($type->dbToPhp(null, $this->rdbms));
	}

	// -------------------------------------------------------------------------
	// phpToDb
	// -------------------------------------------------------------------------

	public function testPhpToDbNullReturnsNull(): void
	{
		$type = (new TypeFile())->nullable();
		self::assertNull($type->phpToDb(null, $this->rdbms));
	}

	public function testPhpToDbPersistedReturnsId(): void
	{
		$type = new TypeFile();
		$vf   = ValidatedFile::forFileID('99');
		self::assertSame('99', $type->phpToDb($vf, $this->rdbms));
	}

	public function testPhpToDbTempReturnsPath(): void
	{
		$type = (new TypeFile())->temp();
		$vf   = ValidatedFile::forTempPath('/tmp/xyz.jpg');
		self::assertSame('/tmp/xyz.jpg', $type->phpToDb($vf, $this->rdbms));
	}

	public function testPhpToDbMultipleReturnsJson(): void
	{
		$type = (new TypeFile())->multiple();
		$vfs  = [
			ValidatedFile::forFileID('1'),
			ValidatedFile::forFileID('2'),
		];
		$db = $type->phpToDb($vfs, $this->rdbms);
		self::assertSame('["1","2"]', $db);
	}

	// -------------------------------------------------------------------------
	// runValidation / validate — IDOR protection
	// -------------------------------------------------------------------------

	public function testValidateRejectsRawStringId(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		// A bare numeric string (e.g. a file ID from user input) must be
		// rejected to prevent IDOR.
		(new TypeFile())->validate('42');
	}

	public function testValidateRejectsInteger(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeFile())->validate(42);
	}

	public function testValidateRejectsPlainArray(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		// A plain array of IDs should be rejected.
		(new TypeFile())->multiple()->validate(['1', '2']);
	}

	public function testValidateAcceptsValidatedFilePassthrough(): void
	{
		$vf    = ValidatedFile::forFileID('7');
		$clean = (new TypeFile())->validate($vf)->getCleanValue();
		self::assertSame($vf, $clean);
	}

	public function testValidateAcceptsValidatedFilePassthroughMultiple(): void
	{
		$vf1   = ValidatedFile::forFileID('1');
		$vf2   = ValidatedFile::forFileID('2');
		$type  = (new TypeFile())->multiple();
		$clean = $type->validate([$vf1, $vf2])->getCleanValue();
		self::assertIsArray($clean);
		self::assertCount(2, $clean);
		self::assertSame($vf1, $clean[0]);
		self::assertSame($vf2, $clean[1]);
	}

	public function testValidateNullableAcceptsNull(): void
	{
		$type  = (new TypeFile())->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}
}
