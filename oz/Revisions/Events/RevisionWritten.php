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

namespace OZONE\Core\Revisions\Events;

use PHPUtils\Events\Event;

/**
 * Class RevisionWritten.
 *
 * A revision was written. Values only: what changed, and not the content, which a listener reads
 * from the namespace if it needs it.
 */
final class RevisionWritten extends Event
{
	/**
	 * @param null|string $digest the content's digest, null when the revision removed it
	 */
	public function __construct(
		public readonly string $namespace,
		public readonly string $key,
		public readonly int $number,
		public readonly ?string $digest,
	) {}
}
