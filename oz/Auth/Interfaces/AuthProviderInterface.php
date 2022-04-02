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

namespace OZONE\OZ\Auth\Interfaces;

use OZONE\OZ\Auth\AuthSecretType;
use OZONE\OZ\Auth\AuthState;

/**
 * Interface AuthProviderInterface.
 */
interface AuthProviderInterface
{
	/**
	 * Gets credentials.
	 *
	 * @return \OZONE\OZ\Auth\Interfaces\AuthCredentialsInterface
	 */
	public function getCredentials(): AuthCredentialsInterface;

	/**
	 * Gets scope.
	 *
	 * @return \OZONE\OZ\Auth\Interfaces\AuthScopeInterface
	 */
	public function getScope(): AuthScopeInterface;

	/**
	 * Validate an authorization with current credentials.
	 *
	 * @param \OZONE\OZ\Auth\AuthSecretType $type
	 */
	public function authorize(AuthSecretType $type): void;

	/**
	 * Get an authorization process state.
	 *
	 * @return \OZONE\OZ\Auth\AuthState
	 */
	public function getState(): AuthState;

	/**
	 * Generate new authorization code, token ...
	 */
	public function generate(): self;

	/**
	 * Refresh the authorization process.
	 *
	 * @param bool $re_authorize true to force re-authorization, false otherwise
	 *
	 * @return $this
	 */
	public function refresh(bool $re_authorize = true): self;

	/**
	 * Cancel the process.
	 *
	 * @return $this
	 */
	public function cancel(): self;
}
