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

namespace OZONE\Core\Roles\Enums;

use OZONE\Core\Roles\Interfaces\RoleInterface;

/**
 * Enum Role.
 */
enum Role: string implements RoleInterface
{
	case SUPER_ADMIN = 'super-admin';
	case ADMIN       = 'admin';
	case EDITOR      = 'editor';

	/**
	 * {@inheritDoc}
	 */
	public static function admin(): self
	{
		return self::ADMIN;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function superAdmin(): self
	{
		return self::SUPER_ADMIN;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function editor(): self
	{
		return self::EDITOR;
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): int
	{
		return match ($this) {
			self::SUPER_ADMIN => 10_000,
			self::ADMIN       => 8_000,
			self::EDITOR      => 5_000,
		};
	}
}
