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

namespace OZONE\Tests\Cli\Cron;

use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Cli\Cron\Schedule;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;
use PHPUnit\Framework\TestCase;

/**
 * Class CronRunnerTest.
 *
 * Against the sandbox's database: the jobs a tick dispatches, and its check-in.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Cron\Cron
 * @covers \OZONE\Core\Cli\Cron\CronRunner
 */
final class CronRunnerTest extends TestCase
{
	public function testADueTaskIsDispatchedOnceAMinuteWhoeverRunsIt(): void
	{
		// both dispatches in the same minute
		if (\time() % 60 >= 57) {
			\sleep(4);
		}

		$name = self::task(static fn (Schedule $schedule) => $schedule->everyMinute());

		Cron::runDues();
		// another server's scheduler, a request, the oz:cron route: the same minute again
		Cron::runDues();

		self::assertSame(1, self::jobs($name));
	}

	public function testTheMinutesSinceTheLastTickAreCaughtUpOnce(): void
	{
		// Due five minutes ago only (in the hour), a minute no tick ran.
		$past = (\intdiv(\time(), 60) - 5) * 60;
		$name = self::task(static fn (Schedule $schedule) => $schedule->everyHourAt((int) \gmdate('i', $past)));

		// the last tick, ten minutes ago
		Cron::runDues(\time() - 600);

		self::assertSame(1, self::jobs($name));
		self::assertNotNull(JobsManager::getStore(Queue::DEFAULT_STORE)->get(Cron::jobRef($name, $past)));

		Cron::runDues(\time() - 600);

		self::assertSame(1, self::jobs($name));
	}

	public function testASchedulerTickChecksIn(): void
	{
		CronRunner::tick(CronRunner::RUNNER_SCHEDULER, true);

		$last = CronRunner::lastTick();

		self::assertNotNull($last);
		self::assertSame(CronRunner::RUNNER_SCHEDULER, $last['runner']);
		self::assertLessThanOrEqual(2, \time() - $last['at']);
		self::assertSame($last['at'], CronRunner::lastSchedulerTick());
		self::assertTrue(CronRunner::isScheduled());
	}

	public function testARequestTickIsNoScheduler(): void
	{
		CronRunner::tick(CronRunner::RUNNER_SCHEDULER, true);

		$scheduler = CronRunner::lastSchedulerTick();

		CronRunner::tick(CronRunner::RUNNER_REQUESTS, true);

		self::assertSame(CronRunner::RUNNER_REQUESTS, CronRunner::lastTick()['runner'] ?? null);
		self::assertSame($scheduler, CronRunner::lastSchedulerTick());
	}

	/**
	 * Registers a task doing nothing, and returns its name.
	 *
	 * @param callable(Schedule):mixed $schedule
	 */
	private static function task(callable $schedule): string
	{
		$name = 'test:cron:' . \bin2hex(\random_bytes(6));

		$schedule(Cron::call(static function (JSONResult $result): void {
			$result->setDone();
		}, $name));

		return $name;
	}

	private static function jobs(string $name): int
	{
		return (int) (new OZJobsQuery())->whereNameIs($name)->find()->getTotal();
	}
}
