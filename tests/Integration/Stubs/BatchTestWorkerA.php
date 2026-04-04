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
 * First batch worker. Writes a flag file and succeeds.
 */
final class BatchTestWorkerA implements WorkerInterface
{
	private JSONResult $result;

	public function __construct(private readonly string $flagFile) {}

	public static function getName(): string
	{
		return 'batch-test-worker-a';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface $job): static
	{
		$this->result = new JSONResult();
		\file_put_contents($this->flagFile, 'ok');
		$this->result->setDone()->setData(['worker' => 'A']);

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
