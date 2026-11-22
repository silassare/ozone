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

namespace OZONE\Core\Web;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Web\Interfaces\RobotsTxtProviderInterface;

/**
 * The default `/robots.txt`: every crawler kept out of everything, in every scope and on every host.
 *
 * The strict answer, for an API, an administration or a project not yet launched alike; a project
 * that wants to be crawled says so with a provider of its own.
 */
final class DefaultRobotsTxtProvider implements RobotsTxtProviderInterface
{
	public const string CONTENT = "User-agent: *\nDisallow: /\n";

	#[Override]
	public function robotsTxt(Context $context): string
	{
		return self::CONTENT;
	}
}
