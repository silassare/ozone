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

/**
 * Interface AuthorizationCredentialsInterface.
 */
interface AuthorizationCredentialsInterface extends ArrayCapableInterface
{
	/**
	 * Should return newly generated raw auth code.
	 *
	 * @return string
	 */
	public function newCode(): string;

	/**
	 * Should return newly generated raw auth token.
	 *
	 * @return string
	 */
	public function newToken(): string;

	/**
	 * Gets raw auth code.
	 *
	 * @return string
	 */
	public function getCode(): string;

	/**
	 * Sets raw auth code.
	 *
	 * @param string $code
	 */
	public function setCode(string $code): static;

	/**
	 * Gets raw auth token.
	 *
	 * @return string
	 */
	public function getToken(): string;

	/**
	 * Sets raw auth token.
	 *
	 * @param string $token
	 */
	public function setToken(string $token): static;

	/**
	 * Gets the auth reference.
	 *
	 * @return string
	 */
	public function getReference(): string;

	/**
	 * Sets the auth reference.
	 *
	 * @param string $reference
	 */
	public function setReference(string $reference): static;

	/**
	 * Gets the auth process refresh key.
	 *
	 * @return string
	 */
	public function getRefreshKey(): string;

	/**
	 * Sets the auth process refresh key.
	 *
	 * @param string $refresh_key
	 */
	public function setRefreshKey(string $refresh_key): static;

	/**
	 * Gets auth link.
	 *
	 * @return Uri
	 */
	public function getLink(): Uri;
}
