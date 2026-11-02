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

use OZONE\Core\Cli\Process;
use OZONE\Core\Cli\Server\Interfaces\HostInterface;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class ProvisionPlan.
 *
 * An ordered list of {@see ProvisionStep}, which can be printed before anything runs and run once
 * the operator agrees. A step already recorded in the manifest, or already satisfied on the host,
 * is skipped rather than repeated.
 */
final class ProvisionPlan
{
	/** @var list<ProvisionStep> */
	private array $steps = [];

	/**
	 * Adds a step, unless it has nothing to run.
	 */
	public function add(ProvisionStep $step): self
	{
		if (!$step->isEmpty()) {
			$this->steps[] = $step;
		}

		return $this;
	}

	/**
	 * The steps, in order.
	 *
	 * @return list<ProvisionStep>
	 */
	public function steps(): array
	{
		return $this->steps;
	}

	/**
	 * Every command of the plan, in order, as it would run.
	 *
	 * @return list<string>
	 */
	public function commands(): array
	{
		$commands = [];

		foreach ($this->steps as $step) {
			foreach ($step->commands as $command) {
				$commands[] = $command;
			}
		}

		return $commands;
	}

	/**
	 * Whether the plan has any step.
	 */
	public function isEmpty(): bool
	{
		return empty($this->steps);
	}

	/**
	 * Runs the plan.
	 *
	 * A failing command stops the run: the rest of the plan usually depends on it, and a half
	 * applied step must not be recorded as done. What did succeed stays in the manifest, so the
	 * next run continues from there.
	 *
	 * A command killed for taking longer than its step allows is one of those failures, and says so
	 * rather than ending the run with a message about a process: which step was cut, and what it had
	 * printed, is what tells an operator whether to give it longer or go looking. A command killed by
	 * anything else says which signal, because "failed on" reads as the command's own verdict and a
	 * package install the host killed -- which is what a box short of memory does to one -- never
	 * reached a verdict at all.
	 *
	 * @param HostInterface $host    the host the plan is for
	 * @param null|callable $on_step called with (ProvisionStep, string $state) per step,
	 *                               state being `skipped`, `running` or `done`
	 *
	 * @return list<string> the names of the steps that ran
	 */
	public function run(
		HostInterface $host,
		ProvisionManifest $manifest,
		?callable $on_step = null
	): array {
		$ran = [];

		foreach ($this->steps as $step) {
			if ($manifest->has($step->name) || $step->isSatisfied($host)) {
				null !== $on_step && $on_step($step, 'skipped');

				continue;
			}

			null !== $on_step && $on_step($step, 'running');

			// The host's own bound, unless this step said what it is worth waiting for. The host is
			// left as it is: the next step gets the bound it asked for, or the host's again.
			$runner = null === $step->timeout ? $host : $host->withTimeout($step->timeout);

			foreach ($step->commands as $command) {
				$output = '';
				$code   = $runner->run($command, $output);

				if (0 !== $code) {
					// Killed, and not by its time running out: a package install on a small box is what
					// the kernel out-of-memory killer takes, and the host answers that as the code a shell
					// reports for it. Named, because "failed on" reads as the command's own verdict.
					$killed = Process::killedPhraseFor($code);

					throw new RuntimeException(\sprintf(
						'Provision step "%s" %s: %s',
						$step->name,
						match (true) {
							HostInterface::TIMED_OUT === $code => 'was still running when its time ran out',
							null !== $killed                   => 'was ' . $killed . ' on',
							default                            => 'failed on',
						},
						$command
					), [
						'_step'    => $step->name,
						'_command' => $command,
						'_code'    => $code,
						'_timeout' => $step->timeout,
						'_output'  => \substr($output, -2000),
					]);
				}
			}

			// Saved after every step, not once at the end: a run interrupted half way (a dropped
			// SSH session, a killed process) must still leave a record of what it did, or the next
			// run repeats it.
			$manifest->record($step, $step->commands)->save();

			$ran[] = $step->name;

			null !== $on_step && $on_step($step, 'done');
		}

		return $ran;
	}
}
