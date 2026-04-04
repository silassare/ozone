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

use __PLH_NAMESPACE__\Workers\ChainTestWorkerA;
use __PLH_NAMESPACE__\Workers\ChainTestWorkerB;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for JobChainTest.
 *
 * Registers both chain workers and dispatches WorkerA with WorkerB in its
 * chain when the trigger file is present.
 */
final class ChainTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(ChainTestWorkerA::class);
		JobsManager::registerWorker(ChainTestWorkerB::class);

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			$chain = [
				[
					'worker'  => ChainTestWorkerB::getName(),
					'payload' => (new ChainTestWorkerB('__PLH_FLAG_B__'))->getPayload(),
					'queue'   => Queue::DEFAULT,
				],
			];

			Queue::get(Queue::DEFAULT)
				->push(new ChainTestWorkerA('__PLH_FLAG_A__'))
				->setChain($chain)
				->dispatch();
		});
	}
}
