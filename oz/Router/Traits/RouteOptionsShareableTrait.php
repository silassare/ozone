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

namespace OZONE\OZ\Router\Traits;

use InvalidArgumentException;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Router\Interfaces\RouteGuardInterface;
use OZONE\OZ\Router\Interfaces\RouteGuardProviderInterface;
use OZONE\OZ\Router\Route;
use OZONE\OZ\Router\RouteGuard;
use OZONE\OZ\Router\RouteInfo;

/**
 * Trait RouteOptionsShareableTrait.
 */
trait RouteOptionsShareableTrait
{
	protected array $route_params = [];

	/**
	 * @var null|callable|\OZONE\OZ\Router\Interfaces\RouteGuardInterface
	 */
	protected $route_guard;

	/**
	 * @var null|callable|\OZONE\OZ\Forms\Form
	 */
	protected $route_form;

	/**
	 * Sets route guard.
	 *
	 * The route guard may be a:
	 * - name in configs `oz.routes.guards`
	 * - a fully qualified classname implementing {@see \OZONE\OZ\Router\Interfaces\RouteGuardProviderInterface}
	 * - an instance of {@see \OZONE\OZ\Router\Interfaces\RouteGuardInterface}
	 * - or a callable that will be called with {@see \OZONE\OZ\Router\RouteInfo} as argument
	 *   and should return an instance of {@see \OZONE\OZ\Router\Interfaces\RouteGuardInterface} or null.
	 *
	 * @param callable|\OZONE\OZ\Router\Interfaces\RouteGuardInterface|string $guard
	 *
	 * @return $this
	 */
	public function guard(string|callable|RouteGuardInterface $guard): static
	{
		if (\is_string($guard)) {
			if (\class_exists($guard)) {
				$provider_class = $guard;
			} else {
				$provider_class = Configs::get('oz.routes.guards', $guard);

				if (!$provider_class) {
					throw new RuntimeException(\sprintf(
						'Unknown route guard provider name "%s".',
						$provider_class
					));
				}
			}

			if (!\is_subclass_of($provider_class, RouteGuardProviderInterface::class)) {
				throw new RuntimeException(\sprintf(
					'Route guard provider "%s" should be subclass of: %s',
					$provider_class,
					RouteGuardProviderInterface::class
				));
			}

			/** @var RouteGuardProviderInterface $provider_class */
			$guard = [$provider_class, 'getGuard'];
		}

		$this->route_guard = $guard;

		return $this;
	}

	/**
	 * Route form.
	 *
	 * @param callable|\OZONE\OZ\Forms\Form $form
	 *
	 * @return $this
	 */
	public function form(callable|Form $form): static
	{
		$this->route_form = $form;

		return $this;
	}

	/**
	 * Add route parameter.
	 *
	 * @param string $name
	 * @param string $pattern
	 *
	 * @return $this
	 */
	public function param(string $name, string $pattern = Route::DEFAULT_PARAM_PATTERN): static
	{
		if (!self::checkPattern($pattern, $reason)) {
			throw new InvalidArgumentException(\sprintf(
				'Route parameter name "%s" pattern is not valid or is too complex. Keep it simple: %s',
				$name,
				$reason
			));
		}

		$this->route_params[$name] = $pattern;

		return $this;
	}

	/**
	 * Add route parameters.
	 *
	 * @param array $params
	 *
	 * @return $this
	 */
	public function params(array $params): static
	{
		foreach ($params as $param => $pattern) {
			$this->param($param, $pattern);
		}

		return $this;
	}

	/**
	 * Get route form.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return null|\OZONE\OZ\Forms\Form
	 */
	public function getForm(RouteInfo $ri): ?Form
	{
		if (null !== $this->route_form && \is_callable($this->route_form)) {
			$form_builder = $this->route_form;
			$form         = $form_builder($ri);

			if (null === $form || $form instanceof Form) {
				return $form;
			}

			throw (new RuntimeException(\sprintf(
				'Route form builder should return instance of "%s" or "null" not: %s',
				Form::class,
				\get_debug_type($form)
			))
			)->suspectCallable($form_builder);
		}

		return $this->route_form;
	}

	/**
	 * Gets route parameters.
	 *
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->route_params;
	}

	/**
	 * Gets route guard.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Router\Interfaces\RouteGuardInterface
	 */
	public function getGuard(RouteInfo $ri): RouteGuardInterface
	{
		$guard = $this->route_guard;

		if (\is_callable($guard)) {
			$ret = $guard($ri);

			if (null !== $ret) {
				if ($ret instanceof RouteGuardInterface) {
					return $ret;
				}

				throw (new RuntimeException(\sprintf(
					'Route guard provider should return instance of "%s" or "null" not: %s',
					RouteGuardInterface::class,
					\get_debug_type($ret)
				))
				)->suspectCallable($guard);
			}
		}

		if ($guard instanceof RouteGuardInterface) {
			return $guard;
		}

		// default
		return new RouteGuard($ri->getContext());
	}

	/**
	 * Checks if the parameter pattern is complex or is invalid.
	 *
	 * Should be valid regex pattern
	 * Should not starts with ^
	 * Should not ends with $
	 *
	 * @param string      $pattern
	 * @param null|string &$reason
	 *
	 * @return bool
	 */
	protected static function checkPattern(string $pattern, ?string &$reason = null): bool
	{
		if (\str_starts_with($pattern, '^')) {
			$reason = 'should not start with "^"';

			return false;
		}

		if (\str_ends_with($pattern, '$')) {
			$reason = 'should not end with "$"';

			return false;
		}

		\set_error_handler(static function (): void {
		}, \E_WARNING);
		$is_invalid = false === \preg_match(Route::REG_DELIMITER . $pattern . Route::REG_DELIMITER, '');
		$reason     = \preg_last_error_msg();
		\restore_error_handler();

		return !$is_invalid;
	}
}
