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

use __PLH_NAMESPACE__\Workers\RetryTestWorker;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for RetryDelayTest.
 *
 * Registers the RetryTestWorker and dispatches one job (via InitHook) only
 * when the trigger file is present, consuming it immediately.
 */
final class RetryTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(RetryTestWorker::class);

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			Queue::get(Queue::DEFAULT)
				->push(new RetryTestWorker('__PLH_COUNTER_FILE__', '__PLH_FAIL_FILE__'))
				->setRetryMax(3)
				->setRetryDelay(3600)
				->dispatch();
		});
	}
}
