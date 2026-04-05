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

use InvalidArgumentException;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\Services\ResumableFormService;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use PHPUtils\Str;

/**
 * Class RouteInfo.
 */
final class RouteInfo
{
	/**
	 * Maps route guard FQN class name to produced data during check.
	 *
	 * @var array<class-string<RouteGuardInterface>, mixed>
	 */
	private array $guards_data;
	private ?FormData $clean_form_data = null;

	/**
	 * @var null|callable(static):Response
	 */
	private $replace_handler;

	/**
	 * RouteInfo constructor.
	 *
	 * @param Context                          $context         The context
	 * @param Route                            $route           The current route
	 * @param array                            $params          The route params
	 * @param null|callable(static):void       $authenticator   The authenticator
	 * @param null|(callable(static):Response) $replace_handler The route handler to run, if null the regular route handler will be used
	 *
	 * @throws InvalidFormException
	 */
	public function __construct(
		private readonly Context $context,
		private readonly Route $route,
		private readonly array $params,
		?callable $authenticator = null,
		?callable $replace_handler = null
	) {
		$this->guards_data     = [];
		$this->replace_handler = $replace_handler;

		$authenticator && $authenticator($this);
		$this->callGuards();
		$this->runMiddlewares();
	}

	/**
	 * Gets current request context.
	 *
	 * @return Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * Gets current route.
	 *
	 * @return Route
	 */
	public function route(): Route
	{
		return $this->route;
	}

	/**
	 * Gets the effective route handler to run.
	 *
	 * If a handler was provided in the constructor, it will be returned, otherwise the regular route handler will be returned.
	 *
	 * @return callable(static):Response
	 */
	public function getEffectiveHandler(): callable
	{
		return $this->replace_handler ?? $this->route()->getHandler();
	}

	/**
	 * Gets current route parameters.
	 *
	 * @return array
	 */
	public function params(): array
	{
		return $this->params;
	}

	/**
	 * Gets current route parameter value with a given name.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function param(string $name, mixed $def = null): mixed
	{
		return $this->params[$name] ?? $def;
	}

	/**
	 * Shortcut for {@see Request::getUri()}.
	 *
	 * @return Uri
	 */
	public function uri(): Uri
	{
		return $this->context->getRequest()
			->getUri();
	}

	/**
	 * Gets validated form data.
	 *
	 * @return FormData
	 */
	public function getCleanFormData(): FormData
	{
		if (null === $this->clean_form_data) {
			// In discovery mode checkRouteForm() is intentionally skipped — return empty
			// FormData so form callables that read it during form-bundle resolution don't throw.
			if ($this->context->isFormDiscoveryRequest()) {
				return new FormData();
			}

			throw new RuntimeException('Form data has not been checked yet.', [
				'_reason' => \sprintf('%s called before the router called %s.', __METHOD__, Str::callableName([$this, 'checkRouteForm'])),
			]);
		}

		return $this->clean_form_data;
	}

	/**
	 * Gets validated form field value.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function getCleanFormField(string $name, mixed $def = null): mixed
	{
		return $this->getCleanFormData()->get($name, $def);
	}

	/**
	 * Gets guard stored results.
	 *
	 * @param class-string<RouteGuardInterface> $guard_fqn_class
	 *
	 * @return mixed
	 */
	public function getGuardStoredResults(string $guard_fqn_class): mixed
	{
		$guard_data = $this->guards_data[$guard_fqn_class] ?? null;

		if (null !== $guard_data) {
			return $guard_data;
		}

		throw new InvalidArgumentException(\sprintf('Guard "%s" has no results stored.', $guard_fqn_class));
	}

	/**
	 * Shortcut for {@see Request::getUnsafeFormData()}.
	 *
	 * @param bool $include_files
	 *
	 * @return FormData
	 */
	public function getUnsafeFormData(bool $include_files = true): FormData
	{
		return $this->context->getRequest()
			->getUnsafeFormData($include_files);
	}

	/**
	 * Shortcut for {@see Request::getUnsafeFormField()}.
	 *
	 * @param string     $name
	 * @param null|mixed $def
	 *
	 * @return mixed
	 */
	public function getUnsafeFormField(string $name, mixed $def = null): mixed
	{
		return $this->context->getRequest()
			->getUnsafeFormField($name, $def);
	}

	/**
	 * Validates the form data if any.
	 *
	 * @internal this should be called once by the router before calling the route handler
	 *
	 * @throws InvalidFormException
	 */
	public function checkRouteForm(): void
	{
		if (null !== $this->clean_form_data) {
			throw new RuntimeException('Form has already been checked.', [
				'_reason' => 'Only the router should call this method, and it should be called only once per route dispatch.',
			]);
		}

		$this->clean_form_data = new FormData();

		$declaration = $this->route->getOptions()->getFormDeclaration();

		if (null !== $declaration) {
			$provider_class = $declaration->getProviderClass();

			if (null !== $provider_class) {
				// Route declared with a ResumableFormProviderInterface provider.
				// Read the resume reference from the request header and inject the completed FormData.
				$header_name = Settings::get('oz.request', 'OZ_FORM_RESUMABLE_REF_HEADER_NAME');
				$resume_ref  = $this->context->getRequest()->getHeaderLine($header_name);

				if ('' === $resume_ref) {
					throw new BadRequestException('OZ_FORM_RESUME_REF_MISSING');
				}

				$clean_fd = ResumableFormService::requireCompletion(
					$provider_class::providerName(),
					$resume_ref,
					$this->context
				);

				$this->clean_form_data->merge($clean_fd);

				return;
			}
		}

		$bundle = $this->route->getOptions()->getFormBundle($this);

		if ($bundle) {
			[$prefilled, $drop_resume_cache] = $bundle->resume($this->context);

			$unsafe_fd = $this->context->getRequest()->getUnsafeFormData();

			$clean_fd = $bundle->validate($unsafe_fd, $prefilled);

			// Validation succeeded — the route handler is about to execute with
			// the fully assembled FormData. Delete the resume cache entry if any so
			// stale partial data never bleeds into a future request.
			$drop_resume_cache();

			$this->clean_form_data->merge($clean_fd);
		}
	}

	/**
	 * Run all guards.
	 */
	private function callGuards(): void
	{
		$route_guards = $this->route->getOptions()->getGuards($this);

		foreach ($route_guards as $guard) {
			$results = $guard->check($this);

			$this->guards_data[$guard::class] = $results;
		}
	}

	/**
	 * Run all middlewares.
	 */
	private function runMiddlewares(): void
	{
		$middlewares = $this->route->getOptions()->getMiddlewares();

		foreach ($middlewares as $mdl) {
			if ($mdl instanceof RouteMiddlewareInterface) {
				$response = $mdl->run($this);
			} else {
				$response = $mdl($this);
			}

			if ($response) {
				$this->context->setResponse($response);
			}
		}
	}
}
