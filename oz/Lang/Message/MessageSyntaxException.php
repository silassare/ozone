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

namespace OZONE\Core\Lang\Message;

use InvalidArgumentException;

/**
 * A text of a catalog that does not follow the message syntax, and where.
 */
final class MessageSyntaxException extends InvalidArgumentException
{
	public function __construct(
		string $reason,
		public readonly string $text,
		public readonly int $offset,
	) {
		parent::__construct(\sprintf('%s at offset %d in "%s".', $reason, $offset, $text));
	}
}
