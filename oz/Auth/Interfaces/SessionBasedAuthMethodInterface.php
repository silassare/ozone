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
use OZONE\Core\Sessions\SessionState;

/**
 * Class SessionBasedAuthMethodInterface.
 */
interface SessionBasedAuthMethodInterface extends AuthMethodInterface
{
	/**
	 * Returns the session instance.
	 *
	 * @return \OZONE\Core\Sessions\Session
	 */
	public function session(): Session;

	/**
	 * Alias of {@link Session::state()}.
	 *
	 * @return \OZONE\Core\Sessions\SessionState
	 */
	public function state(): SessionState;

	/**
	 * Alias of {@link Session::id()}.
	 *
	 * @return string
	 */
	public function id(): string;
}
