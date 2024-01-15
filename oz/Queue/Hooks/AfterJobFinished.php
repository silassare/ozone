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

namespace OZONE\Core\Queue\Hooks;

use OZONE\Core\Queue\Interfaces\JobContractInterface;
use PHPUtils\Events\Event;

/**
 * Class AfterJobFinished.
 *
 * This event is triggered when a job is finished.
 */
final class AfterJobFinished extends Event
{
	/**
	 * AfterJobFinished constructor.
	 *
	 * @param JobContractInterface $job_contract
	 */
	public function __construct(protected JobContractInterface $job_contract) {}

	/**
	 * AfterJobFinished destructor.
	 */
	public function __destruct()
	{
		unset($this->job_contract);
	}

	/**
	 * Returns the job contract.
	 *
	 * @return JobContractInterface
	 */
	public function getJobContract(): JobContractInterface
	{
		return $this->job_contract;
	}
}
