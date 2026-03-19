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

namespace OZONE\Tests\Queue;

use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Queue\Job;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class QueueTest extends TestCase
{
	public function testGetCreatesQueueWithCorrectName(): void
	{
		$name = 'test-name-' . \uniqid('', true);
		$q    = Queue::get($name);

		self::assertSame($name, $q->getName());
	}

	public function testGetReturnsSameInstanceForSameName(): void
	{
		$name = 'test-singleton-' . \uniqid('', true);

		self::assertSame(Queue::get($name), Queue::get($name));
	}

	public function testDefaultMaxConcurrentIsNull(): void
	{
		$q = new Queue('test-default-concurrent-' . \uniqid('', true));

		self::assertNull($q->getMaxConcurrent());
	}

	public function testSetMaxConcurrentPersists(): void
	{
		$q = new Queue('test-set-concurrent-' . \uniqid('', true));
		$q->setMaxConcurrent(5);

		self::assertSame(5, $q->getMaxConcurrent());
	}

	public function testSetMaxConcurrentAcceptsNull(): void
	{
		$q = new Queue('test-null-concurrent-' . \uniqid('', true));
		$q->setMaxConcurrent(3);
		$q->setMaxConcurrent(null);

		self::assertNull($q->getMaxConcurrent());
	}

	public function testSetMaxConcurrentReturnsFluentInterface(): void
	{
		$q = new Queue('test-fluent-' . \uniqid('', true));

		self::assertSame($q, $q->setMaxConcurrent(2));
	}

	public function testDefaultStopOnErrorIsFalse(): void
	{
		$q = new Queue('test-stop-' . \uniqid('', true));

		self::assertFalse($q->shouldStopOnError());
	}

	public function testEnableStopOnError(): void
	{
		$q = new Queue('test-enable-stop-' . \uniqid('', true));
		$q->enableStopOnError();

		self::assertTrue($q->shouldStopOnError());
	}

	public function testDefaultMaxConsecutiveErrorsCount(): void
	{
		$q = new Queue('test-consecutive-' . \uniqid('', true));

		self::assertSame(3, $q->getMaxConsecutiveErrorsCount());
	}

	public function testSetMaxConsecutiveErrorsCount(): void
	{
		$q = new Queue('test-set-consecutive-' . \uniqid('', true));
		$q->setMaxConsecutiveErrorsCount(7);

		self::assertSame(7, $q->getMaxConsecutiveErrorsCount());
	}

	public function testDefaultMaxErrorsCount(): void
	{
		$q = new Queue('test-max-errors-' . \uniqid('', true));

		self::assertSame(10, $q->getMaxErrorsCount());
	}

	public function testSetMaxErrorsCount(): void
	{
		$q = new Queue('test-set-max-errors-' . \uniqid('', true));
		$q->setMaxErrorsCount(20);

		self::assertSame(20, $q->getMaxErrorsCount());
	}

	public function testPushCreatesJobWithCorrectQueueName(): void
	{
		$q = new Queue('push-queue-' . \uniqid('', true));

		$worker = new class implements WorkerInterface {
			public static function getName(): string
			{
				return 'test-push-worker';
			}

			public function work(JobContractInterface $c): static
			{
				return $this;
			}

			public function isAsync(): bool
			{
				return false;
			}

			public function getResult(): JSONResult
			{
				return new JSONResult();
			}

			public static function fromPayload(array $p): static
			{
				return new self();
			}

			public function getPayload(): array
			{
				return [];
			}
		};

		$job = $q->push($worker);

		self::assertInstanceOf(Job::class, $job);
		self::assertSame($q->getName(), $job->getQueue());
		self::assertSame($worker::getName(), $job->getWorker());
	}
}
