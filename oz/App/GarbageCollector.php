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

namespace OZONE\Core\App;

use Override;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Utils\JSONResult;
use OZONE\Core\Utils\Random;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class GarbageCollector.
 *
 * Runs the registered collectors (expired sessions, auth entities, temporary files, cache
 * entries, ...) from the hourly `oz:gc` cron task and, while no scheduler runs cron, after the
 * response on 1 request in `OZ_GC_PROBABILITY`. Register collectors from a `boot()`.
 */
final class GarbageCollector implements BootHookReceiverInterface
{
	public const CRON_TASK = 'oz:gc';

	/**
	 * @var array<string, callable():void>
	 */
	private static array $collectors = [];

	/**
	 * Registers a collector; registering a name again replaces it.
	 *
	 * @param string          $name      unique name, e.g. `oz:sessions`
	 * @param callable():void $collector
	 */
	public static function register(string $name, callable $collector): void
	{
		self::$collectors[$name] = $collector;
	}

	/**
	 * Runs every collector. One failing is logged and does not stop the others.
	 */
	public static function run(): void
	{
		foreach (self::$collectors as $name => $collector) {
			try {
				$collector();
			} catch (Throwable $t) {
				oz_logger()->error($t, ['collector' => $name]);
			}
		}
	}

	/**
	 * Whether this request should run the collectors (`OZ_GC_PROBABILITY`): only while no scheduler
	 * runs cron, since its hourly `oz:gc` task collects then ({@see CronRunner::isScheduled()}).
	 */
	public static function shouldRunOnRequest(): bool
	{
		$frequency = (int) Settings::get('oz.gc', 'OZ_GC_PROBABILITY', 100);

		// the dice first: the check-in is read on those requests only
		return $frequency > 0 && Random::bool($frequency) && !CronRunner::isScheduled();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		FinishHook::listen(static function (): void {
			if (self::shouldRunOnRequest()) {
				self::run();
			}
		}, Event::RUN_LAST);

		CronCollect::listen(static function (): void {
			Cron::call(static function (JSONResult $result): void {
				self::run();
				$result->setDone();
			}, self::CRON_TASK)->everyHour();
		});
	}
}
