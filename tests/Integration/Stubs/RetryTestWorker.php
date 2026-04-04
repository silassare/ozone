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
use RuntimeException;

/**
 * Synchronous worker for RetryDelayTest.
 *
 * Increments a counter file on every run. Throws if a fail-flag file exists.
 */
final class RetryTestWorker implements WorkerInterface
{
	private JSONResult $result;

	public function __construct(
		private readonly string $counterFile,
		private readonly string $failFile,
	) {}

	public static function getName(): string
	{
		return 'retry-test-worker';
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface $job): static
	{
		$this->result = new JSONResult();

		$count = \is_file($this->counterFile)
			? (int) \trim((string) \file_get_contents($this->counterFile))
			: 0;
		\file_put_contents($this->counterFile, (string) ($count + 1));

		if (\is_file($this->failFile)) {
			throw new RuntimeException('Forced failure from RetryTestWorker.');
		}

		$this->result->setDone()->setData(['ok' => true]);

		return $this;
	}

	public function getResult(): JSONResult
	{
		return $this->result;
	}

	public static function fromPayload(array $payload): static
	{
		return new self($payload['counter_file'], $payload['fail_file']);
	}

	public function getPayload(): array
	{
		return [
			'counter_file' => $this->counterFile,
			'fail_file'    => $this->failFile,
		];
	}
}
