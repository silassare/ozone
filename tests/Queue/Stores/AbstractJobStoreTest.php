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

namespace OZONE\Tests\Queue\Stores;

use InvalidArgumentException;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\Job;
use OZONE\Core\Queue\JobContract;
use OZONE\Core\Queue\JobState;
use OZONE\Tests\Support\IntegrationTestCase;
use Throwable;

/**
 * Abstract contract test for {@link JobStoreInterface} implementations.
 *
 * Each concrete subclass provides a specific store via {@link makeStore()}.
 * A fresh unique queue name is created per test method to prevent cross-test
 * pollution and ensure tearDown can clean up reliably via the iterator.
 *
 * @internal
 *
 * @coversNothing
 */
abstract class AbstractJobStoreTest extends IntegrationTestCase
{
	protected JobStoreInterface $store;

	/** Unique queue name per test method - prevents cross-test pollution. */
	protected string $testQueue;

	/** @var JobContractInterface[] Jobs added in other queues that need explicit cleanup. */
	protected array $created = [];

	protected function setUp(): void
	{
		$this->store     = $this->makeStore();
		$this->testQueue = 'test-' . \uniqid('', true);
		$this->created   = [];
	}

	protected function tearDown(): void
	{
		// Clean up all jobs left in the test queue (catches orphans from crashed tests).
		foreach ($this->store->iterator($this->testQueue) as $job) {
			try {
				$this->store->delete($job);
			} catch (Throwable) {
				// best effort
			}
		}

		// Clean up any explicitly tracked jobs in other queues.
		foreach ($this->created as $job) {
			try {
				$this->store->delete($job);
			} catch (Throwable) {
				// best effort
			}
		}
	}

	public function testAddAndGetRoundtrip(): void
	{
		$job = $this->makeJob();
		$job->setPriority(3);
		$job->setRetryMax(5);
		$job->setRetryDelay(30);

		$contract = $this->store->add($job);

		self::assertSame($job->getRef(), $contract->getRef());

		$fetched = $this->store->get($contract->getRef());

		self::assertNotNull($fetched);
		self::assertSame($job->getRef(), $fetched->getRef());
		self::assertSame($job->getWorker(), $fetched->getWorker());
		self::assertSame($job->getQueue(), $fetched->getQueue());
		self::assertSame(JobState::PENDING, $fetched->getState());
		self::assertSame(3, $fetched->getPriority());
		self::assertSame(5, $fetched->getRetryMax());
		self::assertSame(30, $fetched->getRetryDelay());
	}

	public function testGetReturnsNullForUnknownRef(): void
	{
		self::assertNull($this->store->get('nonexistent-' . \uniqid('', true)));
	}

	public function testGetOrFailThrowsForUnknownRef(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->store->getOrFail('nonexistent-' . \uniqid('', true));
	}

	public function testUpdatePersistsStateChange(): void
	{
		$contract = $this->addJob();

		self::assertSame(JobState::PENDING, $contract->getState());

		$contract->setState(JobState::DONE);
		$this->store->update($contract);

		$fetched = $this->store->get($contract->getRef());

		self::assertNotNull($fetched);
		self::assertSame(JobState::DONE, $fetched->getState());
	}

	public function testUpdateCreatesRecordWhenNotFound(): void
	{
		// Build a contract not yet persisted to the store.
		$job      = $this->makeJob();
		$contract = new JobContract(
			$job->getRef(),
			$job->getWorker(),
			$job->getPayload(),
			$this->store
		);
		$contract->setQueue($this->testQueue)
			->setName($job->getName()); // job_name column requires min=1

		// update() must fall through to add() when the ref does not exist.
		$this->store->update($contract);

		$fetched = $this->store->get($contract->getRef());

		self::assertNotNull($fetched);
		self::assertSame($contract->getRef(), $fetched->getRef());
		self::assertSame($this->testQueue, $fetched->getQueue());
	}

	public function testDeleteRemovesRecord(): void
	{
		$contract = $this->store->add($this->makeJob());

		$this->store->delete($contract);

		self::assertNull($this->store->get($contract->getRef()));
	}

	public function testIteratorYieldsAllJobsInQueue(): void
	{
		$a = $this->addJob();
		$b = $this->addJob();

		$refs = [];

		foreach ($this->store->iterator($this->testQueue) as $job) {
			$refs[] = $job->getRef();
		}

		self::assertContains($a->getRef(), $refs);
		self::assertContains($b->getRef(), $refs);
	}

	public function testIteratorFiltersOnState(): void
	{
		$pending = $this->addJob();
		$running = $this->addJob();

		$running->setState(JobState::RUNNING);
		$this->store->update($running);

		$pendingRefs = [];

		foreach ($this->store->iterator($this->testQueue, null, JobState::PENDING) as $job) {
			$pendingRefs[] = $job->getRef();
		}

		self::assertContains($pending->getRef(), $pendingRefs);
		self::assertNotContains($running->getRef(), $pendingRefs);
	}

	public function testIteratorFiltersOnWorker(): void
	{
		$alpha = $this->addJob('', 'worker-alpha');
		$beta  = $this->addJob('', 'worker-beta');

		$alphaRefs = [];

		foreach ($this->store->iterator($this->testQueue, 'worker-alpha') as $job) {
			$alphaRefs[] = $job->getRef();
		}

		self::assertContains($alpha->getRef(), $alphaRefs);
		self::assertNotContains($beta->getRef(), $alphaRefs);
	}

