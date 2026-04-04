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

use __PLH_NAMESPACE__\Workers\EncryptTestWorker;
use OZONE\Core\Db\OZJobsQuery;
use OZONE\Core\Hooks\Events\InitHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Boot hook receiver for EncryptedJobTest.
 *
 * When the trigger file is present:
 *   1. Dispatches one encrypted job.
 *   2. Reads back the raw OZJob entity (payload still encrypted at this point)
 *      and writes its payload JSON to raw_payload.json so the test can
 *      assert the "$enc" sentinel without touching the DB directly.
 */
final class EncryptTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		JobsManager::registerWorker(EncryptTestWorker::class);

		InitHook::listen(static function () {
			$triggerFile = '__PLH_TRIGGER_FILE__';

			if (!\is_file($triggerFile)) {
				return;
			}

			\unlink($triggerFile);

			$contract = Queue::get(Queue::DEFAULT)
				->push(new EncryptTestWorker('__PLH_FLAG_FILE__'))
				->encrypted()
				->dispatch();

			// Read back the raw entity -- payload is still {"$enc":"..."} here because
			// OZJobsQuery does NOT decrypt; only DbJobStore::fromEntity() does.
			$entity = (new OZJobsQuery())
				->whereRefIs($contract->getRef())
				->find(1)
				->fetchClass();

			if (null !== $entity) {
				\file_put_contents(
					'__PLH_RAW_PAYLOAD_FILE__',
					\json_encode($entity->getPayload()->getData())
				);
			}
		});
	}
}
