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
 * Minimal synchronous worker used by the integration test.
 *
 * Writes 'done' to a flag file so the test process can assert the job ran.
 */
final class TestJobWorker implements WorkerInterface
{
	private JSONResult $result;

	public function __construct(private readonly string $flagFile) {}

	public static function getName(): string
	{
		return 'test-job-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface $jobContract): static
	{
		$this->result = new JSONResult();
		\file_put_contents($this->flagFile, 'done');
		$this->result->setDone()->setData(['flag' => $this->flagFile]);

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
