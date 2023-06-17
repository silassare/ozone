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

namespace OZONE\Core\FS;

use OZONE\Core\Http\Stream as HttpStream;
use OZONE\Core\Http\Traits\StreamSourcesTraits;

/**
 * Class Stream.
 */
class FileStream extends HttpStream
{
	use StreamSourcesTraits;
}