	public function testListReturnsPaginatedResults(): void
	{
		// Add 4 jobs first.
		for ($i = 0; $i < 4; ++$i) {
			$this->addJob();
		}

		$page1 = $this->store->list($this->testQueue, null, null, null, 1, 2);
		$page2 = $this->store->list($this->testQueue, null, null, null, 2, 2);

		self::assertCount(2, $page1);
		self::assertCount(2, $page2);

		// All 4 unique refs must appear exactly once across both pages.
		$all = \array_unique(\array_map(
			static fn ($j) => $j->getRef(),
			\array_merge($page1, $page2)
		));

		self::assertCount(4, $all);
	}

	public function testLockReturnsTrueOnFirstAcquire(): void
	{
		$contract = $this->addJob();

		self::assertTrue($this->store->lock($contract));

		$this->store->unlock($contract);
	}

	public function testLockReturnsFalseWhenAlreadyLocked(): void
	{
		$contract = $this->addJob();

		self::assertTrue($this->store->lock($contract));
		self::assertFalse($this->store->lock($contract));

		$this->store->unlock($contract);
	}

	public function testUnlockReleasesLock(): void
	{
		$contract = $this->addJob();

		$this->store->lock($contract);
		$this->store->unlock($contract);

		// After unlock, acquiring the lock must succeed again.
		self::assertTrue($this->store->lock($contract));

		$this->store->unlock($contract);
	}

	public function testIsLockedReflectsLockState(): void
	{
		$contract = $this->addJob();

		self::assertFalse($this->store->isLocked($contract));

		$this->store->lock($contract);
		self::assertTrue($this->store->isLocked($contract));

		$this->store->unlock($contract);
		self::assertFalse($this->store->isLocked($contract));
	}

	public function testCountWithoutStateFilterStartsAtZero(): void
	{
		self::assertSame(0, $this->store->count($this->testQueue));
	}

	public function testCountWithoutStateFilterIncrementsOnAdd(): void
	{
		$this->addJob();
		self::assertSame(1, $this->store->count($this->testQueue));

		$this->addJob();
		self::assertSame(2, $this->store->count($this->testQueue));
	}

	public function testCountWithStateFilter(): void
	{
		$pending = $this->addJob();
		$running = $this->addJob();

		// Set one to RUNNING.
		$running->setState(JobState::RUNNING);
		$this->store->update($running);

		self::assertSame(1, $this->store->count($this->testQueue, JobState::PENDING));
		self::assertSame(1, $this->store->count($this->testQueue, JobState::RUNNING));
		self::assertSame(0, $this->store->count($this->testQueue, JobState::DONE));

		// Silence "unused variable" hint.
		unset($pending);
	}

	public function testCountIsolatedByQueue(): void
	{
		$otherQueue = 'other-' . \uniqid('', true);

		$this->addJob();                         // in $this->testQueue
		$this->addJob($otherQueue);              // in $otherQueue

		self::assertSame(1, $this->store->count($this->testQueue));
		self::assertSame(1, $this->store->count($otherQueue));
	}

	public function testCountDecreasesAfterDelete(): void
	{
		$contract = $this->store->add($this->makeJob()); // not via addJob() so we control deletion

		self::assertSame(1, $this->store->count($this->testQueue));

		$this->store->delete($contract);

		self::assertSame(0, $this->store->count($this->testQueue));
	}

	// ----- encryption roundtrip -------------------------------------------

	public function testEncryptedPayloadIsStoredObfuscatedAndDeserializedCorrectly(): void
	{
		$secret_payload = ['secret' => 'sensitive-value', 'token' => 'abc123'];
		$job            = $this->makeJob($this->testQueue, 'dummy', $secret_payload);
		$job->encrypted(); // opt in to payload encryption

		$contract = $this->store->add($job);

		// 1. The deserialized contract must expose the original plain-text payload.
		self::assertSame($secret_payload, $contract->getPayload());

		// 2. After reloading from the store, the payload must still be correct.
		$reloaded = $this->store->getOrFail($contract->getRef());
		self::assertSame($secret_payload, $reloaded->getPayload());

		// 3. The encryption flag is preserved so re-saves continue to encrypt.
		self::assertTrue($reloaded->shouldEncryptPayload());
	}

	/**
	 * Provide the store implementation under test.
	 */
	abstract protected function makeStore(): JobStoreInterface;

	protected function makeJob(
		string $queue = '',
		string $worker = 'dummy-test-worker',
		array $payload = ['k' => 'v']
	): Job {
		// md5() gives exactly 32 hex chars, satisfying the job_ref min=32 constraint.
		$ref = \md5(\uniqid('', true));

		return (new Job($ref, $worker, $payload))
			->setQueue($queue ?: $this->testQueue)
			->setName($worker); // job_name column requires min=1
	}

	protected function addJob(
		string $queue = '',
		string $worker = 'dummy',
		array $payload = ['k' => 'v']
	): JobContractInterface {
		$contract = $this->store->add($this->makeJob($queue, $worker, $payload));

		if ($queue && $queue !== $this->testQueue) {
			$this->created[] = $contract;
		}

		return $contract;
	}
}
