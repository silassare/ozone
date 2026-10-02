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

use Intervention\Image\Interfaces\ImageInterface;

/**
 * A filter token a project adds to the ones OZone renders (`ImageTokens::register()`, or the
 * `OZ_IMAGE_TOKENS` setting). It draws with Intervention Image directly.
 *
 * Its canonical form bounds it: a URL must not make the server render, nor keep, a rendition per
 * value, so a token with a number snaps it to a few steps, as OZone's own tokens do.
 */
interface ImageTokenInterface
{
	/**
	 * The canonical form of a token when it is this one's (`[a-z0-9]+`, its values snapped), null
	 * when it is not. A built-in token is never asked, nor one starting with `wm` (watermarks).
	 */
	public function canonical(string $token): ?string;

	/**
	 * Draws a canonical token on the image, after the built-in geometry, among the effects in the
	 * order the URL gives them.
	 */
	public function apply(ImageInterface $image, string $token): void;
}
