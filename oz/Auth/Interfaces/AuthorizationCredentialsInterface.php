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

use OZONE\Core\Http\Uri;
use PHPUtils\Interfaces\ArrayCapableInterface;
use SensitiveParameter;

/**
 * Interface AuthorizationCredentialsInterface.
 */
interface AuthorizationCredentialsInterface extends ArrayCapableInterface
{
	/**
	 * Should return newly generated raw auth code.
	 */
	public function newCode(): string;

	/**
	 * Should return newly generated raw auth token.
	 */
	public function newToken(): string;

	/**
	 * Gets raw auth code.
	 */
	public function getCode(): string;

	/**
	 * Sets raw auth code.
	 */
	public function setCode(#[SensitiveParameter] string $code): static;

	/**
	 * Gets raw auth token.
	 */
	public function getToken(): string;

	/**
	 * Sets raw auth token.
	 */
	public function setToken(#[SensitiveParameter] string $token): static;

	/**
	 * Gets the auth reference.
	 */
	public function getReference(): string;

	/**
	 * Sets the auth reference.
	 */
	public function setReference(string $reference): static;

	/**
	 * Gets the auth process refresh key.
	 */
	public function getRefreshKey(): string;

	/**
	 * Sets the auth process refresh key.
	 */
	public function setRefreshKey(string $refresh_key): static;

	/**
	 * Gets auth link.
	 */
	public function getLink(): Uri;
}
