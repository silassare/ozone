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
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Services\FormService;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Class RouteResumableFormProvider.
 *
 * A single-step resumable form provider that wraps a named route's static form bundle.
 * Resolved lazily via the `route:` prefix — no boot-time registration required.
 *
 * Provider ref format: `route:{route_name}` (e.g. `route:oz:signup`).
 *
 * Resolution requirements (checked in {@see AbstractResumableFormProvider::resolve()}):
 *  - The route must exist and have an explicit name (not an auto-generated fallback).
 *  - The route's static form bundle must have {@see Form::resumable()} enabled
 *    (i.e. {@see Form::getResumeScope()} is non-null).
 *
 * Lifecycle: `initForm()` is null, so `initSession` immediately calls `nextStep()`.
 * At step 0 the form bundle is returned; at step 1 null is returned (done).
 * `totalSteps()` is always 1.
 * `resumeScope()` and `resumeTTL()` are derived from the bundle at resolve time.
 */
final class RouteResumableFormProvider extends AbstractResumableFormProvider
{
	public const PROVIDER_REF_PREFIX = 'route:';

	private string $t_route_name;

	private Form $t_bundle;

	/**
	 * @param string $route_name the explicit route name
	 * @param Form   $bundle     the static form bundle (must have resumable() enabled)
	 */
	public function __construct(string $route_name, Form $bundle)
	{
		$this->t_route_name = $route_name;
		$this->t_bundle     = $bundle;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns the provider ref for this instance, e.g. `route:oz:signup`.
	 * Because {@see RouteResumableFormProvider} is resolved lazily and never registered
	 * in the static registry, this instance method is the correct way to obtain the ref;
	 * the static variant throws to prevent accidental registry use.
	 */
	#[Override]
	public static function providerRef(): string
	{
		// This method is intentionally unreachable for RouteResumableFormProvider:
		// instances are constructed lazily by resolve(), not via the registry.
		throw new RuntimeException('RouteResumableFormProvider is not registered in the static registry; use route:{name} as the provider ref.');
	}

	/**
	 * Returns the provider ref for this specific instance.
	 *
	 * @return string e.g. `route:oz:signup`
	 */
	public function getProviderRef(): string
	{
		return self::PROVIDER_REF_PREFIX . $this->t_route_name;
	}

	/**
	 * Resolves a `route:{name}` ref to a {@see RouteResumableFormProvider} instance.
	 *
	 * Called exclusively from {@see AbstractResumableFormProvider::resolve()}.
	 * Validates that:
	 *  - the route exists in the current router,
	 *  - its name was assigned explicitly (not auto-generated),
	 *  - its static form bundle has {@see Form::resumable()} enabled.
	 *
	 * @param string $route_name the route name (with the `route:` prefix already stripped)
	 *
	 * @return self
	 *
	 * @throws NotFoundException when the route does not exist
	 * @throws RuntimeException  when the route name is not explicit or the form is not resumable
	 */
	public static function resolveRoute(string $route_name): self
	{
		$router = context()->getRouter();
		$route  = $router->getRoute($route_name);

		if (null === $route) {
			throw new NotFoundException('OZ_FORM_ROUTE_NOT_FOUND', ['route' => $route_name]);
		}

		$options = $route->getOptions();

		if (!$options->isNameExplicit()) {
			throw new RuntimeException(\sprintf(
				'Route "%s" uses an auto-generated name. '
					. 'Call ->name(...) explicitly on the route before using it as a resumable form provider.',
				$route_name,
			));
		}

		$bundle = $options->getStaticFormBundle();

		if (null === $bundle) {
			throw new RuntimeException(\sprintf(
				'Route "%s" has no static form bundle. Attach a form via ->form(...) to use it as a resumable form provider.',
				$route_name
			));
		}

		if (null === $bundle->getResumeScope()) {
			throw new RuntimeException(\sprintf(
				'Route "%s" form bundle does not have resumable() enabled. '
					. 'Call ->resumable(...) on the form or route to use it as a resumable form provider.',
				$route_name
			));
		}

		return new self($route_name, $bundle);
	}

	/**
	 * Returns the provider ref for a given route name.
	 *
	 * @param string $route_name
	 *
	 * @return string e.g. `route:oz:signup`
	 */
	public static function refForRoute(string $route_name): string
	{
		return self::PROVIDER_REF_PREFIX . $route_name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function nextStep(FormData $progress): ?Form
	{
		$step = (int) $progress->get(FormService::STEP_INDEX_KEY, 0);

		if (0 === $step) {
			return $this->t_bundle;
		}

		// Step 1 means the single form was already submitted — sequence is complete.
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function resumeScope(): RequestScope
	{
		// Non-null guaranteed: resolveRoute() asserts getResumeScope() !== null before constructing this instance.
		return $this->t_bundle->getResumeScope() ?? RequestScope::STATE;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function resumeTTL(): int
	{
		return $this->t_bundle->getResumeTTL();
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
