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
 * A cron task calling the `api` scope as nobody: first a route nobody may read, then one anybody may,
 * so what it writes shows the job went on after a refusal.
 */
final class AnonCallCronBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		CronCollect::listen(static function (): void {
			Cron::call(static function (JSONResult $result): void {
				$refused = context()->callAnonymously('anoncall:members', [], [], 'api');
				$served  = context()->callAnonymously('anoncall:service', [], [], 'api');

				\file_put_contents('__PLH_RESULT_FILE__', \json_encode([
					'refused_status' => $refused->getStatusCode(),
					'served_status'  => $served->getStatusCode(),
					'served_body'    => (string) $served->getBody(),
					'scope_after'    => scope()->getName(),
				]));

				$result->setDone();
			}, 'anon-call-task')->everyMinute();
		});
	}
}
