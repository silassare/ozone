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

/**
 * Class RouteSearchResult.
 */
class RouteSearchResult
{
	/**
	 * RouteSearchResult constructor.
	 *
	 * @param RouteSearchStatus                                        $status
	 * @param null|array{route:\OZONE\Core\Router\Route, params:array} $found
	 * @param array{route:\OZONE\Core\Router\Route, params:array}[]    $static_full_matches
	 * @param array{route:\OZONE\Core\Router\Route, params:array}[]    $dynamic_full_matches
	 * @param array{route:\OZONE\Core\Router\Route, params:array}[]    $static_path_matches
	 * @param array{route:\OZONE\Core\Router\Route, params:array}[]    $dynamic_path_matches
	 */
	public function __construct(
		protected RouteSearchStatus $status,
		protected ?array $found,
		protected array $static_full_matches,
		protected array $dynamic_full_matches,
		protected array $static_path_matches,
		protected array $dynamic_path_matches
	) {
	}

	/**
	 * Gets the status.
	 *
	 * @return RouteSearchStatus
	 */
	public function status(): RouteSearchStatus
	{
		return $this->status;
	}

	/**
	 * Gets the route that matched.
	 *
	 * @return null|array{route:\OZONE\Core\Router\Route, params:array}
	 */
	public function found(): ?array
	{
		return $this->found;
	}

	/**
	 * Gets other static routes that matched.
	 *
	 * @return array{route:\OZONE\Core\Router\Route, params:array}[]
	 */
	public function staticMatches(): array
	{
		return $this->static_full_matches;
	}

	/**
	 * Gets other dynamic routes that matched.
	 *
	 * @return array{route:\OZONE\Core\Router\Route, params:array}[]
	 */
	public function dynamicMatches(): array
	{
		return $this->dynamic_full_matches;
	}

	/**
	 * Gets other static routes that matched but with wrong methods.
	 *
	 * @return array{route:\OZONE\Core\Router\Route, params:array}[]
	 */
	public function staticMatchesWrongMethods(): array
	{
		return $this->static_path_matches;
	}

	/**
	 * Gets other dynamic routes that matched but with wrong methods.
	 *
	 * @return array{route:\OZONE\Core\Router\Route, params:array}[]
	 */
	public function dynamicMatchesWrongMethods(): array
	{
		return $this->dynamic_path_matches;
	}
}
