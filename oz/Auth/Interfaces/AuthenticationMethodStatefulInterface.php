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

use OZONE\Core\Auth\StatefulAuthenticationMethodStore;

/**
 * Class AuthenticationMethodStatefulInterface.
 */
interface AuthenticationMethodStatefulInterface extends AuthenticationMethodInterface
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
	 * @return StatefulAuthenticationMethodStore
	 */
	public function store(): StatefulAuthenticationMethodStore;

	/**
	 * Persists the state.
	 *
	 * This is called after the response is ready to be sent.
	 */
	public function persist(): void;

	/**
	 * Destroy the state.
	 */
	public function destroy(): void;

	/**
	 * Renew the state.
	 *
	 * State store should be emptied and a new state id should be generated.
	 */
	public function renew(): void;

	/**
	 * Attach the user to the state.
	 *
	 * @param AuthUserInterface $user
	 */
	public function attachAuthUser(AuthUserInterface $user): void;

	/**
	 * Detach the current auth user from the state.
	 */
	public function detachAuthUser(): void;

	/**
	 * Get the state lifetime in seconds.
	 *
	 * @return int
	 */
	public function lifetime(): int;
}
