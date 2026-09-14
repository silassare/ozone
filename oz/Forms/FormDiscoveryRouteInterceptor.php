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

namespace OZONE\Core\Forms;

use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\RouteInfo;

/**
 * Class FormDiscoveryRouteInterceptor.
 *
 * Route interceptor that discovers form bundles for routes in form discovery requests.
 */
final class FormDiscoveryRouteInterceptor implements RouteInterceptorInterface
{
	/**
	 * FormDiscoveryRouteInterceptor constructor.
	 */
	public function __construct(private readonly RouteInfo $ri) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'oz:form:discovery:route:interceptor';
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
		$bundle  = $this->ri->route()->getOptions()->getFormBundle($this->ri);

		$svc = new class($this->ri) extends Service {};
		$svc->json()->setDone()->setForm($bundle);

		return AsyncValue::withPreview(static fn () => $svc->respond());
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
