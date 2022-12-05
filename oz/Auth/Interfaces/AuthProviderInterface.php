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
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\JSONResponse;

/**
 * Interface AuthProviderInterface.
 */
interface AuthProviderInterface
{
	/**
	 * Get provider instance.
	 *
	 * @param \OZONE\OZ\Core\Context                            $context
	 * @param null|\OZONE\OZ\Auth\Interfaces\AuthScopeInterface $scope
	 *
	 * @return self
	 */
	public static function getInstance(Context $context, ?AuthScopeInterface $scope = null): self;

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Returns json response.
	 *
	 * @return \OZONE\OZ\Core\JSONResponse
	 */
	public function getJSONResponse(): JSONResponse;

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
