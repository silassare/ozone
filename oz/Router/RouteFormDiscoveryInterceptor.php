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
use OZONE\Core\App\Service;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;

/**
 * Class RouteFormDiscoveryInterceptor.
 *
 * Route interceptor that discovers form bundles for routes in form discovery requests.
 */
final class RouteFormDiscoveryInterceptor implements RouteInterceptorInterface
{
	/**
	 * RouteFormDiscoveryInterceptor constructor.
	 */
	public function __construct(private readonly RouteInfo $ri) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'route-form-discovery';
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getPriority(): int
	{
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function shouldIntercept(): bool
	{
		return $this->ri->getContext()->getRequest()->isFormDiscoveryRequest();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function handle(): Response
	{
		$handler  = Service::createHandler(static function (Service $svc) {
			$ri      = $svc->getContext()->getRouteInfo();
			$bundle  = $ri->route()->getOptions()->getFormBundle($ri);

			$svc->json()->setDone()->setForm($bundle);

			$svc->respond();
		});

		return $handler($this->ri);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function instance(RouteInfo $ri): static
	{
		return new self($ri);
	}
}
