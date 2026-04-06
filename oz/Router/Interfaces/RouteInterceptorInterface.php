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

use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;

/**
 * Interface RouteInterceptor.
 */
interface RouteInterceptorInterface
{
	/**
	 * Gets the name of the interceptor.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * Gets the priority of the interceptor.
	 *
	 * Interceptors with higher priority run first.
	 *
	 * @return int
	 */
	public static function getPriority(): int;

	/**
	 * Determines whether the interceptor should intercept the current request.
	 */
	public function shouldIntercept(): bool;

	/**
	 * Handles the request. Called only if `shouldIntercept()` returns true.
	 *
	 * @return Response
	 */
	public function handle(): Response;

	/**
	 * Creates an instance of the interceptor from the given route info.
	 *
	 * @param RouteInfo $ri the route info object
	 *
	 * @return static
	 */
	public static function instance(RouteInfo $ri): static;
}
