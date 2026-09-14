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

use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\Resume\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Forms\Resume\ResumableFormProvider;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\Interfaces\RouteMiddlewareInterface;

/**
 * Class ResolvedRouteOptions.
 *
 * The options of a route merged with those it inherits from its groups, built once by
 * {@see RouteSharedOptions::resolved()} (and rebuilt when any route option changes) instead
 * of walking the group chain on every request. Also the way to inspect a route's effective
 * configuration.
 *
 * Inheritance: lists (auth methods, guards, middlewares, forms) are the parents' entries
 * followed by the route's own; params and interceptors are merged by name; CSRF, resume and
 * form declaration settings come from the innermost level that sets them.
 */
final class ResolvedRouteOptions
{
	/**
	 * ResolvedRouteOptions constructor.
	 *
	 * `$guard_entries` holds guards or guard factories, `$form_declarations` is outermost first,
	 * `$csrf_scope` is the scope of an explicit CSRF check, `$csrf_disabled` tells whether the
	 * default check is off, and `$resume_config` holds the `resumable()` scope and TTL.
	 *
	 * @param list<class-string<AuthenticationMethodInterface>>                    $authentication_methods
	 * @param list<array{type: string, ...}>                                       $guard_descriptors
	 * @param list<(callable(RouteInfo):?RouteGuardInterface)|RouteGuardInterface> $guard_entries
	 * @param list<(callable(RouteInfo):?Response)|RouteMiddlewareInterface>       $middlewares
	 * @param array<string, class-string<RouteInterceptorInterface>>               $interceptors
	 * @param array<string, string>                                                $params
	 * @param list<RouteFormDeclaration>                                           $form_declarations
	 * @param null|RequestScope                                                    $csrf_scope
	 * @param bool                                                                 $csrf_disabled
	 * @param null|array{0: RequestScope, 1: int}                                  $resume_config
	 */
	public function __construct(
		public readonly array $authentication_methods,
		public readonly array $guard_descriptors,
		public readonly array $guard_entries,
		public readonly array $middlewares,
		public readonly array $interceptors,
		public readonly array $params,
		public readonly array $form_declarations,
		public readonly ?RequestScope $csrf_scope,
		public readonly bool $csrf_disabled,
		public readonly ?array $resume_config,
	) {}

	/**
	 * The innermost form declaration, or null.
	 */
	public function formDeclaration(): ?RouteFormDeclaration
	{
		$last = \array_key_last($this->form_declarations);

		return null === $last ? null : $this->form_declarations[$last];
	}

	/**
	 * The documentation policy of the innermost form declaration, or null.
	 */
	public function docPolicy(): ?RouteFormDocPolicy
	{
		return $this->formDeclaration()?->getPolicy();
	}

	/**
	 * The resumable form provider of the innermost form declaration, or null.
	 *
	 * @return null|class-string<ResumableFormProviderInterface>
	 */
	public function providerClass(): ?string
	{
		return $this->formDeclaration()?->getProviderClass();
	}

	/**
	 * The provider driving resumption: the declared one, else {@see ResumableFormProvider}
	 * when the route is `resumable()`, else null (no resume support).
	 *
	 * @return null|class-string<ResumableFormProviderInterface>
	 */
	public function resumeProviderClass(): ?string
	{
		return $this->providerClass() ?? (null !== $this->resume_config ? ResumableFormProvider::class : null);
	}

	/**
	 * Whether the route can be driven through a form session.
	 */
	public function hasResumeSupport(): bool
	{
		return null !== $this->resumeProviderClass();
	}

	/**
	 * The guards of a request, factories resolved.
	 *
	 * @return list<RouteGuardInterface>
	 */
	public function guards(RouteInfo $ri): array
	{
		$guards = [];

		foreach ($this->guard_entries as $entry) {
			if ($entry instanceof RouteGuardInterface) {
				$guards[] = $entry;

				continue;
			}

			$guard = $entry($ri);

			if (null === $guard) {
				continue;
			}

			if (!$guard instanceof RouteGuardInterface) {
				throw (new RuntimeException(\sprintf(
					'Route guard provider should return instance of "%s" or "null" not: %s',
					RouteGuardInterface::class,
					\get_debug_type($guard)
				)))->suspectCallable($entry);
			}

			$guards[] = $guard;
		}

		return $guards;
	}

	/**
	 * The forms of a request, outermost first.
	 *
	 * @return list<Form>
	 */
	public function forms(RouteInfo $ri): array
	{
		$forms = [];

		foreach ($this->form_declarations as $declaration) {
			$form = $declaration->resolve($ri);

			if (null !== $form) {
				$forms[] = $form;
			}
		}

		return $forms;
	}

	/**
	 * The forms of a request merged into one, submitted to the request URI and method; null
	 * when the route has no form.
	 */
	public function formBundle(RouteInfo $ri): ?Form
	{
		$forms = $this->forms($ri);

		if (empty($forms)) {
			return null;
		}

		$request = $ri->getContext()->getRequest();

		return self::merge($forms)
			->submitTo($request->getUri())
			->method($request->getMethod());
	}

	/**
	 * The forms that can be documented without a request, outermost first: declarations
	 * with the OPAQUE or DYNAMIC policy, and dynamic factories without preview, give none.
	 *
	 * @return list<Form>
	 */
	public function docForms(): array
	{
		$forms = [];

		foreach ($this->form_declarations as $declaration) {
			$form = $declaration->getDocForm();

			if (null !== $form) {
				$forms[] = $form;
			}
		}

		return $forms;
	}

	/**
	 * The documentable forms merged into one, or null.
	 */
	public function staticFormBundle(): ?Form
	{
		$forms = $this->docForms();

		return empty($forms) ? null : self::merge($forms);
	}

	/**
	 * @param non-empty-list<Form> $forms
	 */
	private static function merge(array $forms): Form
	{
		$bundle = new Form();

		foreach ($forms as $form) {
			$bundle->merge($form);
		}

		return $bundle;
	}
}
