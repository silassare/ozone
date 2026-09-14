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
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Exceptions\FileScanRejectedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Enums\FileScanState;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Scan\FileScan;
use OZONE\Core\FS\Scan\FileScanWorker;
use OZONE\Core\Queue\JobState;
use OZONE\Tests\Support\FakeFileScanner;
use PHPUnit\Framework\TestCase;

/**
 * Class FileScanTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\Scan\FileScan
 * @covers \OZONE\Core\FS\Scan\FileScanWorker
 */
final class FileScanTest extends TestCase
{
	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		FakeFileScanner::reset();

		Settings::set('oz.files.scan', 'OZ_FILE_SCANNER', FakeFileScanner::class);
	}

	#[Override]
	protected function tearDown(): void
	{
		$keys = ['OZ_FILE_SCAN_ENABLED', 'OZ_FILE_SCAN_MODE', 'OZ_FILE_SCANNER', 'OZ_FILE_SCAN_SERVE_UNVERIFIED'];

		foreach ($keys as $key) {
			Settings::unset('oz.files.scan', $key);
		}

		parent::tearDown();
	}

	public function testDisabledScanLeavesFilesUnscanned(): void
	{
		$file = self::store('not scanned');

		self::assertSame(FileScanState::UNSCANNED, $file->getScanState());
		self::assertSame([], FakeFileScanner::$scanned);
	}

	public function testSyncScanMarksCleanFiles(): void
	{
		self::enable(FileScan::MODE_SYNC);

		$file = self::store('clean content');

		self::assertSame(FileScanState::CLEAN, $file->getScanState());
		self::assertSame(['clean content'], FakeFileScanner::$scanned);
		self::assertSame(FileScanState::CLEAN, FS::getFileByID((string) $file->getID())?->getScanState());
	}

	public function testSyncScanRejectsAndDeletesInfectedFiles(): void
	{
		self::enable(FileScan::MODE_SYNC);

		FakeFileScanner::$signature = 'Test-Signature';

		self::assertRejected('OZ_FILE_INFECTED');
	}

	public function testSyncScanRejectsFilesWhenTheScannerFails(): void
	{
		self::enable(FileScan::MODE_SYNC);

		FakeFileScanner::$fail = true;

		self::assertRejected('OZ_FILE_SCAN_FAILED');
	}

	public function testClonesAreNotScannedAgain(): void
	{
		self::enable(FileScan::MODE_SYNC);

		$clone = self::store('source')->cloneFile();
		$clone->save();

		self::assertSame(['source'], FakeFileScanner::$scanned);
		self::assertSame(FileScanState::CLEAN, $clone->getScanState());
	}

	public function testAsyncScanQueuesAJob(): void
	{
		self::enable(FileScan::MODE_ASYNC);

		$file = self::store('queued');

		self::assertSame(FileScanState::PENDING, $file->getScanState());
		self::assertSame([], FakeFileScanner::$scanned);

		$jobs = (new OZJobsQuery())
			->whereWorkerIs(FileScanWorker::class)
			->whereStateIs(JobState::PENDING)
			->find()
			->fetchAllClass();

		$payloads = \array_map(static fn ($job) => (array) $job->getPayload()->getData(), $jobs);

		self::assertContains(['file_id' => (string) $file->getID()], $payloads);
	}

	public function testScanFileSavesTheVerdictOnEveryRecordSharingTheContent(): void
	{
		self::enable(FileScan::MODE_ASYNC);

		$file  = self::store('shared');
		$clone = $file->cloneFile();
		$clone->save();

		FakeFileScanner::$signature = 'Test-Signature';

		self::assertSame(FileScanState::INFECTED, FileScan::scanFile($file));

		foreach ([$file, $clone] as $f) {
			$stored = FS::getFileByID((string) $f->getID());

			self::assertSame(FileScanState::INFECTED, $stored?->getScanState());
			self::assertSame('Test-Signature', $stored->getData()['scan_signature'] ?? null);
		}
	}

	public function testFailedScansAreMarkedAndRethrown(): void
	{
		self::enable(FileScan::MODE_ASYNC);

		$file = self::store('unreachable');

		FakeFileScanner::$fail = true;

		try {
			FileScan::scanFile($file);

			self::fail('The scan failure should be rethrown.');
		} catch (RuntimeException) {
			self::assertSame(FileScanState::FAILED, FS::getFileByID((string) $file->getID())?->getScanState());
		}
	}

	public function testServability(): void
	{
		$file     = new OZFile();
		$withheld = [FileScanState::INFECTED, FileScanState::PENDING, FileScanState::FAILED];

		foreach (FileScanState::cases() as $state) {
			$file->setScanState($state);

			self::assertSame(!\in_array($state, $withheld, true), FileScan::isServable($file), $state->value);
		}

		Settings::set('oz.files.scan', 'OZ_FILE_SCAN_SERVE_UNVERIFIED', true);

		self::assertTrue(FileScan::isServable($file->setScanState(FileScanState::PENDING)));
		self::assertTrue(FileScan::isServable($file->setScanState(FileScanState::FAILED)));
		self::assertFalse(FileScan::isServable($file->setScanState(FileScanState::INFECTED)));
	}

	private static function enable(string $mode): void
	{
		Settings::set('oz.files.scan', 'OZ_FILE_SCAN_ENABLED', true);
		Settings::set('oz.files.scan', 'OZ_FILE_SCAN_MODE', $mode);
	}

	private static function store(string $content): OZFile
	{
		$file = FS::getStorage(FS::PRIVATE_STORAGE)->saveRaw($content, 'text/plain', 'scan.txt');

		$file->save();

		return $file;
	}

	private static function assertRejected(string $code): void
	{
		$storage = FS::getStorage(FS::PRIVATE_STORAGE);
		$file    = $storage->saveRaw('rejected', 'text/plain', 'rejected.txt');

		try {
			$file->save();

			self::fail('The file should be rejected.');
		} catch (FileScanRejectedException $e) {
			self::assertSame($code, $e->getMessage());
			self::assertTrue($file->isNew());
			self::assertFalse($storage->exists($file));
		}
	}
}
