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

namespace OZONE\Core\Columns;

use InvalidArgumentException;

/**
 * Class ValidatedFile.
 */
final class ValidatedFile
{
	public function __construct(private readonly string $value, private readonly bool $is_path)
	{
		if (!$this->is_path && !\is_numeric($value)) {
			throw new InvalidArgumentException("Invalid File ID provided: {$value}");
		}
	}

	public function __toString(): string
	{
		return $this->value;
	}

	public function isPath(): bool
	{
		return $this->is_path;
	}

	public function isFileID(): bool
	{
		return !$this->is_path;
	}
}
