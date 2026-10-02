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

namespace OZONE\Core\FS\Images\Interfaces;

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZFile;

/**
 * Who may get an image's untouched original (the token `original`): its metadata included, without
 * the watermark a policy forces. A project names its own in `oz.files` `OZ_IMAGE_ORIGINAL_ACCESS`.
 */
interface ImageOriginalAccessInterface
{
	public function allows(OZFile $file, Context $context): bool;
}
