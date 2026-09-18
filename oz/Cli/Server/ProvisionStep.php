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

namespace OZONE\Core\Cli\Server;

use Closure;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;

/**
 * Class ProvisionStep.
 *
 * One thing a provision run does, as a name, a reason and the exact shell commands. A step knows
 * nothing about how it runs: that is {@see ProvisionPlan}, which is what makes a plan printable and
 * assertable without touching the host.
 */
final class ProvisionStep
{
	/**
	 * @param string       $name      a short identifier, recorded in the manifest
	 * @param string       $reason    why the step is in the plan, shown to the operator
	 * @param list<string> $commands  the shell commands, in order
	 * @param null|Closure $satisfied `fn (HostInterface $host): bool`, true when the host already has this;
	 *                                null when unknown
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $reason,
		public readonly array $commands,
		private readonly ?Closure $satisfied = null,
	) {}

	/**
	 * Whether the host already satisfies this step, so a run can skip it.
	 *
	 * A step with no check is never skipped: its commands have to be idempotent instead.
	 */
	public function isSatisfied(HostInterface $host): bool
	{
		return null !== $this->satisfied && true === ($this->satisfied)($host);
	}

	/**
	 * Whether the step has anything to run.
	 */
	public function isEmpty(): bool
	{
		return empty($this->commands);
	}
}
