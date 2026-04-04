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
 * Synchronous worker for JobPruneTest. Always succeeds.
 */
final class PruneTestWorker implements WorkerInterface
{
	private JSONResult $result;

	public function __construct(private readonly string $successFile) {}

	public static function getName(): string
	{
		return 'prune-test-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface $job): static
	{
		$this->result = new JSONResult();
		\file_put_contents($this->successFile, 'ok');
		$this->result->setDone()->setData(['ok' => true]);

		return $this;
	}

	public function getResult(): JSONResult
	{
		return $this->result;
	}

	public static function fromPayload(array $payload): static
	{
		return new self($payload['success_file']);
	}

	public function getPayload(): array
	{
		return ['success_file' => $this->successFile];
	}
}
