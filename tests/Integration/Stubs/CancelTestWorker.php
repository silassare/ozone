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

namespace __PLH_NAMESPACE__\Workers;

use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * Minimal worker for CancelJobTest.
 *
 * Writes a flag file when it executes so the test can confirm the job ran
 * (or did not run when cancelled).
 */
final class CancelTestWorker implements WorkerInterface
{
	private JSONResult $result;

	public function __construct(private readonly string $flagFile) {}

	public static function getName(): string
	{
		return 'cancel-test-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface $job): static
	{
		$this->result = new JSONResult();
		\file_put_contents($this->flagFile, 'ran');
		$this->result->setDone()->setData(['ran' => true]);

		return $this;
	}

	public function getResult(): JSONResult
	{
		return $this->result;
	}

	public static function fromPayload(array $payload): static
	{
		return new self($payload['flag_file']);
	}

	public function getPayload(): array
	{
		return ['flag_file' => $this->flagFile];
	}
}
