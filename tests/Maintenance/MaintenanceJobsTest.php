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

namespace OZONE\Tests\Maintenance;

use Override;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Db\OZJob;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Maintenance\Maintenance;
use OZONE\Core\Maintenance\MaintenanceMode;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Job;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Stores\StateRegistry;
use OZONE\Core\Utils\JSONResult;
use OZONE\Tests\Support\QueueOrderWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * Class MaintenanceJobsTest.
 *
 * Cron and the queued jobs under a maintenance: they keep running unless it pauses them, and a pause
 * leaves the work where it was, for after `off`.
 *
 * @internal
 */
#[CoversClass(JobsManager::class)]
#[CoversClass(CronRunner::class)]
#[CoversClass(GarbageCollector::class)]
final class MaintenanceJobsTest extends TestCase
{
	private const string QUEUE = 'maintenance-test';

	#[Override]
	protected function setUp(): void
	{
		QueueOrderWorker::reset();

		if (!isset(JobsManager::getWorkers()[QueueOrderWorker::NAME])) {
			JobsManager::registerWorker(QueueOrderWorker::class);
		}
	}

	#[Override]
	protected function tearDown(): void
	{
		Maintenance::disable();
		Settings::unset('oz.gc', 'OZ_GC_PROBABILITY');
	}

