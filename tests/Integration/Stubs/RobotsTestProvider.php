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

namespace __PLH_NAMESPACE__;

use OZONE\Core\App\Context;
use OZONE\Core\Web\Interfaces\RobotsTxtProviderInterface;

/**
 * A project's `/robots.txt`: crawlers welcome, and the scope and the host served written in a
 * comment, so a test reads which request the answer was made for.
 */
final class RobotsTestProvider implements RobotsTxtProviderInterface
{
	public function robotsTxt(Context $context): string
	{
		return '# ' . scope()->getName() . ' on ' . $context->getHost() . "\n"
			. "User-agent: *\nAllow: /\n";
	}
}
