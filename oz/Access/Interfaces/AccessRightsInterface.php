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

namespace OZONE\Core\Access\Interfaces;

use OZONE\Core\Db\OZAuth;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface AccessRightsInterface.
 */
interface AccessRightsInterface extends ArrayCapableInterface
{
	/**
	 * Loads access rights info from auth entity.
	 */
	public static function from(OZAuth $auth): static;

	/**
	 * Pushes a scope to the stack.
	 */
	public function pushScope(self $scope): static;

	/**
	 * Add allowed action.
	 *
	 * allow: users.read
	 * allow: users.read_all
	 * allow: articles.read
	 * allow: articles.update
	 */
	public function allow(string $action): static;

	/**
	 * Add denied action.
	 *
	 * useful when you want to allow all action on entities and deny delete action.
	 *
	 * allow: users.*
	 * deny: users.delete_all
	 * deny: users.delete
	 */
	public function deny(string $action): static;

	/**
	 * Checks if all given action are allowed.
	 */
	public function can(string ...$actions): bool;

	/**
	 * Asserts that all given action are allowed.
	 */
	public function assertCan(string ...$actions): void;
}
