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

namespace OZONE\Core\Auth\Interfaces;

use OZONE\Core\Sessions\Session;

/**
 * Class SessionBasedAuthMethodInterface.
 */
interface SessionBasedAuthMethodInterface extends StatefulAuthMethodInterface
{
	/**
	 * Returns the session instance.
	 *
	 * @return Session
	 */
	public function session(): Session;
}
