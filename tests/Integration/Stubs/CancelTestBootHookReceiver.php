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

use __PLH_NAMESPACE__\Workers\CancelTestWorker;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for CancelJobTest.
 *
 * Registers the worker. When the trigger file is present, dispatches one job
 * and writes the job ref to a file so the test can pass it to `oz jobs cancel`.
 */
final class CancelTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(CancelTestWorker::class);

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			$contract = Queue::get(Queue::DEFAULT)
				->push(new CancelTestWorker('__PLH_FLAG_FILE__'))
				->dispatch();

			\file_put_contents('__PLH_REF_FILE__', $contract->getRef());
		});
	}
}
