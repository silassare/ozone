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

namespace OZONE\Core\Cli\Cmd;

use Kli\KliArgs;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Class CronCmd.
 */
final class CronCmd extends Command
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage cron tasks.');

		if (Utils::isProjectLoaded()) {
			// action: run scheduled cron tasks
			$run = $this->action('run', 'Run scheduled cron tasks.');

			$run->handler(static function () {
				// process due cron tasks, which will push them into the cron:sync and cron:async queues;
				// this is done first to ensure that any tasks due at the time of invocation are included
				// in the current run, even if they were due before the JobsManager::run() calls below.
				Cron::runDues();
				// Process cron:sync tasks in-process; cron:async tasks will spawn subprocesses.
				JobsManager::run(null, Queue::CRON_SYNC);
				JobsManager::run(null, Queue::CRON_ASYNC);
			});

			// action: start a specific cron task
			$start = $this->action('start', 'Start a specific cron task.');
			$start->option('name', 'n')
				->description('The name of the task to start.')
				->required()
				->string();

			$start->handler(static function (KliArgs $args) {
				Cron::start($args->get('name'));
			});
		}
	}
}
