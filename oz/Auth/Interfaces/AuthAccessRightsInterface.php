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
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface AuthAccessRightsInterface.
 */
interface AuthAccessRightsInterface extends ArrayCapableInterface
{
	/**
	 * Loads access rights info from auth entity.
	 *
	 * @param OZAuth                         $auth
	 * @param null|AuthAccessRightsInterface $parent
	 *
	 * @return static
	 */
	public static function from(OZAuth $auth, ?self $parent = null): static;

	/**
	 * Add allowed action.
	 *
	 * allow: users.read
	 * allow: users.read_all
	 * allow: articles.read
	 * allow: articles.update
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
}
