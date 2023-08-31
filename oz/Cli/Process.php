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

namespace OZONE\Core\Cli;

/**
 * Class Process.
 */
final class Process extends \Symfony\Component\Process\Process
{
	/**
	 * Process constructor.
	 *
	 * {@inheritDoc}
	 */
	public function __construct(
		array $command,
		string $cwd = null,
		array $env = null,
		mixed $input = null,
		?float $timeout = 0 // 0 means no timeout limit
	) {
		parent::__construct($command, $cwd, $env, $input, $timeout);
	}
}
