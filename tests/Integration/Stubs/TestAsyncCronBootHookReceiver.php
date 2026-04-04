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

namespace __PLH_NAMESPACE__;

use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Cli\Cron\Tasks\CallableTask;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * Registers an async callable cron task via CronCollect.
 *
 * Uses inBackground() so the task is routed to the cron:async queue and
 * executed via a background subprocess. This verifies that CronTaskWorker
 * calls Cron::collect() in its constructor so the task registry is populated
 * in the subprocess context.
 */
final class TestAsyncCronBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		CronCollect::listen(static function () {
			$task = new CallableTask(
				'test-async-cron-flag-task',
				static function (JSONResult $result) {
					\file_put_contents('__PLH_FLAG_FILE__', 'async-cron-ok');
					$result->setDone()->setData(['flag' => '__PLH_FLAG_FILE__']);
				},
			);
			$task->inBackground();
			$task->schedule()->everyMinute();
			Cron::addTask($task);
		});
	}
}
