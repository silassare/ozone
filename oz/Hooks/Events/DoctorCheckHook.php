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

namespace OZONE\Core\Hooks\Events;

use InvalidArgumentException;
use OZONE\Core\Cli\Cmd\DoctorCmd;
use PHPUtils\Events\Event;

/**
 * Class DoctorCheckHook.
 *
 * Triggered by `oz doctor check` after its own project checks, so a plugin checks what only it
 * knows how to: each listener adds rows of the shape OZone's own have, reported and counted with
 * them, a failure failing the run as OZone's do.
 */
final class DoctorCheckHook extends Event
{
	/** @var list<array{name: string, status: string, detail: string, fix: string}> */
	private array $rows = [];

	/**
	 * Adds a check.
	 *
	 * @param string $status {@see DoctorCmd::OK}, {@see DoctorCmd::WARN} or {@see DoctorCmd::FAIL}
	 * @param string $fix    what to do about it, empty when nothing is to be done
	 *
	 * @throws InvalidArgumentException when the status is none of the three
	 */
	public function add(string $name, string $status, string $detail, string $fix = ''): static
	{
		if (!\in_array($status, [DoctorCmd::OK, DoctorCmd::WARN, DoctorCmd::FAIL], true)) {
			throw new InvalidArgumentException(\sprintf(
				'A doctor check is "%s", "%s" or "%s", not "%s".',
				DoctorCmd::OK,
				DoctorCmd::WARN,
				DoctorCmd::FAIL,
				$status
			));
		}

		$this->rows[] = ['name' => $name, 'status' => $status, 'detail' => $detail, 'fix' => $fix];

		return $this;
	}

	/**
	 * The checks added, in the order they were.
	 *
	 * @return list<array{name: string, status: string, detail: string, fix: string}>
	 */
	public function getRows(): array
	{
		return $this->rows;
	}
}
