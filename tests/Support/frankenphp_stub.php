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

// FrankenPHP's function, for tests/Runtime/RuntimeTest: FrankenPHP defines it in classic mode as
// well as in worker mode, so its presence alone must not make a worker.

if (!\function_exists('frankenphp_handle_request')) {
	function frankenphp_handle_request(callable $callback): bool
	{
		return false;
	}
}
