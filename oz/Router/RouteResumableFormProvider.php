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
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Class RouteResumableFormProvider.
 *
 * A single-step resumable form provider that wraps a named route's static form bundle.
 * The client submits the target route name in the init form, and the provider returns
 * that route's static form bundle as the one and only step.
 *
 * Registered as the built-in `'route'` provider in `oz.forms.providers`.
 *
 * Flow:
 *   initForm()        -> Form with `route_name` field
 *   nextStep(index 0) -> route's static form bundle (the real form to fill)
 *   nextStep(index 1) -> null (done)
 *
 * `resumeScope()` and `resumeTTL()` are derived from the target route's static form bundle
 * (stored in `$progress` private state after step 0 resolves the bundle).
 */
final class RouteResumableFormProvider extends AbstractResumableFormProvider
{
	public const PROVIDER_NAME = 'route';

	/**
	 * Cache key used inside FormResumeProgress to remember the bundle's settings after
	 * they are resolved at step 0, so step 1 can read them without re-querying the router.
	 */
	private const RESUME_SCOPE_KEY = 'bundle_resume_scope';
	private const RESUME_TTL_KEY   = 'bundle_resume_ttl';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function providerName(): string
	{
		return self::PROVIDER_NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns a Form with a single required `route_name` field.
	 * The client submits the target route's name to start the session.
	 */
	#[Override]
	public static function initForm(): ?Form
	{
		$form = new Form();
		$form->string('route_name', true);

		return $form;
	}

	/**
	 * {@inheritDoc}
	 *
	 * - At step index 0: resolves `$cleaned_form->get('route_name')` to a route, fetches
	 *   its static form bundle, validates that the bundle has resumable() enabled, stores
	 *   `resumeScope` and `resumeTTL` in `$progress`, and returns the bundle.
	 * - At step index 1: returns null (sequence complete).
	 *
	 * @throws NotFoundException when the route does not exist
	 * @throws RuntimeException  when the route has no static form bundle or the bundle
	 *                           does not have resumable() enabled
	 */
	#[Override]
	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		if (0 !== $progress->getStepIndex()) {
			// Step 1+ means the single form was already submitted — sequence complete.
			return null;
		}

		$route_name = (string) $cleaned_form->get('route_name');
		$router     = $this->ri->getContext()->getRouter();
		$route      = $router->getRoute($route_name);

		if (null === $route) {
			throw new NotFoundException('OZ_FORM_ROUTE_NOT_FOUND', ['route' => $route_name]);
		}

		$bundle = $route->getOptions()->getStaticFormBundle();

		if (null === $bundle) {
			throw new RuntimeException(\sprintf(
				'Route "%s" has no static form bundle. Attach a form via ->form(...) to use it as a resumable form provider.',
				$route_name
			));
		}

		if (null === $bundle->getResumeScope()) {
			throw new RuntimeException(\sprintf(
				'Route "%s" form bundle does not have resumable() enabled. '
					. 'Call ->resumable(...) on the form to use it as a resumable form provider.',
				$route_name
			));
		}

		// Store the bundle's settings in private progress state so resumeScope()/resumeTTL()
		// can return them on subsequent calls without re-resolving the route.
		$progress->set(self::RESUME_SCOPE_KEY, $bundle->getResumeScope()->value);
		$progress->set(self::RESUME_TTL_KEY, $bundle->getResumeTTL());

		return $bundle;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Derived from the target route's bundle after step 0 resolves it.
	 * Falls back to STATE when the bundle has not been resolved yet (init phase).
	 */
	#[Override]
	public function resumeScope(): RequestScope
	{
		if (null !== $this->ri) {
			// During initSession the provider has not yet called nextStep(), so the
			// progress does not exist yet. Use the default scope for scope_id derivation.
			// The correct scope is stored after step 0 completes.
		}

		return RequestScope::STATE;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function resumeTTL(): int
	{
		return 3600;
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
