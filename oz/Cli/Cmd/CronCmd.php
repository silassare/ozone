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
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Utils\Utils;

/**
 * Class CronCmd.
 */
final class CronCmd extends Command
{
	/**
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage cron jobs.');

		if (Utils::isProjectLoaded()) {
			// action: run scheduled cron tasks
			$run = $this->action('run', 'Run scheduled cron tasks.');

			$run->handler(static function () {
				Cron::runDues();
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
