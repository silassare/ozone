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
 * An async worker: the job runs in a process of its own, `oz jobs run`, and says which SAPI that
 * process ran under and what its environment held.
 */
final class PhpCliTestWorker implements WorkerInterface
{
	private JSONResult $result;

	public function __construct(private readonly string $flagFile) {}

	public static function getName(): string
	{
		return 'php-cli-test-worker';
	}

	public function isAsync(): bool
	{
		return true;
	}

	public function work(JobContractInterface $job): static
	{
		$this->result = new JSONResult();
		\file_put_contents($this->flagFile, \json_encode([
			'sapi' => \PHP_SAPI,
			'pid'  => \getmypid(),
			'env'  => \getenv(),
		]));
		$this->result->setDone();

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
