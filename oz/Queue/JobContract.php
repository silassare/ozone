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

namespace OZONE\Core\Queue;

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;

/**
 * Class JobContract.
 *
 * A {@link Job} bound to a {@link JobStoreInterface}.
 *
 * Extends `Job` with persistence operations: {@link save()} flushes the current state back
 * to the store, {@link lock()} / {@link unlock()} prevent concurrent workers from picking
 * up the same job, and {@link getTrackingCode()} returns a `store:ref` string that can later
 * be resolved back to this contract via {@link fromTrackingCode()}.
 *
 * Instances are created exclusively by {@link JobStoreInterface::add()} and should not be
 * instantiated directly by application code.
 */
class JobContract extends Job implements JobContractInterface
{
	/**
	 * JobContract constructor.
	 *
	 * @param string            $ref
	 * @param string            $worker
	 * @param array             $payload
	 * @param JobStoreInterface $store
	 */
	public function __construct(string $ref, string $worker, array $payload, protected readonly JobStoreInterface $store)
	{
		parent::__construct($ref, $worker, $payload);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStore(): JobStoreInterface
	{
		return $this->store;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getTrackingCode(): string
	{
		return \sprintf('%s:%s', $this->store->getName(), $this->getRef());
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromTrackingCode(string $tracking_code): static
	{
		[$store_name, $ref] = \explode(':', $tracking_code, 2);

		if (!$ref || !$store_name) {
			throw new RuntimeException(\sprintf('Invalid job tracking code: %s', $tracking_code));
		}

		return JobsManager::getStore($store_name)
			->getOrFail($ref);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function save(): static
	{
		$this->store->update($this);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function lock(): bool
	{
		return $this->store->lock($this);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function unlock(): bool
	{
		return $this->store->unlock($this);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isLocked(): bool
	{
		return $this->store->isLocked($this);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function cancel(): bool
	{
		return JobsManager::cancel($this);
	}
}
