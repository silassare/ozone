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

namespace OZONE\Core\Forms\Interfaces;

/**
 * A type whose rules do not all live in its own options.
 *
 * A password's length, a username's length and pattern, the genders accepted: they come from the
 * settings when the type does not set them, so a client reading only the type's options could not
 * check a value the way the server will. A type that implements this says what those rules resolve to,
 * and {@see \OZONE\Core\Forms\Field::frontendType()} adds them to what a discovered form sends.
 *
 * Only what a client may see: these reach the browser.
 */
interface FrontendTypeOptionsInterface
{
	/**
	 * The rules this type applies beyond its own options, as they resolve now.
	 *
	 * @return array<string, mixed>
	 */
	public function frontendOptions(): array;
}
