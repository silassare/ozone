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

use __PLH_NAMESPACE__\Workers\BatchTestWorkerA;
use __PLH_NAMESPACE__\Workers\BatchTestWorkerB;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\BatchManager;
use OZONE\Core\Queue\Hooks\BatchFinished;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for JobBatchTest.
 *
 * Registers both batch workers, listens for BatchFinished to write a flag,
 * and dispatches a two-worker batch when the trigger file is present.
 */
final class BatchTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(BatchTestWorkerA::class);
		JobsManager::registerWorker(BatchTestWorkerB::class);

		BatchFinished::listen(static function (BatchFinished $event) {
			$hasError = $event->has_error ? '1' : '0';
			\file_put_contents('__PLH_FINISHED_FLAG__', 'batch-finished:' . $hasError);
		});

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			BatchManager::create(
				[
					new BatchTestWorkerA('__PLH_FLAG_A__'),
					new BatchTestWorkerB('__PLH_FLAG_B__'),
				],
				Queue::DEFAULT,
				'test-batch',
			);
		});
	}
}
