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

/**
 * Interface AuthScopeInterface.
 */
interface AuthScopeInterface extends AuthAccessRightsInterface
{
	/**
	 * Gets the authorization label.
	 *
	 * @return string
	 */
	public function getLabel(): string;

	/**
	 * Sets the authorization label.
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
}
