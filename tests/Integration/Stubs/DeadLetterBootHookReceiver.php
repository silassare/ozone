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

use __PLH_NAMESPACE__\Workers\DeadLetterTestWorker;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for DeadLetterTest.
 *
 * Registers the worker and dispatches one job (retry_max=2, retry_delay=0)
 * when the trigger file is present.
 */
final class DeadLetterBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(DeadLetterTestWorker::class);

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			Queue::get(Queue::DEFAULT)
				->push(new DeadLetterTestWorker('__PLH_COUNTER_FILE__', '__PLH_FAIL_FILE__', '__PLH_SUCCESS_FILE__'))
				->setRetryMax(2)
				->setRetryDelay(0)
				->dispatch();
		});
	}
}
