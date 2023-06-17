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
 * Interface AuthCredentialsInterface.
 */
interface AuthCredentialsInterface extends ArrayCapableInterface
{
	/**
	 * Should return newly generated raw authorization code.
	 *
	 * @return string
	 */
	public function newCode(): string;

	/**
	 * Should return newly generated raw authorization token.
	 *
	 * @return string
	 */
	public function newToken(): string;

	/**
	 * Gets raw authorization code.
	 *
	 * @return string
	 */
	public function getCode(): string;

	/**
	 * Sets raw authorization code.
	 *
	 * @param string $code
	 *
	 * @return $this
	 */
	public function setCode(string $code): self;

	/**
	 * Gets raw authorization token.
	 *
	 * @return string
	 */
	public function getToken(): string;

	/**
	 * Sets raw authorization token.
	 *
	 * @param string $token
	 *
	 * @return $this
	 */
	public function setToken(string $token): self;

	/**
	 * Gets the authorization reference.
	 *
	 * @return string
	 */
	public function getReference(): string;

	/**
	 * Sets the authorization reference.
	 *
	 * @param string $reference
	 *
	 * @return $this
	 */
	public function setReference(string $reference): self;

	/**
	 * Gets the authorization process refresh key.
	 *
	 * @return string
	 */
	public function getRefreshKey(): string;

	/**
	 * Sets the authorization process refresh key.
	 *
	 * @param string $refresh_key
	 *
	 * @return $this
	 */
	public function setRefreshKey(string $refresh_key): self;

	/**
	 * Gets authorization link.
	 *
	 * @return \OZONE\Core\Http\Uri
	 */
	public function getLink(): Uri;
}
