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

use OZONE\Core\Db\OZAuth;

/**
 * Interface AuthScopeInterface.
 */
interface AuthScopeInterface
{
	/**
	 * Loads scope info from auth.
	 *
	 * @param OZAuth $auth
	 *
	 * @return static
	 */
	public static function from(OZAuth $auth): static;

	/**
	 * Gets the auth label.
	 *
	 * @return string
	 */
	public function getLabel(): string;

	/**
	 * Sets the auth label.
	 *
	 * @param string $label
	 *
	 * @return $this
	 */
	public function setLabel(string $label): self;

	/**
	 * Gets the maximum failure allowed.
	 *
	 * @return int
	 */
	public function getTryMax(): int;

	/**
	 * Sets the maximum failure allowed.
	 *
	 * @param int $try_max
	 *
	 * @return $this
	 */
	public function setTryMax(int $try_max): self;

	/**
	 * Gets the lifetime in seconds.
	 *
	 * @return int
	 */
	public function getLifetime(): int;

	/**
	 * Sets the lifetime in seconds.
	 *
	 * @param int $lifetime
	 *
	 * @return $this
	 */
	public function setLifetime(int $lifetime): self;

	/**
	 * Gets the access rights.
	 *
	 * @return AuthAccessRightsInterface
	 */
	public function getAccessRight(): AuthAccessRightsInterface;

	/**
	 * Sets the access rights.
	 *
	 * @param AuthAccessRightsInterface $access_right
	 *
	 * @return $this
	 */
	public function setAccessRight(AuthAccessRightsInterface $access_right): self;
}
