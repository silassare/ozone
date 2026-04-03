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

namespace OZONE\Core\Queue\Stores;

use Generator;
use InvalidArgumentException;
use Override;
use OZONE\Core\App\Keys;
use OZONE\Core\Crypt\DoCrypt;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;
use OZONE\Core\Queue\JobContract;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Utils\JSONResult;
use OZONE\Core\Utils\RedisFactory;
use Redis as PhpRedis;

/**
 * Class RedisJobStore.
 *
 * Redis-backed job store using the ext-redis PHP extension.
 *
 * Key structure (all keys are relative to the global `OZ_REDIS_PREFIX`):
 *
 * | Key pattern                  | Type   | Description                          |
 * | ---------------------------- | ------ | ------------------------------------ |
 * | `oz:jobs:job:{ref}`          | HASH   | All fields of a single job           |
 * | `oz:jobs:queue:{queue_name}` | ZSET   | Refs in a queue, scored by created_at|
 * | `oz:jobs:all`                | ZSET   | All refs across every queue          |
 * | `oz:jobs:lock:{ref}`         | STRING | Atomic lock (SET NX EX)              |
 *
 * Filtering by worker, state, and priority is done in PHP after loading the
 * hash data, which is acceptable for the typical small-to-medium queue sizes
 * found in cron-style workloads.
 *
 * Locking uses Redis atomic `SET ... NX EX` so only one concurrent worker can
 * claim a given job at a time.
 */
class RedisJobStore implements JobStoreInterface
{
	public const NAME = 'redis';

	/**
	 * Seconds before an acquired lock expires automatically.
	 * Prevents jobs from staying locked forever if the worker process crashes.
	 */
	private const LOCK_TTL = 7200;

	private const KEY_PREFIX = 'oz:jobs:';

	// Hash field names
	private const F_REF        = 'ref';
	private const F_NAME       = 'name';
	private const F_QUEUE      = 'queue';
	private const F_WORKER     = 'worker';
	private const F_STATE      = 'state';
	private const F_PRIORITY   = 'priority';
	private const F_PAYLOAD    = 'payload';
	private const F_RESULT     = 'result';
	private const F_TRY_COUNT  = 'try_count';
	private const F_RETRY_MAX  = 'retry_max';
	private const F_RETRY_DEL  = 'retry_delay';
	private const F_STARTED_AT = 'started_at';
	private const F_ENDED_AT   = 'ended_at';
	private const F_CREATED_AT = 'created_at';
	private const F_UPDATED_AT = 'updated_at';
	private const F_RUN_AFTER  = 'run_after';
	private const F_CHAIN      = 'chain';
	private const F_BATCH_ID   = 'batch_id';

