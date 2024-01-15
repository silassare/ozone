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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\JobStoreInterface;

/**
 * Class JobContract.
 */
class JobContract extends Job implements Interfaces\JobContractInterface
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
	public function getStore(): JobStoreInterface
	{
		return $this->store;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTrackingCode(): string
	{
		return \sprintf('%s:%s', $this->store->getName(), $this->getRef());
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromTrackingCode(string $tracking_code): JobContractInterface
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
	public function save(): self
	{
		$this->store->update($this);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function lock(): bool
	{
		return $this->store->lock($this);
	}

	/**
	 * {@inheritDoc}
	 */
	public function unlock(): bool
	{
		return $this->store->unlock($this);
	}
}
