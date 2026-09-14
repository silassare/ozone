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
use OZONE\Core\FS\FS;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * Class FileScanWorker.
 *
 * Scans a file queued by {@see FileScan::afterInsert()} (async mode). A failed scan throws, so the
 * queue retries the job.
 *
 * The payload is `['file_id' => string]`.
 */
final class FileScanWorker implements WorkerInterface
{
	private JSONResult $result;

	/**
	 * FileScanWorker constructor.
	 *
	 * @param string $file_id
	 */
	public function __construct(private readonly string $file_id)
	{
		$this->result = new JSONResult();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return self::class;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAsync(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function work(JobContractInterface $job_contract): static
	{
		$file = FS::getFileByID($this->file_id);

		if (!$file) {
			$this->result->setDone()->setData(['file_id' => $this->file_id, 'skipped' => true]);

			return $this;
		}

		$state = FileScan::scanFile($file);

		$this->result->setDone()->setData(['file_id' => $this->file_id, 'state' => $state->value]);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromPayload(array $payload): static
	{
		return new self((string) $payload['file_id']);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPayload(): array
	{
		return ['file_id' => $this->file_id];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getResult(): JSONResult
	{
		return $this->result;
	}
}