	public function testAMaintenanceThatDoesNotPauseTheJobsLeavesThemRunning(): void
	{
		self::maintenance(false);

		$job = self::addJob('kept');

		self::assertSame(1, JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE));
		self::assertSame(['kept'], QueueOrderWorker::ran());
		self::assertSame(JobState::DONE, self::reread($job)->getState());
	}

	public function testPausedJobsWaitPendingAndRunAfterOff(): void
	{
		$job = self::addJob('waits');

		self::maintenance(true);

		self::assertSame(0, JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE));
		self::assertFalse(JobsManager::runJob(self::reread($job)));
		self::assertSame([], QueueOrderWorker::ran());

		$waiting = self::reread($job);

		// not failed, not dropped, not left locked: as it was queued
		self::assertSame(JobState::PENDING, $waiting->getState());
		self::assertFalse($waiting->isLocked());
		self::assertSame(0, $waiting->getTryCount());

		Maintenance::disable();

		self::assertSame(1, JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE));
		self::assertSame(['waits'], QueueOrderWorker::ran());
		self::assertSame(JobState::DONE, self::reread($job)->getState());
	}

	public function testAForcedRunIsRefusedWhileThePauseLastsUnlessToldOtherwise(): void
	{
		$job = self::takenJob('forced');

		self::maintenance(true);

		try {
			JobsManager::forceRunJob(self::reread($job));
			self::fail('A forced run went through a pause.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('jobs are paused by a maintenance', $e->getMessage());
		}

		// refused, and left exactly as it was
		self::assertSame([], QueueOrderWorker::ran());
		self::assertSame(JobState::RUNNING, self::reread($job)->getState());
		self::assertSame(0, self::reread($job)->getTryCount());

		JobsManager::forceRunJob(self::reread($job), true);

		self::assertSame(['forced'], QueueOrderWorker::ran());
		self::assertSame(JobState::DONE, self::reread($job)->getState());
	}

	public function testASingleJobRunsDespiteAPauseOnlyWhenTold(): void
	{
		$job = self::addJob('told');

		self::maintenance(true);

		self::assertFalse(JobsManager::runJob(self::reread($job)));
		self::assertTrue(JobsManager::runJob(self::reread($job), true));
		self::assertSame(['told'], QueueOrderWorker::ran());
	}

	public function testAJobHandedOffBeforeThePauseIsGivenBackUntried(): void
	{
		$job = self::takenJob('handed');

		self::maintenance(true);

		self::assertFalse(JobsManager::runHandedOff(self::reread($job)));

		$back = self::reread($job);

		self::assertSame([], QueueOrderWorker::ran());
		self::assertSame(JobState::PENDING, $back->getState());
		self::assertFalse($back->isLocked());
		self::assertSame(0, $back->getTryCount());

		Maintenance::disable();

		self::assertSame(1, JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE));
		self::assertSame(['handed'], QueueOrderWorker::ran());
	}

	public function testAJobHandedOffRunsWhenNothingIsPaused(): void
	{
		$job = self::takenJob('handed-on');

		self::maintenance(false);

		self::assertTrue(JobsManager::runHandedOff(self::reread($job)));
		self::assertSame(['handed-on'], QueueOrderWorker::ran());
		self::assertSame(JobState::DONE, self::reread($job)->getState());
	}

	public function testACronTaskStartedByHandWaitsForThePauseUnlessToldOtherwise(): void
	{
		$name = 'test:maintenance:start:' . \bin2hex(\random_bytes(6));
		$ran  = 0;

		Cron::call(static function (JSONResult $result) use (&$ran): void {
			++$ran;
			$result->setDone();
		}, $name)->everyHour();

		self::maintenance(true);

		try {
			Cron::start($name);
			self::fail('A cron task started by hand went through a pause.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('jobs are paused by a maintenance', $e->getMessage());
		}

		self::assertSame(0, $ran);

		Cron::start($name, true);

		self::assertSame(1, $ran);
	}

	public function testAPausedRunLeavesTheJobsOfADeadProcessAsTheyAre(): void
	{
		$job = self::addJob('stranded');

		// Taken by a process that died, long ago: a run settles it as a failed attempt.
		new OZJobsQuery()
			->whereRefIs($job->getRef())
			->update([
				OZJob::COL_STATE      => JobState::RUNNING->value,
				OZJob::COL_LOCKED     => true,
				OZJob::COL_UPDATED_AT => '1',
			])
			->execute();

		self::maintenance(true);

		JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE);

		// not while the jobs are paused: settling it is a write, to a store a migration may be changing
		self::assertSame(JobState::RUNNING, self::reread($job)->getState());
		self::assertSame(0, self::reread($job)->getTryCount());

		Maintenance::disable();

		JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE);

		self::assertSame(1, self::reread($job)->getTryCount());
	}

	public function testAPausedTickDispatchesNothingAndDoesNotCheckInSoTheNextOneCatchesUp(): void
	{
		self::checkedIn('LAST_TICK', ['at' => \time() - 180, 'runner' => CronRunner::RUNNER_SCHEDULER]);

		$before = CronRunner::lastTick();
		$name   = self::task();

		self::maintenance(true);

		self::assertFalse(CronRunner::tick(CronRunner::RUNNER_SCHEDULER, true));
		self::assertSame($before, CronRunner::lastTick());
		self::assertSame(0, self::jobsOf($name));

		Maintenance::disable();

		self::assertTrue(CronRunner::tick(CronRunner::RUNNER_SCHEDULER, true));
		self::assertSame(1, self::jobsOf($name));
	}

	public function testATickRunsUnderAMaintenanceThatDoesNotPauseTheJobs(): void
	{
		self::checkedIn('LAST_TICK', ['at' => \time() - 180, 'runner' => CronRunner::RUNNER_SCHEDULER]);

		$name = self::task();

		self::maintenance(false);

		self::assertTrue(CronRunner::tick(CronRunner::RUNNER_SCHEDULER, true));
		self::assertSame(1, self::jobsOf($name));
	}

	public function testARequestCollectsNoGarbageWhileTheJobsArePaused(): void
	{
		// Every request collects while no scheduler has checked in: the cron task it stands in for is
		// the one a pause holds.
		Settings::set('oz.gc', 'OZ_GC_PROBABILITY', 1);
		self::checkedIn('LAST_SCHEDULER', \time() - 86_400);

		self::assertTrue(GarbageCollector::shouldRunOnRequest());

		self::maintenance(false);

		self::assertTrue(GarbageCollector::shouldRunOnRequest());

		self::maintenance(true);

		self::assertFalse(GarbageCollector::shouldRunOnRequest());
	}

	/**
	 * Writes a check-in of cron as an earlier tick left it: a tick only dispatches the minutes after
	 * the last one, so one earlier in the same minute would leave a test's task undispatched.
	 *
	 * @param string $constant the name of the {@see CronRunner} constant holding the key
	 */
	private static function checkedIn(string $constant, mixed $value): void
	{
		$key = new ReflectionClassConstant(CronRunner::class, $constant)->getValue();

		StateRegistry::store(CronRunner::STORE)->set((string) $key, $value);
	}

	private static function maintenance(bool $pause_jobs): void
	{
		[$mode] = MaintenanceMode::start(pause_jobs: $pause_jobs);

		Maintenance::enable($mode);
	}

	private static function addJob(string $label): JobContractInterface
	{
		$job = new Job(\md5(\uniqid('', true)), QueueOrderWorker::NAME, ['label' => $label])
			->setQueue(self::QUEUE)
			->setName(QueueOrderWorker::NAME);

		return JobsManager::getStore(Queue::DEFAULT_STORE)->add($job);
	}

	/**
	 * A job as a run leaves it when it hands it off to a process of its own: locked, running.
	 */
	private static function takenJob(string $label): JobContractInterface
	{
		$job = self::addJob($label);

		self::assertTrue($job->lock());

		$job->setState(JobState::RUNNING);
		$job->save();

		return $job;
	}

	private static function reread(JobContractInterface $job): JobContractInterface
	{
		return JobsManager::getStore(Queue::DEFAULT_STORE)->getOrFail($job->getRef());
	}

	/**
	 * Registers a task due every minute and doing nothing, and returns its name.
	 */
	private static function task(): string
	{
		$name = 'test:maintenance:' . \bin2hex(\random_bytes(6));

		Cron::call(static function (JSONResult $result): void {
			$result->setDone();
		}, $name)->everyMinute();

		return $name;
	}

	private static function jobsOf(string $name): int
	{
		return new OZJobsQuery()->whereNameIs($name)->find()->getTotal();
	}
}
