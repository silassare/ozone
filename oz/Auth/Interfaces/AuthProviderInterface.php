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

use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Auth\AuthSecretType;
use OZONE\Core\Auth\AuthState;

/**
 * Interface AuthProviderInterface.
 */
interface AuthProviderInterface
{
	/**
	 * Get auth provider name.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Get auth provider instance.
	 *
	 * @param Context $context
	 * @param array   $payload
	 *
	 * @return self
	 */
	public static function get(Context $context, array $payload): self;

	/**
	 * Get payload.
	 *
	 * @return array
	 */
	public function getPayload(): array;

	/**
	 * Returns json response.
	 *
	 * @return JSONResponse
	 */
	public function getJSONResponse(): JSONResponse;

	/**
	 * Gets credentials.
	 *
	 * @return AuthCredentialsInterface
	 */
	public function getCredentials(): AuthCredentialsInterface;

	/**
	 * Gets scope.
	 *
	 * @return AuthScopeInterface
	 */
	public function getScope(): AuthScopeInterface;

	/**
	 * Sets scope.
	 *
	 * @param AuthScopeInterface $scope
	 *
	 * @return $this
	 */
	public function setScope(AuthScopeInterface $scope): self;

	/**
	 * Validate an authorization with current credentials.
	 *
	 * @param AuthSecretType $type
	 */
	public function authorize(AuthSecretType $type): void;

	/**
	 * Get an authorization process state.
	 *
	 * @return AuthState
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
