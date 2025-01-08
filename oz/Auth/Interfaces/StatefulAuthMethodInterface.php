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

use OZONE\Core\Auth\StatefulAuthStore;

/**
 * Class StatefulAuthMethodInterface.
 */
interface StatefulAuthMethodInterface extends AuthMethodInterface
{
	/**
	 * State ID.
	 *
	 * @return string
	 */
	public function stateID(): string;

	/**
	 * Returns the stateful auth store.
	 *
	 * @return StatefulAuthStore
	 */
	public function store(): StatefulAuthStore;

	/**
	 * Persists the state.
	 *
	 * This is called after the response is ready to be sent.
	 */
	public function persist(): void;
}
