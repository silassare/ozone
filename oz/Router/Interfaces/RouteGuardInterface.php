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

namespace OZONE\Core\Router\Interfaces;

use OZONE\Core\Forms\FormData;
use OZONE\Core\Router\RouteInfo;

/**
 * Interface RouteGuardInterface.
 */
interface RouteGuardInterface
{
	/**
	 * Returns rules.
	 */
	public function toRules(): array;

	/**
	 * Creates a new instance from rules.
	 *
	 * @param array $rules
	 *
	 * @return self
	 */
	public static function fromRules(array $rules): self;

	/**
	 * Check.
	 */
	public function check(RouteInfo $ri): void;

	/**
	 * Returns clean grant form data.
	 *
	 * @return null|FormData
	 */
	public function getFormData(): ?FormData;
}
