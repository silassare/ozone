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

use __PLH_NAMESPACE__\Workers\DaemonTestWorker;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for DaemonTest.
 *
 * Registers the worker. When the trigger file is present, dispatches one job
 * WITHOUT running it -- this simulates work arriving in the queue before the
 * daemon starts.
 */
final class DaemonTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(DaemonTestWorker::class);

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			Queue::get(Queue::DEFAULT)
				->push(new DaemonTestWorker('__PLH_FLAG_FILE__'))
				->dispatch();
		});
	}
}
