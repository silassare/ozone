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

use __PLH_NAMESPACE__\Workers\PhpCliTestWorker;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Utils\JSONResult;

/**
 * A cron task that, wherever the tick runs it, dispatches a job of an async worker and runs its
 * queue: from a request's tick, the job's process is started by the process serving the request.
 * And a command task, a program of the project's that is not PHP, which writes down the environment
 * it was started with.
 */
final class PhpCliTestBootHookReceiver implements BootHookReceiverInterface
{
	public const string QUEUE = 'php-cli-test';

	public static function boot(): void
	{
		JobsManager::registerWorker(PhpCliTestWorker::class);

		CronCollect::listen(static function (): void {
			Cron::call(static function (JSONResult $result): void {
				\file_put_contents('__PLH_TICK_FILE__', \json_encode([
					'sapi'   => \PHP_SAPI,
					'binary' => \PHP_BINARY,
					'pid'    => \getmypid(),
				]));

				Queue::get(self::QUEUE)->push(new PhpCliTestWorker('__PLH_FLAG_FILE__'))->dispatch();
				JobsManager::run(Queue::DEFAULT_STORE, self::QUEUE);

				$result->setDone();
			}, 'php-cli-test-task')->everyMinute();

			// Absolute paths: the request this may run from has no PATH, as a PHP-FPM pool's does not.
			Cron::command(
				['/bin/sh', '-c', '/usr/bin/env > ' . \escapeshellarg('__PLH_ENV_FILE__')],
				'php-cli-test-command',
				'',
				false
			)->everyMinute();
		});
	}
}
