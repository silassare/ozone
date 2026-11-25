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
use OZONE\Core\App\Settings;
use OZONE\Core\Forms\Resume\Services\ResumableFormService;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\RouteFormDeclaration;
use OZONE\Core\Router\RouteInfo;

/**
 * Class FormDiscoveryRouteInterceptor.
 *
 * Route interceptor that discovers form bundles for routes in form discovery requests.
 *
 * The bundle is answered as a top-level `form` key next to `data` in the envelope, and the route
 * handler is not run.
 *
 * A route whose form is declared through a provider has no bundle to discover: that declaration
 * ({@see RouteFormDeclaration::provider()}) holds neither a form nor a factory, so
 * the answer carries no `form` key. The forms of such a route are the provider's steps, read through
 * the resume flow ({@see ResumableFormService}).
 */
final class FormDiscoveryRouteInterceptor implements RouteInterceptorInterface
{
	/**
	 * FormDiscoveryRouteInterceptor constructor.
	 */
	public function __construct(private readonly RouteInfo $ri) {}

	#[Override]
	public static function getName(): string
	{
		return 'oz:form:discovery:route:interceptor';
	}

	#[Override]
	public static function getPriority(): int
	{
		return 0;
	}

	#[Override]
	public function shouldIntercept(): bool
	{
		return $this->ri->getContext()->getRequest()->isFormDiscoveryRequest();
	}

	#[Override]
	public function handle(): Response
	{
		$bundle  = $this->ri->route()->getOptions()->getFormBundle($this->ri);

		$svc = new class($this->ri) extends Service {};
		$svc->json()->setDone()->setForm($bundle);

		return $svc->respond();
	}

	#[Override]
	public static function instance(RouteInfo $ri): static
	{
		return new self($ri);
	}

	/**
	 * What a form discovery of a rate-limited route is counted against, in place of the route's own
	 * limit: a quota of its own per IP and per route (`OZ_FORM_DISCOVERY_IP_RATE` in
	 * `OZ_FORM_DISCOVERY_IP_INTERVAL`), so a page showing a form spends none of the attempts the route
	 * rations, and asking for forms is bounded all the same. Null when the rate is 0: not counted.
	 */
	public static function rateLimit(RouteInfo $ri): ?RouteRateLimitInterface
	{
		$rate = (int) Settings::get('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE', 120);

		if ($rate <= 0) {
			return null;
		}

		return new IPRateLimit(
			$ri,
			$rate,
			(int) Settings::get('oz.forms', 'OZ_FORM_DISCOVERY_IP_INTERVAL', 60),
			1,
			'form-discovery'
		);
	}
}
