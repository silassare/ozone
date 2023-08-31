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

namespace OZONE\Core\Queue\Cli;

use Kli\KliArgs;
use Kli\Types\KliTypeString;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\Queue;

/**
 * Class JobsCmd.
 */
final class JobsCmd extends Command
{
	/**
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage job queue.');

		if (Utils::isProjectLoaded()) {
			$store_type = new KliTypeString();
			$store_type->pattern('~^(' . \implode('|', \array_keys(JobsManager::getStores())) . ')$~');

			$queue_type = new KliTypeString();
			$queue_type->pattern('~^(' . \implode('|', \array_keys(Queue::getQueues())) . ')$~')
				->def(Queue::DEFAULT);

			// this is responsible for running jobs added to the queue
			$jobs_run = $this->action('run', 'Run jobs.');

			$jobs_run->option('store', 's')
				->description('The job store to use.')
				->type($store_type);

			$jobs_run->option('queue', 'q')
				->description('The queue name.')
				->required()
				->type($queue_type);

			$jobs_run->option('worker', 'w')
				->description('The worker name.')
				->string();

			$jobs_run->option('priority', 'p')
				->description('The priority.')
				->number();

			$jobs_run->option('max-jobs', 'm')
				->description('The maximum number of jobs to run.')
				->number();

			$jobs_run->handler(static function (KliArgs $args) {
				$store    = $args->get('store');
				$worker   = $args->get('worker');
				$queue    = $args->get('driver');
				$priority = $args->get('priority');
				$max_jobs = $args->get('max-jobs');

				JobsManager::run($store, $queue, $worker, $priority, $max_jobs);
			});

			$jobs_finish = $this->action('finish', 'Finish jobs.');

			$jobs_finish->option('store', 's')
				->description('The job store were the job is.')
				->required()
				->type($store_type);

			$jobs_finish->option('ref', 'r')
				->description('The job ref.')
				->required()
				->string();

			$jobs_finish->handler(static function (KliArgs $args) {
				$store = $args->get('store');
				$ref   = $args->get('ref');

				JobsManager::finish(JobsManager::getStore($store)
					->getOrFail($ref));
			});
		}
	}
}
