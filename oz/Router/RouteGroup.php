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

namespace OZONE\Core\Router;

use Override;

/**
 * Class RouteGroup.
 */
final class RouteGroup extends RouteSharedOptions
{
	public function __construct(string $path, ?self $parent = null)
	{
		parent::__construct($path, $parent);
	}

	/**
	 * Returns the parent group, or null when this is a root group.
	 *
	 * @return null|RouteGroup
	 */
	#[Override]
	public function getParent(): ?self
	{
		return $this->parent;
	}
}
