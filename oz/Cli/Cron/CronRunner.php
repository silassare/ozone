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

namespace OZONE\Core\Cli\Cron;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Stores\CacheRegistry;
use OZONE\Core\Stores\KeyValueStore;
use OZONE\Core\Stores\StateRegistry;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class CronRunner.
 *
 * Runs the cron ticks, whoever triggers them (`OZ_CRON_RUNNER`, `oz.cron`): a scheduler --
 * `oz cron run` every minute, or the `oz cron work` process --, an external service through the
 * `oz:cron` route ({@see CronEndpoint}), or, on a host with neither, the requests, once their
 * response is sent.
 *
 * Every tick checks in, in a store the servers of a project share (`oz:cron`): that is how a request
 * knows a scheduler runs, and whether the garbage collection can be left to it.
 */
final class CronRunner implements BootHookReceiverInterface
{
	public const RUNNER_AUTO      = 'auto';
	public const RUNNER_SCHEDULER = 'scheduler';
	public const RUNNER_REQUESTS  = 'requests';

	/**
	 * What ran a tick through the `oz:cron` route: an external scheduler.
	 */
	public const RUNNER_WEB = 'web';

	/**
	 * The state store of the check-ins (`oz.stores.state`).
	 */
	public const STORE = 'oz:cron';

	/**
	 * How recent a scheduler's tick must be for cron to run the hourly tasks (the garbage collection
	 * among them).
	 */
	private const SCHEDULED_WITHIN = 3900;

	private const LAST_TICK      = 'last_tick';
	private const LAST_SCHEDULER = 'last_scheduler';

	/**
	 * Runs a tick: dispatches the due tasks ({@see Cron::runDues()}), the minutes since the last tick
	 * included, then runs the cron queues.
	 *
	 * @param string $runner what runs it: one of the `RUNNER_*`
	 * @param bool   $inline run the background tasks in this process, as a request does, rather than
	 *                       in processes of their own
	 */
	public static function tick(string $runner, bool $inline = false): void
	{
		$last = self::lastTick();

		self::checkIn($runner);

		Cron::runDues($last['at'] ?? null);

		// The store Cron::runDues() dispatches to: a tick never waits on another one (a Redis
		// job store that is down, say).
		JobsManager::run(Queue::DEFAULT_STORE, Queue::CRON_SYNC);

		if (!$inline) {
			JobsManager::run(Queue::DEFAULT_STORE, Queue::CRON_ASYNC);

			return;
		}

		// A limit of 0 runs every background job in this process (JobsManager::runJob()).
		$async = Queue::get(Queue::CRON_ASYNC);
		$limit = $async->getMaxConcurrent();

		$async->setMaxConcurrent(0);

		try {
			JobsManager::run(Queue::DEFAULT_STORE, Queue::CRON_ASYNC);
		} finally {
			$async->setMaxConcurrent($limit);
		}
	}

	/**
	 * The last tick, whatever ran it, or null when none has.
	 *
	 * @return null|array{at: int, runner: string}
	 */
	public static function lastTick(): ?array
	{
		$tick = self::store()->get(self::LAST_TICK);

		if (!\is_array($tick) || !isset($tick['at'], $tick['runner'])) {
			return null;
		}

		return ['at' => (int) $tick['at'], 'runner' => (string) $tick['runner']];
	}

	/**
	 * When a scheduler, or the `oz:cron` route, last ran a tick; null when none has.
	 */
	public static function lastSchedulerTick(): ?int
	{
		$at = self::store()->get(self::LAST_SCHEDULER);

		return \is_int($at) ? $at : null;
	}

	/**
	 * Whether a scheduler runs cron -- `oz cron run`, `oz cron work` or the `oz:cron` route -- recently
	 * enough to dispatch the hourly tasks: requests running the due tasks on a host without one do not
	 * count.
	 */
	public static function isScheduled(): bool
	{
		try {
			$at = self::lastSchedulerTick();
		} catch (Throwable) {
			return false;
		}

		return null !== $at && (\time() - $at) <= self::SCHEDULED_WITHIN;
	}

	/**
	 * `OZ_CRON_RUNNER`: one of `RUNNER_AUTO`, `RUNNER_SCHEDULER`, `RUNNER_REQUESTS`.
	 */
	public static function mode(): string
	{
		return (string) Settings::get('oz.cron', 'OZ_CRON_RUNNER', self::RUNNER_AUTO);
	}

	/**
	 * `OZ_CRON_SCHEDULER_TIMEOUT`: seconds without a scheduler before requests run the due tasks.
	 */
	public static function schedulerTimeout(): int
	{
		return (int) Settings::get('oz.cron', 'OZ_CRON_SCHEDULER_TIMEOUT', 120);
	}

	/**
	 * Has this request run a tick once its response is sent, as the `oz:cron` route asks.
	 *
	 * @internal
	 */
	public static function tickAfterResponse(): void
	{
		CacheRegistry::runtime(self::class)->set('tick', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		FinishHook::listen(static function (): void {
			self::afterResponse();
		}, Event::RUN_LAST);
	}

	/**
	 * What a request does once its response is sent: the tick the `oz:cron` route asked for, or, the
	 * first time it is looked at in a minute on this server, the one a missing scheduler leaves to it.
	 */
	private static function afterResponse(): void
	{
		if (OZone::isCliMode() || Migrations::DB_NOT_INSTALLED_VERSION === Migrations::getInstalledDbVersion()) {
			return;
		}

		try {
			if (true === CacheRegistry::runtime(self::class)->get('tick')) {
				self::tick(self::RUNNER_WEB, true);

				return;
			}

			$mode = self::mode();

			if (self::RUNNER_SCHEDULER === $mode || !self::firstLookThisMinute()) {
				return;
			}

			if (self::RUNNER_AUTO === $mode) {
				$scheduler = self::lastSchedulerTick();

				if (null !== $scheduler && (\time() - $scheduler) <= self::schedulerTimeout()) {
					return;
				}
			}

			self::tick(self::RUNNER_REQUESTS, true);
		} catch (Throwable $t) {
			// The response is sent: a failing tick is the log's business, not the client's.
			oz_logger()->error($t);
		}
	}

	/**
	 * Whether this request is the first of its interval (`OZ_CRON_REQUESTS_INTERVAL`, a minute by
	 * default) to look, on this server: the others read one small file, not the shared store.
	 */
	private static function firstLookThisMinute(): bool
	{
		$dir      = \rtrim(app()->getProjectDir()->getRoot(), '/\\') . DS . '.ozone' . DS . 'cache';
		$file     = $dir . DS . 'cron.minute';
		$interval = \max(60, (int) Settings::get('oz.cron', 'OZ_CRON_REQUESTS_INTERVAL', 60));
		$slot     = (string) \intdiv(\time(), $interval);

		if (\is_file($file) && \file_get_contents($file) === $slot) {
			return false;
		}

		if (!\is_dir($dir)) {
			\mkdir($dir, 0o775, true);
		}

		// Two requests of the same slot may both pass: a tick is idempotent (Cron::runDues()).
		return false !== \file_put_contents($file, $slot);
	}

	private static function checkIn(string $runner): void
	{
		$now   = \time();
		$store = self::store();

		$store->set(self::LAST_TICK, ['at' => $now, 'runner' => $runner]);

		if (self::RUNNER_REQUESTS !== $runner) {
			$store->set(self::LAST_SCHEDULER, $now);
		}
	}

	private static function store(): KeyValueStore
	{
		return StateRegistry::store(self::STORE);
	}
}
