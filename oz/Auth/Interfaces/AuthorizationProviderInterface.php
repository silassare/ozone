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
	 */
	public static function getName(): string;

	/**
	 * Resolve authorization provider instance using an existing auth entity.
	 */
	public static function resolve(Context $context, OZAuth $auth): static;

	/**
	 * Get payload.
	 */
	public function getPayload(): array;

	/**
	 * Returns json response.
	 */
	public function getJSONResponse(): JSONResponse;

	/**
	 * Gets credentials.
	 */
	public function getCredentials(): AuthorizationCredentialsInterface;

	/**
	 * Gets authorization scope.
	 */
	public function getScope(): AuthorizationScopeInterface;

	/**
	 * Sets authorization scope.
	 */
	public function setScope(AuthorizationScopeInterface $scope): static;

	/**
	 * Authorize with current credentials.
	 */
	public function authorize(AuthorizationSecretType $type): void;

	/**
	 * Get an auth process state.
	 */
	public function getState(): AuthorizationState;

	/**
	 * Generate new auth code, token ...
	 *
	 * @param null|AuthUserInterface $user if provided, the user will get ownership of the generated code, token ...
	 */
	public function generate(?AuthUserInterface $user = null): static;

	/**
	 * Refresh the auth process.
	 *
	 * @param bool $re_authorize true to force re-auth, false otherwise
	 */
	public function refresh(bool $re_authorize = true): static;

	/**
	 * Cancel the process.
	 */
	public function cancel(): static;
}
