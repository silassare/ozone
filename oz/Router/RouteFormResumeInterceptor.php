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
use OZONE\Core\App\Settings;
use OZONE\Core\Forms\Services\ResumableFormService;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;

/**
 * Class RouteFormResumeInterceptor.
 *
 * Route interceptor that handles form-resume requests directly on the matched route,
 * instead of routing them through the standalone `/form/:provider/...` endpoints.
 *
 * Fires when:
 *  - the request carries the `X-OZONE-Form-Resume: ?1` header, AND
 *  - the matched route has resume support (via `->resumable()` or `->form(provider($class))`)
 *
 * All route auth, guards, and middlewares run before this interceptor, so the
 * full security context of the real route is enforced on every resume operation.
 *
 * The sub-action (init/state/next/back/cancel/evaluate) is read from the
 * `X-OZONE-Form-Resume-Action` header; when absent the default is "init".
 */
final class RouteFormResumeInterceptor implements RouteInterceptorInterface
{
	/**
	 * RouteFormResumeInterceptor constructor.
	 */
	public function __construct(private readonly RouteInfo $ri) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'oz:route:form:resume:interceptor';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Runs at priority 1 — before the form-discovery interceptor (priority 0),
	 * but after any custom interceptors registered with a higher priority.
	 */
	#[Override]
	public static function getPriority(): int
	{
		return 1;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function shouldIntercept(): bool
	{
		return $this->ri->getContext()->getRequest()->isFormResumeRequest()
			&& $this->ri->route()->getOptions()->hasResumeSupport();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function handle(): Response
	{
		$options       = $this->ri->route()->getOptions();
		$providerClass = $options->resolveProviderClass() ?? RouteResumableFormProvider::class;
		$action        = $this->ri->getContext()->getRequest()->getHeaderLine(
			Settings::get('oz.request', 'OZ_FORM_RESUME_ACTION_HEADER_NAME')
		);
		$action       = '' !== $action ? $action : ResumableFormService::ACTION_INIT;

		$svc = new ResumableFormService($this->ri);

		return $svc->handleFromRealContext($providerClass, $action);
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
