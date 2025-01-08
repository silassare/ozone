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
 * Class WorkerInterface.
 */
interface WorkerInterface
{
	/**
	 * Gets the worker name.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Instructs the worker to do the job.
	 *
	 * @param JobContractInterface $job_contract
	 *
	 * @return $this
	 */
	public function work(JobContractInterface $job_contract): self;

	/**
	 * Is the worker working asynchronously?
	 *
	 * @return bool
	 */
	public function isAsync(): bool;

	/**
	 * Gets the result.
	 *
	 * @return array
	 */
	public function getResult(): array;

	/**
	 * Gets the worker instance from payload.
	 *
	 * @param array $payload
	 *
	 * @return static
	 */
	public static function fromPayload(array $payload): self;

	/**
	 * Returns the payload.
	 *
	 * The payload is the data that will be passed to the worker later.
	 *
	 * @return array
	 */
	public function getPayload(): array;
}
