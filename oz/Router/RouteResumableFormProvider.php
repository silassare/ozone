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
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Class RouteResumableFormProvider.
 *
 * Default resumable form provider used by the {@see RouteFormResumeInterceptor}
 * when a route declares `->resumable()` without an explicit provider class. It
 * delegates the form bundle and resume settings to the matched route's options at
 * runtime, so it only functions correctly when invoked through the interceptor
 * pipeline (where `$this->ri` is set to the real route's RouteInfo).
 *
 * Flow:
 *   initForm()        -> null  (no init step; the route is already known from the URL)
 *   nextStep(index 0) -> the route's form bundle at request time (`getFormBundle($ri)`)
 *   nextStep(index 1) -> null  (sequence complete)
 *
 * `resumeScope()` and `resumeTTL()` are resolved from the route options' `resolveResumeConfig()`.
 * When the route has no resume config (should not happen in normal usage), both fall back to their
 * `AbstractResumableFormProvider` defaults.
 */
final class RouteResumableFormProvider extends AbstractResumableFormProvider
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return 'oz:route:resumable:form:provider';
	}

	/**
	 * {@inheritDoc}
	 *
	 * - Step 0: returns the matched route's form bundle via `getFormBundle($this->ri)`.
	 * - Step 1+: returns null (sequence complete).
	 */
	#[Override]
	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		if (0 !== $progress->getStepIndex()) {
			return null;
		}

		return $this->ri->route()->getOptions()->getFormBundle($this->ri);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Derived from the matched route's resume config. Falls back to STATE when not set.
	 */
	#[Override]
	public function resumeScope(): RequestScope
	{
		return $this->ri->route()->getOptions()->resolveResumeConfig()[0] ?? RequestScope::STATE;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Derived from the matched route's resume config. Falls back to 3600 when not set.
	 */
	#[Override]
	public function resumeTTL(): int
	{
		return $this->ri->route()->getOptions()->resolveResumeConfig()[1] ?? 3600;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function totalSteps(): ?int
	{
		return 1;
	}
}
