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

use OZONE\Core\Queue\Job;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;
use OZONE\Tests\Support\IntegrationTestCase;
use Throwable;

/**
 * @internal
 *
 * @coversNothing
 */
final class JobTest extends IntegrationTestCase
{
	/** @var string[] */
	private array $createdRefs = [];

	protected function tearDown(): void
	{
		$store = JobsManager::getStore(Queue::DEFAULT_STORE);

		foreach ($this->createdRefs as $ref) {
			try {
				$contract = $store->get($ref);

				if ($contract) {
					$store->delete($contract);
				}
			} catch (Throwable) {
				// best effort
			}
		}

		$this->createdRefs = [];
	}

	public function testConstructorSetsFields(): void
	{
		$job = new Job('ref-001', 'my-worker', ['key' => 'val']);

		self::assertSame('ref-001', $job->getRef());
		self::assertSame('my-worker', $job->getWorker());
		self::assertSame(['key' => 'val'], $job->getPayload());
	}

	public function testDefaultStateIsPending(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertSame(JobState::PENDING, $job->getState());
	}

	public function testDefaultQueueIsDefault(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertSame(Queue::DEFAULT, $job->getQueue());
	}

	public function testSetQueueAndGet(): void
	{
		$job = new Job('ref', 'worker', []);
		$job->setQueue('my-queue');

		self::assertSame('my-queue', $job->getQueue());
	}

	public function testSetPriorityAndGet(): void
	{
		$job = new Job('ref', 'worker', []);
		$job->setPriority(5);

		self::assertSame(5, $job->getPriority());
	}

	public function testSetStateAndGet(): void
	{
		$job = new Job('ref', 'worker', []);
		$job->setState(JobState::RUNNING);

		self::assertSame(JobState::RUNNING, $job->getState());
	}

	public function testIncrementTryCount(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertSame(0, $job->getTryCount());

		$job->incrementTryCount();
		self::assertSame(1, $job->getTryCount());

		$job->incrementTryCount();
		self::assertSame(2, $job->getTryCount());
	}

	public function testDefaultStartedAtIsNull(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertNull($job->getStartedAt());
	}

	public function testSetStartedAt(): void
	{
		$job = new Job('ref', 'worker', []);
		$job->setStartedAt(1700000000.5);

		self::assertSame(1700000000.5, $job->getStartedAt());
	}

	public function testDefaultEndedAtIsNull(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertNull($job->getEndedAt());
	}

	public function testSetRetryMaxAndGet(): void
	{
		$job = new Job('ref', 'worker', []);
		$job->setRetryMax(5);

		self::assertSame(5, $job->getRetryMax());
	}

	public function testDefaultRetryMax(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertSame(3, $job->getRetryMax());
	}

	public function testSetRetryDelayAndGet(): void
	{
		$job = new Job('ref', 'worker', []);
		$job->setRetryDelay(120);

		self::assertSame(120, $job->getRetryDelay());
	}

	public function testResultRoundtrip(): void
	{
		$job    = new Job('ref', 'worker', []);
		$result = new JSONResult();
		$result->setDone()->setData(['foo' => 'bar']);
		$job->setResult($result);

		self::assertFalse($job->getResult()->isError());
		self::assertSame('bar', $job->getResult()->getDataKey('foo'));
	}

	public function testDefaultResultIsNotError(): void
	{
		$job = new Job('ref', 'worker', []);

		self::assertInstanceOf(JSONResult::class, $job->getResult());
		self::assertFalse($job->getResult()->isError());
	}

	public function testDispatchPersistsToDefaultStore(): void
	{
		$ref                 = 'test-dispatch-' . \uniqid('', true);
		$this->createdRefs[] = $ref;

		$job      = (new Job($ref, 'dummy-worker', ['x' => 1]))->setQueue('test-dispatch')->setName('test-dispatch-job');
		$contract = $job->dispatch();

		self::assertSame($ref, $contract->getRef());
		self::assertSame(JobState::PENDING, $contract->getState());

		$fetched = JobsManager::getStore(Queue::DEFAULT_STORE)->get($ref);
		self::assertNotNull($fetched);
		self::assertSame($ref, $fetched->getRef());
		self::assertSame('test-dispatch', $fetched->getQueue());
	}
}
