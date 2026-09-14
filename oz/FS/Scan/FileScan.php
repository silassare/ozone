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

namespace OZONE\Core\FS\Scan;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZFilesQuery;
use OZONE\Core\Exceptions\FileScanRejectedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Enums\FileScanState;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Scan\Interfaces\FileScannerInterface;
use OZONE\Core\FS\Traits\FileEntityTrait;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;
use Throwable;

/**
 * Class FileScan.
 *
 * Optional virus scan of new files (`oz.files.scan`, off by default). Every new file record goes
 * through it ({@see FileEntityTrait::save()}), whatever its storage:
 *
 * - `sync` mode: the content is scanned before the record is inserted; an infected content, or one
 *   that could not be scanned, is deleted from its storage and rejected with a
 *   {@see FileScanRejectedException}.
 * - `async` mode: the file is inserted `pending` and a {@see FileScanWorker} job scans it.
 *
 * Clones share the content of their source, so they are not scanned again; the state of a content
 * is set on every file record sharing it.
 */
final class FileScan implements BootHookReceiverInterface
{
	public const MODE_SYNC  = 'sync';
	public const MODE_ASYNC = 'async';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		// Registered even when scanning is off, so queued scans still run after it is turned off.
		JobsManager::registerWorker(FileScanWorker::class);
	}

	/**
	 * Checks if new files are scanned.
	 */
	public static function isEnabled(): bool
	{
		return (bool) Settings::get('oz.files.scan', 'OZ_FILE_SCAN_ENABLED', false);
	}

	/**
	 * Checks if new files are scanned by a queue job instead of before their insertion.
	 */
	public static function isAsync(): bool
	{
		return self::MODE_ASYNC === Settings::get('oz.files.scan', 'OZ_FILE_SCAN_MODE', self::MODE_SYNC);
	}

	/**
	 * Gets the scanner set in `OZ_FILE_SCANNER`.
	 */
	public static function scanner(): FileScannerInterface
	{
		$class = Settings::get('oz.files.scan', 'OZ_FILE_SCANNER');

		if (!\is_string($class) || !\is_subclass_of($class, FileScannerInterface::class)) {
			throw new RuntimeException(\sprintf(
				'The file scanner "%s" should implement "%s".',
				\is_string($class) ? $class : \get_debug_type($class),
				FileScannerInterface::class
			));
		}

		return $class::fromSettings();
	}

	/**
	 * Called for a new file record, before its insertion.
	 *
	 * @throws FileScanRejectedException in sync mode, when the content is infected or could not be scanned
	 */
	public static function beforeInsert(OZFile $file): void
	{
		if (!self::isEnabled() || $file->getCloneID()) {
			return;
		}

		if (self::isAsync()) {
			$file->setScanState(FileScanState::PENDING);

			return;
		}

		try {
			$result = self::scanner()->scan(FS::getStorage($file->getStorage())->getStream($file));
		} catch (Throwable $t) {
			self::discard($file);

			throw new FileScanRejectedException('OZ_FILE_SCAN_FAILED', null, $t);
		}

		if ($result->isInfected()) {
			self::discard($file);

			throw new FileScanRejectedException('OZ_FILE_INFECTED', ['_signature' => $result->signature]);
		}

		$file->setScanState(FileScanState::CLEAN);
	}

	/**
	 * Called for a new file record, once inserted: queues its scan in async mode.
	 *
	 * The job store of the default queue is the database, so the job is only committed with the file.
	 */
	public static function afterInsert(OZFile $file): void
	{
		if (FileScanState::PENDING !== $file->getScanState() || $file->getCloneID()) {
			return;
		}

		Queue::get((string) Settings::get('oz.files.scan', 'OZ_FILE_SCAN_QUEUE', Queue::DEFAULT))
			->push(new FileScanWorker((string) $file->getID()))
			->dispatch();
	}

	/**
	 * Scans a stored file and saves the verdict on every file record sharing its content.
	 *
	 * An infected content is deleted from its storage when no clone uses it.
	 *
	 * @return FileScanState CLEAN or INFECTED
	 *
	 * @throws Throwable when the scan fails; the file is then marked FAILED
	 */
	public static function scanFile(OZFile $file): FileScanState
	{
		try {
			$result = self::scanner()->scan(FS::getStorage($file->getStorage())->getStream($file));
		} catch (Throwable $t) {
			self::saveState($file, FileScanState::FAILED);

			throw $t;
		}

		$state = $result->state();

		self::saveState($file, $state, $result->signature);

		if ($result->isInfected()) {
			self::discard($file);
		}

		return $state;
	}

	/**
	 * Checks if a file may be served: never when infected; when pending or failed, only if
	 * `OZ_FILE_SCAN_SERVE_UNVERIFIED` allows it.
	 */
	public static function isServable(OZFile $file): bool
	{
		return match ($file->getScanState()) {
			FileScanState::INFECTED                        => false,
			FileScanState::PENDING, FileScanState::FAILED  => (bool) Settings::get(
				'oz.files.scan',
				'OZ_FILE_SCAN_SERVE_UNVERIFIED',
				false
			),
			default => true,
		};
	}

	/**
	 * Saves a scan state on the file and the other records sharing its content.
	 */
	private static function saveState(OZFile $file, FileScanState $state, ?string $signature = null): void
	{
		$files = (new OZFilesQuery())
			->whereStorageIs($file->getStorage())
			->whereRefIs($file->getRef())
			->find()
			->lazy();

		foreach ($files as $f) {
			if (null !== $signature) {
				$data                   = $f->getData();
				$data['scan_signature'] = $signature;

				$f->setData($data);
			}

			$f->setScanState($state)->save();
		}

		$file->setScanState($state);
	}

	/**
	 * Deletes a rejected content from its storage.
	 */
	private static function discard(OZFile $file): void
	{
		try {
			FS::getStorage($file->getStorage())->delete($file);
		} catch (Throwable $t) {
			oz_logger()->error($t);
		}
	}
}
