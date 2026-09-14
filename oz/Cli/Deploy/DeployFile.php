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

namespace OZONE\Core\Cli\Deploy;

/**
 * Class DeployFile.
 *
 * One file `oz deploy init` writes, as a destination and its content.
 *
 * Content, not a path to copy from: a plan can then be asserted in full without a filesystem, which
 * is what makes the generated nginx and systemd files testable rather than hoped-for.
 */
final class DeployFile
{
	/**
	 * @param string $path       where to write, relative to the project
	 * @param string $content    the file content
	 * @param bool   $executable whether it needs the executable bit
	 * @param string $reason     what the file is for, shown to the operator
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $content,
		public readonly bool $executable = false,
		public readonly string $reason = '',
	) {}
}
