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

use Kli\Exceptions\KliException;
use Kli\Exceptions\KliInputException;
use Kli\KliArgs;
use Kli\Types\KliTypeString;
use Override;
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
	 * @throws KliException
	 */
	#[Override]
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

			$jobs_run->option('job', 'j')
				->description('Run a specific job by ref.')
				->string();

			$jobs_run->option('force', 'f')
				->description('Run the job even if it is locked (also used for async hand-off).')
				->bool();

			$jobs_run->handler(self::run(...));

			$jobs_finish = $this->action('finish', 'Finish jobs.');

			$jobs_finish->option('store', 's')
				->description('The job store were the job is.')
				->required()
				->type($store_type);

			$jobs_finish->option('ref', 'r')
				->description('The job ref.')
				->required()
				->string();

			$jobs_finish->handler(self::finish(...));
		}
	}

	private static function finish(KliArgs $args)
	{
		$store = $args->get('store');
		$ref   = $args->get('ref');

		JobsManager::finish(JobsManager::getStore($store)
			->getOrFail($ref));
	}

	/**
	 * @throws KliException
	 */
	private static function run(KliArgs $args)
	{
		$store   = $args->get('store');
		$job_ref = $args->get('job');
		$force   = (bool) $args->get('force');

		if (null !== $job_ref) {
			$job = JobsManager::getStore($store)->getOrFail($job_ref);

			if ($force) {
				if ($job->isLocked()) {
					// Job is already locked (async hand-off or forced takeover):
					// skip lock acquisition and run directly.
					JobsManager::forceRunJob($job);
				} else {
					// Not locked yet: acquire lock normally then run.
					JobsManager::runJob($job);
				}
			} else {
				// Normal single-job mode: fail if locked.
				if (!JobsManager::runJob($job)) {
					throw new KliInputException(
						\sprintf('Job "%s" is locked. Use --force to run it anyway.', $job_ref)
					);
				}
			}

			return;
		}

		$worker   = $args->get('worker');
		$queue    = $args->get('queue');
		$priority = $args->get('priority');
		$max_jobs = $args->get('max-jobs');

		JobsManager::run($store, $queue, $worker, $priority, $max_jobs);
	}
}
