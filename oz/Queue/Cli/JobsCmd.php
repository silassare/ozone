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
use Kli\Types\KliTypeNumber;
use Kli\Types\KliTypeString;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
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

			// ---- prune -------------------------------------------------------
			$jobs_prune = $this->action('prune', 'Delete old terminal-state jobs.');

			$jobs_prune->option('store', 's')
				->description('Restrict pruning to this store. Defaults to all stores.')
				->type($store_type);

			$jobs_prune->option('queue', 'q')
				->description('Restrict pruning to this queue.')
				->type($queue_type);

			$jobs_prune->option('state', 't')
				->description('Restrict pruning to jobs in this state (done|failed|dead_letter|cancelled).')
				->string();

			$jobs_prune->option('older-than', 'o')
				->description('Only delete jobs older than this many seconds (default: 86400 = 24 h).')
				->type((new KliTypeNumber())->min(0)->def(86400));

			$jobs_prune->handler(self::prune(...));

			// ---- dead-letter -------------------------------------------------
			$jobs_dl = $this->action('dead-letter', 'Manage dead-letter jobs.');

			$jobs_dl->option('store', 's')
				->description('Restrict to this store.')
				->type($store_type);

			$jobs_dl->option('queue', 'q')
				->description('Restrict to this queue.')
				->type($queue_type);

			$jobs_dl->option('action', 'a')
				->description('What to do: list, retry, delete.')
				->string()
				->def('list');

			$jobs_dl->handler(self::deadLetter(...));
		}
	}

	private static function finish(KliArgs $args): void
	{
		$store = $args->get('store');
		$ref   = $args->get('ref');

		JobsManager::finish(JobsManager::getStore($store)
			->getOrFail($ref));
	}

	private static function prune(KliArgs $args): void
	{
		$store_name  = $args->get('store');
		$queue_name  = $args->get('queue');
		$state_raw   = $args->get('state');
		$older_than  = (int) $args->get('older-than');

		$state = null;

		if (null !== $state_raw) {
			$state = match ($state_raw) {
				'done'        => JobState::DONE,
				'failed'      => JobState::FAILED,
				'dead_letter' => JobState::DEAD_LETTER,
				'cancelled'   => JobState::CANCELLED,
				default       => throw new KliInputException(
					\sprintf('Unknown state "%s". Use: done, failed, dead_letter, cancelled.', $state_raw)
				),
			};
		}

		$total = 0;

		foreach (JobsManager::getStores() as $store) {
			if (null !== $store_name && $store->getName() !== $store_name) {
				continue;
			}

			$total += $store->prune($older_than, $state, $queue_name);
		}

		echo \sprintf("Pruned %d job(s).\n", $total);
	}

	private static function deadLetter(KliArgs $args): void
	{
		$store_name = $args->get('store');
		$queue_name = $args->get('queue');
		$action     = (string) ($args->get('action') ?? 'list');

		$count = 0;

		foreach (JobsManager::getStores() as $store) {
			if (null !== $store_name && $store->getName() !== $store_name) {
				continue;
			}

			$jobs = $store->list($queue_name, null, JobState::DEAD_LETTER);

			foreach ($jobs as $job) {
				++$count;

				if ('list' === $action) {
					echo \sprintf(
						"[%s] %s worker=%s queue=%s tries=%d/%d\n",
						$job->getRef(),
						$job->getName(),
						$job->getWorker(),
						$job->getQueue(),
						$job->getTryCount(),
						$job->getRetryMax()
					);

					continue;
				}

				if ('retry' === $action) {
					$job->setState(JobState::PENDING)
						->setRunAfter(null)
						->setTryCount(0);
					$store->update($job);

					continue;
				}

				if ('delete' === $action) {
					$store->delete($job);

					continue;
				}

				throw new KliInputException(
					\sprintf('Unknown action "%s". Use: list, retry, delete.', $action)
				);
			}
		}

		if ('list' !== $action) {
			echo \sprintf("Dead-letter %s: %d job(s) affected.\n", $action, $count);
		} elseif (0 === $count) {
			echo "No dead-letter jobs found.\n";
		}
	}

	private static function run(KliArgs $args): void
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
