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
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\Resume\FormSessionStore;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\Router\Guards\CSRFRouteGuard;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;
use PHPUtils\Str;

/**
 * Class RouteInfo.
 */
final class RouteInfo
{
	/**
	 * Maps route guard FQCNs to produced data during check.
	 *
	 * @var array<class-string<RouteGuardInterface>, mixed>
	 */
	private array $guards_data;
	private ?FormDataClean $cleaned_fd = null;

	private ?RouteInterceptorInterface $interceptor = null;

	/**
	 * @var list<callable(Response):void>
	 */
	private array $on_success = [];

	/**
	 * RouteInfo constructor.
	 *
	 * @param Context                    $context       The context
	 * @param Route                      $route         The current route
	 * @param array                      $params        The route params
	 * @param null|callable(static):void $authenticator The authenticator
	 *
	 * @throws InvalidFormException
	 */
	public function __construct(
		private readonly Context $context,
		private readonly Route $route,
		private readonly array $params,
		?callable $authenticator = null,
	) {
		$this->guards_data = [];

		$authenticator && $authenticator($this);

		$this->callGuards();
		$this->runMiddlewares();

		$this->interceptor = $this->selectInterceptor();
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
	 * Checks if the route is intercepted.
	 *
	 * @return bool
	 */
	public function isIntercepted(): bool
	{
		return null !== $this->interceptor;
	}

	/**
	 * Gets the interceptor instance if any.
	 *
	 * @return null|RouteInterceptorInterface
	 */
	public function getInterceptor(): ?RouteInterceptorInterface
	{
		return $this->interceptor;
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
	 * If a handler was provided in the constructor, it will be returned,
	 * otherwise the regular route handler will be returned.
	 *
	 * @return callable(static):Response
	 */
	public function getEffectiveHandler(): callable
	{
		return null !== $this->interceptor
			? $this->interceptor->handle(...)
			: $this->route()->getHandler();
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
	 * @return FormDataClean
	 */
	public function getCleanFormData(): FormDataClean
	{
		if (null === $this->cleaned_fd) {
			// When there is an interceptor, checkRouteForm() is intentionally skipped
			// so we define an empty FormDataClean so form callables that read it during
			// form-bundle resolution don't throw.

			if (null === $this->interceptor) {
				throw new RuntimeException('Form data has not been checked yet.', [
					'_reason' => \sprintf(
						'%s called before the router called %s.',
						__METHOD__,
						Str::callableName([$this, 'checkRouteForm'])
					),
				]);
			}

			$this->cleaned_fd = new FormDataClean();
		}

		return $this->cleaned_fd;
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
	 * @throws InvalidFormException
	 *
	 * @internal this should be called once by the router before calling the route handler
	 */
	public function checkRouteForm(): void
	{
		if (null !== $this->cleaned_fd) {
			throw new RuntimeException('Form has already been checked.', [
				'_reason' => 'Only the router calls this method, once per route dispatch.',
			]);
		}

		// Held locally as well as on the instance: the same object either way, but the local keeps
		// the "assigned right here" guarantee readable through the try/catch below.
		$cleaned          = new FormDataClean();
		$this->cleaned_fd = $cleaned;

		if ($this->route->getOptions()->hasResumeSupport()) {
			$header_name = Settings::get('oz.request', 'OZ_FORM_RESUME_REF_HEADER_NAME');
			$resume_ref  = $this->context->getRequest()->getHeaderLine($header_name);

			if ('' !== $resume_ref) {
				// resume_ref present: the completed session is the input, provided it
				// belongs to this route's provider (and to this route when opened here).
				$cleaned->merge(FormSessionStore::requireCompletionForRoute($resume_ref, $this));

				// Drop the session only once the handler has returned a successful
				// response, so a failing handler leaves the wizard intact. This prevents
				// reuse and frees the cache entry without waiting for TTL.
				$this->onSuccess(static fn () => FormSessionStore::drop($resume_ref));

				return;
			}

			if (null !== $this->route->getOptions()->resolveProviderClass()) {
				// Provider-based routes have no standalone form — the session is the only input path.
				throw new BadRequestException('OZ_FORM_SESSION_REF_MISSING');
			}

			// ->resumable() only: the route's own form may still be submitted directly.
			// Fall through to normal form validation below.
		}

		$bundle = $this->route->getOptions()->getFormBundle($this);

		if (!$bundle) {
			return;
		}

		$unsafe_fd = $this->context->getRequest()->getUnsafeFormData();

		// Fast path: no form in the bundle opted into Form::resumable().
		if (null === $bundle->getResumeScope()) {
			$cleaned->merge($bundle->validate($unsafe_fd));

			return;
		}

		$this->validateResumable($bundle, $unsafe_fd);
	}

	/**
	 * Registers a callback to run once the route handler has returned a
	 * successful response.
	 *
	 * Scoped to this dispatch: unlike a global ResponseHook listener, it is
	 * discarded with this RouteInfo, so it can neither fire for another request
	 * nor pile up in a long-running process. It does not run when the handler
	 * throws or returns an unsuccessful response.
	 *
	 * @param callable(Response):void $callback
	 *
	 * @return $this
	 *
	 * @internal
	 */
	public function onSuccess(callable $callback): static
	{
		$this->on_success[] = $callback;

		return $this;
	}

	/**
	 * Runs the {@see self::onSuccess()} callbacks against the handler's response,
	 * at most once.
	 *
	 * @internal called by the router right after the route handler returns
	 */
	public function finalize(Response $response): void
	{
		$callbacks        = $this->on_success;
		$this->on_success = [];

		if (!$response->isSuccessful()) {
			return;
		}

		foreach ($callbacks as $callback) {
			$callback($response);
		}
	}

	/**
	 * The accumulator `checkRouteForm()` created for this dispatch.
	 *
	 * @return FormDataClean
	 */
	private function cleanFormData(): FormDataClean
	{
		if (null === $this->cleaned_fd) {
			throw new RuntimeException('The route form has not been checked yet.', [
				'_reason' => 'Only the router calls this, after checkRouteForm().',
			]);
		}

		return $this->cleaned_fd;
	}

	/**
	 * Validates a bundle whose forms opted into {@see Form::resumable()}.
	 *
	 * Values validated by an earlier failed attempt on this route are replayed (and
	 * re-checked), so the client only resends what is missing. A failed attempt saves
	 * what it managed to validate; a successful handler clears the entry. The entry is
	 * partitioned by route, so two routes sharing a form never prefill each other.
	 *
	 * Cost: one cache read, plus a write only when a failed attempt cleaned something
	 * new, plus a delete only when an entry was read and the handler succeeded.
	 *
	 * @throws InvalidFormException
	 */
	private function validateResumable(Form $bundle, FormData $unsafe_fd): void
	{
		$partition          = $this->route->key();
		[$prefilled, $drop] = $bundle->resume($this->context, $partition);
		$cleaned_fd         = $prefilled ?? new FormDataClean();
		$before             = $cleaned_fd->toArray();

		try {
			$bundle->validate($unsafe_fd, $cleaned_fd);
		} catch (InvalidFormException $e) {
			// validate() fills $cleaned_fd field by field, so it still holds everything
			// cleaned before the failure.
			if ($cleaned_fd->toArray() !== $before) {
				$bundle->saveForLater($this->context, $cleaned_fd, $partition);
			}

			throw $e;
		}

		// checkRouteForm() assigned it before calling this.
		$this->cleanFormData()->merge($cleaned_fd);

		if (null !== $prefilled) {
			$this->onSuccess(static fn () => $drop());
		}
	}

	/**
	 * Run all guards.
	 */
	private function callGuards(): void
	{
		$route_guards = $this->route->getOptions()->getGuards($this);

		// Unsafe requests authenticated by the session cookie need a CSRF token, unless the
		// route opted out (see CSRF::isRequiredByDefault()). Sub-requests were checked with
		// the request that made them.
		if (
			!$this->context->isSubRequest()
			&& CSRF::isRequiredByDefault($this->route->getOptions(), $this->context, $this->currentAuth())
		) {
			\array_unshift($route_guards, new CSRFRouteGuard(RequestScope::STATE));
		}

		foreach ($route_guards as $guard) {
			$results = $guard->check($this);

			$this->guards_data[$guard::class] = $results;
		}
	}

	/**
	 * The authentication method of the request, when the route defines one.
	 */
	private function currentAuth(): ?AuthenticationMethodInterface
	{
		return $this->context->authState()->current();
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

	/**
	 * Find the first interceptor that should intercept the request.
	 */
	private function selectInterceptor(): ?RouteInterceptorInterface
	{
		$interceptors = $this->route->getOptions()->getInterceptors();

		\usort($interceptors, self::interceptorsPriorityComparator(...));

		foreach ($interceptors as $interceptor) {
			$instance = $interceptor::instance($this);

			if ($instance->shouldIntercept()) {
				return $instance;
			}
		}

		return null;
	}

	/**
	 * Sorts interceptors by priority, highest first.
	 *
	 * @param class-string<RouteInterceptorInterface> $a
	 * @param class-string<RouteInterceptorInterface> $b
	 */
	private static function interceptorsPriorityComparator(string $a, string $b): int
	{
		$ap = $a::getPriority();
		$bp = $b::getPriority();

		if ($ap === $bp) {
			return 0;
		}

		return ($ap > $bp) ? -1 : 1;
	}
}
