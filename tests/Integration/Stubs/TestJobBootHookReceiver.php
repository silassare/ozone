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

use __PLH_NAMESPACE__\Workers\TestJobWorker;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Registers TestJobWorker and dispatches one test job per bootstrap via InitHook.
 */
final class TestJobBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(TestJobWorker::class);

		InitHook::listen(static function () {
			// Flag file path is resolved relative to the project root at runtime.
			$flagFile = \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'job_ran.flag';
			// Remove any previous run's flag so the test sees a fresh result.
			if (\is_file($flagFile)) {
				\unlink($flagFile);
			}
			Queue::get(Queue::DEFAULT)->push(new TestJobWorker($flagFile))->dispatch();
		});
	}
}
