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

use OZONE\OZ\Db\OZAuth;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface AuthScopeInterface.
 */
interface AuthScopeInterface extends ArrayCapableInterface
{
	/**
	 * Loads scope info from auth.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @return static
	 */
	public static function from(OZAuth $auth): self;

	/**
	 * Add allowed action.
	 *
	 * @param string $action
	 *
	 * @return static
	 */
	public function allow(string $action): self;

	/**
	 * Add denied action.
	 *
	 * useful when you want to allow all action on entities and deny delete action.
	 *
	 * allow: users.*
	 * deny: users.delete_all
	 * deny: users.delete
	 *
	 * @param string $action
	 *
	 * @return static
	 */
	public function deny(string $action): self;

	/**
	 * Checks if all given action are allowed.
	 *
	 * @param string ...$actions
	 *
	 * @return bool
	 */
	public function can(string ...$actions): bool;

	/**
	 * Assert if all given action are allowed.
	 *
	 * @param string ...$actions
	 */
	public function assertCan(string ...$actions): void;

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
	 * Gets the value to be authorized.
	 *
	 * @return string
	 */
	public function getValue(): string;

	/**
	 * Sets the authorization process owner key.
	 *
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setValue(string $value): self;

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
