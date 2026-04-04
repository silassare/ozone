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
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * Registers a callable cron task via CronCollect so the integration test
 * can verify the full dispatch + process pipeline.
 */
final class TestCronBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		CronCollect::listen(static function () {
			Cron::call(static function (JSONResult $result) {
				\file_put_contents('__PLH_FLAG_FILE__', 'cron-ok');
				$result->setDone()->setData(['flag' => '__PLH_FLAG_FILE__']);
			}, 'test-cron-flag-task')->everyMinute();
		});
	}
}
