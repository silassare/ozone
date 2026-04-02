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

use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\Db\OZAuth;

/**
 * Interface AuthorizationScopeInterface.
 */
interface AuthorizationScopeInterface
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
	 */
	public function setLabel(string $label): static;

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
	 */
	public function setTryMax(int $try_max): static;

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
	 */
	public function setLifetime(int $lifetime): static;

	/**
	 * Gets the access rights.
	 *
	 * @return AccessRightsInterface
	 */
	public function getAccessRight(): AccessRightsInterface;

	/**
	 * Sets the access rights.
	 *
	 * @param AccessRightsInterface $access_right
	 */
	public function setAccessRight(AccessRightsInterface $access_right): static;
}
