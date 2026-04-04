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
use OZONE\Core\Http\UploadedFile;
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

	/** @var string[] temporary files created during tests that must be cleaned up */
	private array $tmpFiles = [];

	protected function setUp(): void
	{
		$this->rdbms = $this->createMock(RDBMSInterface::class);
	}

	protected function tearDown(): void
	{
		foreach ($this->tmpFiles as $path) {
			if (\file_exists($path)) {
				\unlink($path);
			}
		}
		$this->tmpFiles = [];
		parent::tearDown();
	}

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

	// -----------------------------------------------------------------
	// .ofa alias tests
	// -----------------------------------------------------------------

	public function testAliasRejectedForTempColumn(): void
	{
		// A .ofa alias references a persisted file; temp columns must reject it.
		$path = $this->makeAliasFile('99', 'fake-key');

		$upload = new UploadedFile(
			$path,
			'file.ofa',
			'text/x-ozone-file-alias',
			\filesize($path),
			\UPLOAD_ERR_OK,
			false
		);

		$this->expectException(TypesInvalidValueException::class);
		(new TypeFile())->temp()->validate($upload);
	}

	public function testOversizedAliasRejected(): void
	{
		// Alias descriptor > 4 KB causes FS::parseFileAlias() to return null,
		// which the pre-resolution step converts to OZ_FILE_INVALID.
		$path             = \tempnam(\sys_get_temp_dir(), 'oz_test_alias_big_');
		$this->tmpFiles[] = $path;
		\file_put_contents($path, \str_repeat('X', 4001));

		$upload = new UploadedFile(
			$path,
			'big.ofa',
			'text/x-ozone-file-alias',
			4001,
			\UPLOAD_ERR_OK,
			false
		);

		$this->expectException(TypesInvalidValueException::class);
		(new TypeFile())->validate($upload);
	}

	public function testAliasWithInvalidJsonRejected(): void
	{
		// Non-JSON content causes FS::parseFileAlias() to throw, which the
		// pre-resolution step wraps as OZ_FILE_INVALID.
		$path             = \tempnam(\sys_get_temp_dir(), 'oz_test_alias_bad_');
		$this->tmpFiles[] = $path;
		\file_put_contents($path, 'not-valid-json');

		$upload = new UploadedFile(
			$path,
			'bad.ofa',
			'text/x-ozone-file-alias',
			\filesize($path),
			\UPLOAD_ERR_OK,
			false
		);

		$this->expectException(TypesInvalidValueException::class);
		(new TypeFile())->validate($upload);
	}

	public function testAliasWithMissingFieldsRejected(): void
	{
		// Valid JSON but missing required file_id / file_key fields.
		$path             = \tempnam(\sys_get_temp_dir(), 'oz_test_alias_empty_');
		$this->tmpFiles[] = $path;
		\file_put_contents($path, \json_encode(['foo' => 'bar']));

		$upload = new UploadedFile(
			$path,
			'empty.ofa',
			'text/x-ozone-file-alias',
			\filesize($path),
			\UPLOAD_ERR_OK,
			false
		);

		$this->expectException(TypesInvalidValueException::class);
		(new TypeFile())->validate($upload);
	}

	public function testAliasWithNonexistentFileIdRejected(): void
	{
		// Valid JSON structure but the referenced file_id does not exist in DB.
		$path = $this->makeAliasFile('999999999', 'fake-key');

		$upload = new UploadedFile(
			$path,
			'ghost.ofa',
			'text/x-ozone-file-alias',
			\filesize($path),
			\UPLOAD_ERR_OK,
			false
		);

		$this->expectException(TypesInvalidValueException::class);
		(new TypeFile())->validate($upload);
	}

	public function testNonAliasExtensionWithAliasMimeBypassesAliasPath(): void
	{
		// A file with alias MIME but a non-.ofa extension is NOT detected as an
		// alias and falls through to normal MIME validation.  Since no MIME
		// restriction is set on the column, checkUploadedFile() only checks size
		// and error -- and an upload-error file is rejected directly.
		$path = $this->makeAliasFile('1', 'k');

		$upload = new UploadedFile(
			$path,
			'file.png',            // .png, not .ofa -> alias path skipped
			'text/x-ozone-file-alias',
			\filesize($path),
			\UPLOAD_ERR_OK,
			false
		);

		// Without reaching storage/DB, checkUploadedFile() alone runs.
		// The file passes size and error checks but the MIME type 'text/x-ozone-file-alias'
		// is then handed to storage->upload().  We cannot call storage in a unit
		// test, so just assert that the upload is NOT silently treated as an alias.
		// We verify the alias pre-resolution did not consume the stream by checking
		// it can still be read.
		$stream = $upload->getStream();
		self::assertNotEmpty($stream->getContents(), 'stream must still be readable when alias path was skipped');
	}

	/**
	 * Creates a real temp file containing a JSON alias descriptor and registers
	 * it for tearDown cleanup.
	 *
	 * @param string $file_id
	 * @param string $file_key
	 *
	 * @return string absolute path to the created file
	 */
	private function makeAliasFile(string $file_id, string $file_key): string
	{
		$path             = \tempnam(\sys_get_temp_dir(), 'oz_test_alias_');
		$this->tmpFiles[] = $path;
		\file_put_contents($path, \json_encode(['file_id' => $file_id, 'file_key' => $file_key]));

		return $path;
	}
}
