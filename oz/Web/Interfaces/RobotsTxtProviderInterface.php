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

namespace OZONE\Core\Web\Interfaces;

use OZONE\Core\App\Context;
use OZONE\Core\Web\RobotsTxt;

/**
 * What `/robots.txt` answers ({@see RobotsTxt}).
 *
 * A project or a plugin names its implementation in the `OZ_ROBOTS_TXT_PROVIDER` key of the
 * `oz.request` settings group, and a scope names one of its own in its `oz.request.php`. The class is
 * built with no argument, once per request for `/robots.txt`.
 *
 * The provider is given the request being answered, so one provider can answer per scope and per
 * host: the host served is `$context->getHost()`, the scope serving it `scope()`, and whether that
 * scope is an API one `$context->isApiContext()`.
 */
interface RobotsTxtProviderInterface
{
	/**
	 * The text of `/robots.txt` for this request, as a crawler reads it (RFC 9309): groups of
	 * `User-agent` lines followed by their `Allow` and `Disallow` rules, and any `Sitemap` line.
	 */
	public function robotsTxt(Context $context): string;
}
