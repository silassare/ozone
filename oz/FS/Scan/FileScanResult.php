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

namespace OZONE\Core\FS\Scan;

use OZONE\Core\FS\Enums\FileScanState;

/**
 * Class FileScanResult.
 *
 * The verdict of a {@see Interfaces\FileScannerInterface}.
 */
final class FileScanResult
{
	/**
	 * FileScanResult constructor.
	 *
	 * @param null|string $signature the name of the threat found, null when the content is clean
	 */
	private function __construct(public readonly ?string $signature) {}

	/**
	 * A clean content.
	 */
	public static function clean(): self
	{
		return new self(null);
	}

	/**
	 * An infected content.
	 *
	 * @param string $signature the name of the threat found
	 */
	public static function infected(string $signature): self
	{
		return new self($signature);
	}

	/**
	 * Checks if the content is infected.
	 */
	public function isInfected(): bool
	{
		return null !== $this->signature;
	}

	/**
	 * The file scan state this verdict leads to.
	 */
	public function state(): FileScanState
	{
		return $this->isInfected() ? FileScanState::INFECTED : FileScanState::CLEAN;
	}
}
