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
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Db\OZAuth;

/**
 * Interface AuthorizationProviderInterface.
 */
interface AuthorizationProviderInterface
{
	/**
	 * Get authorization provider name.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Resolve authorization provider instance using an existing auth entity.
	 *
	 * @param Context $context
	 * @param OZAuth  $auth
	 *
	 * @return self
	 */
	public static function resolve(Context $context, OZAuth $auth): self;

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
	 * @return AuthorizationCredentialsInterface
	 */
	public function getCredentials(): AuthorizationCredentialsInterface;

	/**
	 * Gets authorization scope.
	 *
	 * @return AuthorizationScopeInterface
	 */
	public function getScope(): AuthorizationScopeInterface;

	/**
	 * Sets authorization scope.
	 *
	 * @param AuthorizationScopeInterface $scope
	 *
	 * @return $this
	 */
	public function setScope(AuthorizationScopeInterface $scope): self;

	/**
	 * Authorize with current credentials.
	 *
	 * @param AuthorizationSecretType $type
	 */
	public function authorize(AuthorizationSecretType $type): void;

	/**
	 * Get an auth process state.
	 *
	 * @return AuthorizationState
	 */
	public function getState(): AuthorizationState;

	/**
	 * Generate new auth code, token ...
	 *
	 * @param null|AuthUserInterface $user if provided, the user will get ownership of the generated code, token ...
	 */
	public function generate(?AuthUserInterface $user = null): self;

	/**
	 * Refresh the auth process.
	 *
	 * @param bool $re_authorize true to force re-auth, false otherwise
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
