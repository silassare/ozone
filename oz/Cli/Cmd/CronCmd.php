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

namespace OZONE\Core\Cli\Cmd;

use Kli\KliArgs;
use Kli\Types\KliTypeNumber;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Cli\Utils\Utils;
use Throwable;

/**
 * Class CronCmd.
 */
final class CronCmd extends Command
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage cron tasks.');

		if (Utils::isProjectLoaded()) {
			// action: run scheduled cron tasks
			$run = $this->action('run', 'Run the due cron tasks: every minute, from a scheduler (crontab, a timer).');

			$run->handler(static function (): void {
				CronRunner::tick(CronRunner::RUNNER_SCHEDULER);
			});

			// action: the scheduler as a long-running process
			$work = $this->action('work', 'Run the due cron tasks every minute, as a long-running process.');

			$work->option('max-time', 't')
				->description('Stop after this many seconds (0 = run indefinitely).')
				->type((new KliTypeNumber())->min(0)->def(0));

			$work->option('memory', 'e')
				->description('Stop when PHP memory usage exceeds this many MB (0 = unlimited).')
				->type((new KliTypeNumber())->min(0)->def(128));

			$work->handler(self::work(...));

			// action: start a specific cron task
			$start = $this->action('start', 'Start a specific cron task.');
			$start->option('name', 'n')
				->description('The name of the task to start.')
				->required()
				->string();

			$start->handler(static function (KliArgs $args): void {
				Cron::start($args->get('name'));
			});
		}
	}

	/**
	 * A tick at the start of every minute, until SIGTERM, SIGINT, `--max-time` or `--memory` stops it.
	 */
	private static function work(KliArgs $args): void
	{
		$max_time   = (int) $args->get('max-time');
		$max_mem_mb = (int) $args->get('memory');
		$started    = \time();
		$stop       = false;

		if (\function_exists('pcntl_signal')) {
			\pcntl_signal(\SIGTERM, static function () use (&$stop): void {
				$stop = true;
			});
			\pcntl_signal(\SIGINT, static function () use (&$stop): void {
				$stop = true;
			});
		}

		while (!$stop) {
			try {
				CronRunner::tick(CronRunner::RUNNER_SCHEDULER);
			} catch (Throwable $t) {
				// One failing tick stops nothing: the next minute is another tick.
				oz_logger()->error($t);
			}

			$next = (\intdiv(\time(), 60) + 1) * 60;

			// A second at a time, so a signal stops it at once.
			while (!$stop && \time() < $next) {
				\sleep(1);

				if (\function_exists('pcntl_signal_dispatch')) {
					\pcntl_signal_dispatch();
				}

				if ($max_time > 0 && (\time() - $started) >= $max_time) {
					$stop = true;
				}
			}

			if ($max_mem_mb > 0 && (\memory_get_usage(true) / 1_048_576) >= $max_mem_mb) {
				$stop = true;
			}
		}
	}
}
