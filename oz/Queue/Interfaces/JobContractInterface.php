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
	 */
	public static function fromTrackingCode(string $tracking_code): static;

	/**
	 * Saves the job contract.
	 */
	public function save(): static;

	/**
	 * Says the job is still being worked on.
	 *
	 * A worker whose single run outlasts its store's lock lease calls this as it goes, or the job
	 * is taken for one a dead process left behind and settled while it is still running. Work that
	 * finishes well within the lease never needs it: every save says as much.
	 */
	public function touch(): static;

	/**
	 * Locks the job contract.
	 */
	public function lock(): bool;

	/**
	 * Unlocks the job contract.
	 */
	public function unlock(): bool;

	/**
	 * Checks whether the job contract is currently locked.
	 */
	public function isLocked(): bool;

	/**
	 * Cancels the job if it is PENDING and not locked.
	 *
	 * Returns true on success, false when the job is already running, finished,
	 * or held by another worker (locked).
	 */
	public function cancel(): bool;
}
