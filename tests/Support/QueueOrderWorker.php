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

namespace OZONE\Tests\Support;

use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * A worker that does nothing but say it ran, and in what order.
 *
 * What a queue test needs of a worker is that running it be cheap and that the run be observable:
 * the labels name the jobs in the order they were taken, which is what tells a queue that honours
 * priority from one that happens to be in the right order.
 *
 * @internal
 */
final class QueueOrderWorker implements WorkerInterface
{
	public const string NAME = 'queue-order-test-worker';

	/**
	 * The label of every job run since the last {@see self::reset()}, in order.
	 *
	 * @var string[]
	 */
	private static array $ran = [];

	public function __construct(private readonly string $label) {}

	/**
	 * The labels of the jobs run so far, in the order they were taken.
	 *
	 * @return string[]
	 */
	public static function ran(): array
	{
		return self::$ran;
	}

	/**
	 * Forgets what ran, so one test does not read another's run.
	 */
	public static function reset(): void
	{
		self::$ran = [];
	}

	public static function getName(): string
	{
		return self::NAME;
	}

	public function isAsync(): bool
	{
		return false;
	}

	public function work(JobContractInterface $job): static
	{
		self::$ran[] = $this->label;

		return $this;
	}

	public function getResult(): JSONResult
	{
		return new JSONResult()->setDone();
	}

	public static function fromPayload(array $payload): static
	{
		return new self((string) ($payload['label'] ?? ''));
	}

	public function getPayload(): array
	{
		return ['label' => $this->label];
	}
}