	private ?PhpRedis $redis = null;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $ref): ?JobContractInterface
	{
		$data = $this->redis()->hGetAll($this->jobKey($ref));

		if (empty($data)) {
			return null;
		}

		return $this->fromData($data);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getOrFail(string $ref): JobContractInterface
	{
		$job = $this->get($ref);

		if (!$job) {
			throw new InvalidArgumentException(\sprintf(
				'Job with ref "%s" not found in store "%s".',
				$ref,
				self::NAME
			));
		}

		return $job;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function add(JobInterface $job): JobContractInterface
	{
		$data       = $this->toData($job);
		$ref        = $job->getRef();
		$created_at = $job->getCreatedAt();

		$this->redis()->hMset($this->jobKey($ref), $data);
		$this->redis()->zAdd($this->queueKey($job->getQueue()), $created_at, $ref);
		$this->redis()->zAdd($this->allKey(), $created_at, $ref);

		return $this->fromData($data);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function update(JobContractInterface $job_contract): static
	{
		if ($this->redis()->exists($this->jobKey($job_contract->getRef()))) {
			$this->redis()->hMset($this->jobKey($job_contract->getRef()), $this->toData($job_contract));
		} else {
			$this->add($job_contract);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function delete(JobContractInterface $job_contract): static
	{
		$ref = $job_contract->getRef();

		$this->redis()->del($this->jobKey($ref));
		$this->redis()->zRem($this->queueKey($job_contract->getQueue()), $ref);
		$this->redis()->zRem($this->allKey(), $ref);
		$this->redis()->del($this->lockKey($ref));

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Iterates all jobs in the given queue, applying worker / state / priority
	 * filters in PHP. Jobs are yielded in FIFO order (oldest created_at first).
	 *
	 * @return Generator<JobContractInterface>
	 */
	#[Override]
	public function iterator(
		string $queue_name,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null
	): Generator {
		$refs = $this->redis()->zRange($this->queueKey($queue_name), 0, -1);
		$now  = \time();

		foreach ($refs as $ref) {
			$data = $this->redis()->hGetAll($this->jobKey((string) $ref));

			if (empty($data)) {
				continue;
			}

			if (null !== $worker_name && ($data[self::F_WORKER] ?? '') !== $worker_name) {
				continue;
			}

			if (null !== $state && (int) ($data[self::F_STATE] ?? -1) !== $state->value) {
				continue;
			}

			if (null !== $priority && (int) ($data[self::F_PRIORITY] ?? 0) !== $priority) {
				continue;
			}

			// Skip jobs whose run_after window has not arrived yet.
			$run_after_raw = $data[self::F_RUN_AFTER] ?? '';

			if ('' !== $run_after_raw && (int) $run_after_raw > $now) {
				continue;
			}

			yield $this->fromData($data);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function list(
		?string $queue_name = null,
		?string $worker_name = null,
		?JobState $state = null,
		?int $priority = null,
		int $page = 1,
		int $max = 100
	): array {
		$index_key = null !== $queue_name ? $this->queueKey($queue_name) : $this->allKey();
		$refs      = $this->redis()->zRange($index_key, 0, -1);
		$results   = [];
		$skip      = ($page - 1) * $max;
		$seen      = 0;

		foreach ($refs as $ref) {
			if (\count($results) >= $max) {
				break;
			}

			$data = $this->redis()->hGetAll($this->jobKey((string) $ref));

			if (empty($data)) {
				continue;
			}

			if (null !== $worker_name && ($data[self::F_WORKER] ?? '') !== $worker_name) {
				continue;
			}

			if (null !== $state && (int) ($data[self::F_STATE] ?? -1) !== $state->value) {
				continue;
			}

			if (null !== $priority && (int) ($data[self::F_PRIORITY] ?? 0) !== $priority) {
				continue;
			}

			if ($seen < $skip) {
				++$seen;

				continue;
			}

			$results[] = $this->fromData($data);
			++$seen;
		}

		return $results;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Uses an atomic `SET ... NX EX` so only the first concurrent caller
	 * acquires the lock; subsequent callers receive false.
	 */
	#[Override]
	public function lock(JobContractInterface $job_contract): bool
	{
		return $this->redis()->set(
			$this->lockKey($job_contract->getRef()),
			'1',
			['EX' => self::LOCK_TTL, 'NX']
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function unlock(JobContractInterface $job_contract): bool
	{
		return (bool) $this->redis()->del($this->lockKey($job_contract->getRef()));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isLocked(JobContractInterface $job_contract): bool
	{
		return (bool) $this->redis()->exists($this->lockKey($job_contract->getRef()));
	}

	/**
	 * {@inheritDoc}
	 *
	 * When no state is given, returns the ZSET cardinality (O(1) ZCARD).
	 * When a state is given, iterates the refs and reads hash fields (O(n)),
	 * which is acceptable for the small queue sizes typical of cron workloads.
	 */
	#[Override]
	public function count(string $queue_name, ?JobState $state = null): int
	{
		if (null === $state) {
			// zCard returns int >= 0 for existing sorted sets, false when the
			// key does not exist (empty queue). Both false and 0 map to 0 here.
			/** @psalm-suppress RedundantCast */
			return (int) ($this->redis()->zCard($this->queueKey($queue_name)) ?: 0);
		}

		$refs  = $this->redis()->zRange($this->queueKey($queue_name), 0, -1);
		$count = 0;

		foreach ($refs as $ref) {
			$data = $this->redis()->hGetAll($this->jobKey((string) $ref));

			if (!empty($data) && (int) ($data[self::F_STATE] ?? -1) === $state->value) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Iterates the all-jobs set (or a specific queue set) by score range to find
	 * jobs older than the cutoff, then checks each against terminal state(s).
	 * Only terminal-state jobs are removed.
	 */
	#[Override]
	public function prune(int $older_than_seconds, ?JobState $state = null, ?string $queue_name = null): int
	{
		$terminal        = [JobState::DONE, JobState::FAILED, JobState::DEAD_LETTER, JobState::CANCELLED];
		$terminal_values = \array_map(static fn (JobState $s) => $s->value, $terminal);
		$cutoff          = \time() - $older_than_seconds;

		if (null !== $state) {
			if (!\in_array($state, $terminal, true)) {
				return 0; // refuse to prune non-terminal states
			}

			$terminal_values = [$state->value];
		}

		$index_key = null !== $queue_name ? $this->queueKey($queue_name) : $this->allKey();
		$refs      = $this->redis()->zRangeByScore($index_key, '-inf', (string) $cutoff);
		$count     = 0;

		foreach ($refs as $ref) {
			$data = $this->redis()->hGetAll($this->jobKey((string) $ref));

			if (empty($data)) {
				continue;
			}

			$job_state = (int) ($data[self::F_STATE] ?? -1);

			if (!\in_array($job_state, $terminal_values, true)) {
				continue;
			}

			$queue = (string) ($data[self::F_QUEUE] ?? '');

			$this->redis()->del($this->jobKey((string) $ref));
			$this->redis()->zRem($this->queueKey($queue), $ref);
			$this->redis()->zRem($this->allKey(), $ref);
			$this->redis()->del($this->lockKey((string) $ref));

			++$count;
		}

		return $count;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Scans the all-jobs set and filters in PHP by `batch_id` (and optionally state).
	 * Acceptable because batch sizes are typically small.
	 */
	#[Override]
	public function countByBatch(string $batch_id, ?JobState $state = null): int
	{
		$refs  = $this->redis()->zRange($this->allKey(), 0, -1);
		$count = 0;

		foreach ($refs as $ref) {
			$data = $this->redis()->hGetAll($this->jobKey((string) $ref));

			if (empty($data) || ($data[self::F_BATCH_ID] ?? '') !== $batch_id) {
				continue;
			}

			if (null !== $state && (int) ($data[self::F_STATE] ?? -1) !== $state->value) {
				continue;
			}

			++$count;
		}

		return $count;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Scans the all-jobs set and returns all jobs whose `batch_id` matches.
	 */
	#[Override]
	public function listByBatch(string $batch_id): array
	{
		$refs    = $this->redis()->zRange($this->allKey(), 0, -1);
		$results = [];

		foreach ($refs as $ref) {
			$data = $this->redis()->hGetAll($this->jobKey((string) $ref));

			if (empty($data) || ($data[self::F_BATCH_ID] ?? '') !== $batch_id) {
				continue;
			}

			$results[] = $this->fromData($data);
		}

		return $results;
	}

	/**
	 * Returns the shared Redis connection, connecting lazily on first call.
	 *
	 * @return PhpRedis
	 */
	private function redis(): PhpRedis
	{
		return $this->redis ??= RedisFactory::get();
	}

	/**
	 * Redis key for the job HASH.
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	private function jobKey(string $ref): string
	{
		return self::KEY_PREFIX . 'job:' . $ref;
	}

	/**
	 * Redis key for the queue membership sorted set.
	 *
	 * @param string $queue
	 *
	 * @return string
	 */
	private function queueKey(string $queue): string
	{
		return self::KEY_PREFIX . 'queue:' . $queue;
	}

	/**
	 * Redis key for the all-queues sorted set.
	 *
	 * @return string
	 */
	private function allKey(): string
	{
		return self::KEY_PREFIX . 'all';
	}

	/**
	 * Redis key for the job lock.
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	private function lockKey(string $ref): string
	{
		return self::KEY_PREFIX . 'lock:' . $ref;
	}

	/**
	 * Serializes a {@link JobInterface} to the flat string map stored in a Redis HASH.
	 *
	 * @param JobInterface $job
	 *
	 * @return array<string, string>
	 */
	private function toData(JobInterface $job): array
	{
		$run_after = $job->getRunAfter();

		// Encrypt payload at rest when requested.
		if ($job->shouldEncryptPayload()) {
			$secret  = Keys::secret();
			$cipher  = (new DoCrypt())->encrypt(\json_encode($job->getPayload()), $secret);
			$payload = \json_encode(['$enc' => $cipher]) ?: '{}';
		} else {
			$payload = \json_encode($job->getPayload()) ?: '{}';
		}

		return [
			self::F_REF        => $job->getRef(),
			self::F_NAME       => $job->getName(),
			self::F_QUEUE      => $job->getQueue(),
			self::F_WORKER     => $job->getWorker(),
			self::F_STATE      => (string) $job->getState()->value,
			self::F_PRIORITY   => (string) $job->getPriority(),
			self::F_PAYLOAD    => $payload,
			self::F_RESULT     => \json_encode($job->getResult()->toArray()) ?: '{}',
			self::F_TRY_COUNT  => (string) $job->getTryCount(),
			self::F_RETRY_MAX  => (string) $job->getRetryMax(),
			self::F_RETRY_DEL  => (string) $job->getRetryDelay(),
			self::F_STARTED_AT => (string) ($job->getStartedAt() ?? ''),
			self::F_ENDED_AT   => (string) ($job->getEndedAt() ?? ''),
			self::F_CREATED_AT => (string) $job->getCreatedAt(),
			self::F_UPDATED_AT => (string) $job->getUpdatedAt(),
			self::F_RUN_AFTER  => null !== $run_after ? (string) $run_after : '',
			self::F_CHAIN      => \json_encode($job->getChain()) ?: '[]',
			self::F_BATCH_ID   => $job->getBatchId() ?? '',
		];
	}

	/**
	 * Deserializes a Redis HGETALL result array into a {@link JobContractInterface}.
	 *
	 * @param array<string, string> $data
	 *
	 * @return JobContractInterface
	 */
	private function fromData(array $data): JobContractInterface
	{
		$ref         = (string) ($data[self::F_REF] ?? '');
		$worker      = (string) ($data[self::F_WORKER] ?? '');
		$raw_payload = (array) (\json_decode($data[self::F_PAYLOAD] ?? '{}', true) ?? []);
		$result      = JSONResult::revive(\json_decode($data[self::F_RESULT] ?? '{}', true) ?? []);

		$is_encrypted = false;

		// Detect encrypted payload: sentinel key '$enc' wraps the ciphertext.
		if (isset($raw_payload['$enc']) && \is_string($raw_payload['$enc'])) {
			$decrypted = (new DoCrypt())->decrypt($raw_payload['$enc'], Keys::secret());

			if (false !== $decrypted) {
				$raw_payload  = (array) \json_decode($decrypted, true);
				$is_encrypted = true;
			}
		}

		$started_raw = $data[self::F_STARTED_AT] ?? '';
		$ended_raw   = $data[self::F_ENDED_AT] ?? '';

		$job = new JobContract($ref, $worker, $raw_payload, $this);

		$run_after_raw = $data[self::F_RUN_AFTER] ?? '';
		$chain_raw     = $data[self::F_CHAIN] ?? '[]';
		$batch_id_raw  = $data[self::F_BATCH_ID] ?? '';

		$job->setName((string) ($data[self::F_NAME] ?? ''))
			->setQueue((string) ($data[self::F_QUEUE] ?? ''))
			->setState(JobState::from((int) ($data[self::F_STATE] ?? 0)))
			->setPriority((int) ($data[self::F_PRIORITY] ?? 0))
			->setTryCount((int) ($data[self::F_TRY_COUNT] ?? 0))
			->setRetryMax((int) ($data[self::F_RETRY_MAX] ?? 3))
			->setRetryDelay((int) ($data[self::F_RETRY_DEL] ?? 60))
			->setResult($result)
			->setStartedAt('' !== $started_raw ? (float) $started_raw : null)
			->setEndedAt('' !== $ended_raw ? (float) $ended_raw : null)
			->setCreatedAt((int) ($data[self::F_CREATED_AT] ?? 0))
			->setUpdatedAt((int) ($data[self::F_UPDATED_AT] ?? 0))
			->setRunAfter('' !== $run_after_raw ? (int) $run_after_raw : null)
			->setChain((array) (\json_decode($chain_raw, true) ?? []))
			->setBatchId('' !== $batch_id_raw ? $batch_id_raw : null);

		// Preserve the encryption flag so subsequent saves re-encrypt the payload.
		if ($is_encrypted) {
			$job->encrypted();
		}

		return $job;
	}
}
