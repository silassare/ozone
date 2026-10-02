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

namespace OZONE\Core\FS\Enums;

/**
 * The image drivers OZone renders with (`oz.files` `OZ_IMAGE_DRIVER`).
 */
enum ImageDriverName: string
{
	/** The first the server has, in this order: libvips, Imagick, GD. */
	case AUTO    = 'auto';
	case VIPS    = 'vips';
	case IMAGICK = 'imagick';
	case GD      = 'gd';
}
