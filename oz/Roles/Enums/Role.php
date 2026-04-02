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

use Override;
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
	#[Override]
	public static function admin(): static
	{
		return self::ADMIN;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function superAdmin(): static
	{
		return self::SUPER_ADMIN;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function editor(): static
	{
		return self::EDITOR;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function weight(): int
	{
		return match ($this) {
			self::SUPER_ADMIN => 10_000,
			self::ADMIN       => 8_000,
			self::EDITOR      => 5_000,
		};
	}
}
