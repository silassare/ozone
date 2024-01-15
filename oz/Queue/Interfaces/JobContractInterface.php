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

namespace OZONE\Core\Queue\Interfaces;

/**
 * Interface JobContractInterface.
 */
interface JobContractInterface extends JobInterface
{
	/**
	 * Returns the job store.
	 */
	public function getStore(): JobStoreInterface;

	/**
	 * Returns the contract tracking code.
	 */
	public function getTrackingCode(): string;

	/**
	 * Gets the job from a tracking code.
	 *
	 * @param string $tracking_code
	 *
	 * @return JobContractInterface
	 */
	public static function fromTrackingCode(string $tracking_code): self;

	/**
	 * Saves the job contract.
	 *
	 * @return $this
	 */
	public function save(): self;

	/**
	 * Locks the job contract.
	 *
	 * @return bool
	 */
	public function lock(): bool;

	/**
	 * Unlocks the job contract.
	 *
	 * @return bool
	 */
	public function unlock(): bool;
}
