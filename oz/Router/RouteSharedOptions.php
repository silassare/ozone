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

namespace OZONE\OZ\Router;

use InvalidArgumentException;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Router\Interfaces\RouteGuardInterface;
use OZONE\OZ\Router\Interfaces\RouteGuardProviderInterface;
use OZONE\OZ\Users\UserRole;

/**
 * Class SharedOptions.
 */
class RouteSharedOptions
{
	protected array $_params = [];

	/**
	 * @var array<callable|\OZONE\OZ\Router\Interfaces\RouteGuardInterface>
	 */
	protected array $_guards = [];

	/**
	 * @var null|callable|\OZONE\OZ\Forms\Form
	 */
	protected $_form;

	protected readonly string      $_path;
	protected readonly ?RouteGroup $_parent;
	private string                 $_name = '';

	/**
	 * RouteSharedOptions constructor.
	 *
	 * @param string                           $path
	 * @param null|\OZONE\OZ\Router\RouteGroup $parent
	 */
	protected function __construct(
		string $path,
		?RouteGroup $parent = null
	) {
		$this->_path   = $path;
		$this->_parent = $parent;
	}

	/**
	 * RouteSharedOptions destructor.
	 */
	public function __destruct()
	{
		unset($this->_guards, $this->_form, $this->_params);
	}

	/**
	 * Define the route name.
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function name(string $name): static
	{
		$this->_name = $name;

		return $this;
	}

	/**
	 * Adds a 2FA guard.
	 *
	 * @return $this
	 */
	public function with2FA(): static
	{
		return $this->guard(function (RouteInfo $ri) {
			return (new RouteGuard($ri->getContext()))->with2FA();
		});
	}

	/**
	 * Adds a guard that check user role.
	 *
	 * @return $this
	 */
	public function withRole(string|UserRole $role): static
	{
		return $this->guard(function (RouteInfo $ri) use ($role) {
			return (new RouteGuard($ri->getContext()))->withRole($role);
		});
	}

	/**
	 * Add guard.
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
	 * @return static
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

		$this->_guards[] = $guard;

		return $this;
	}

	/**
	 * Sets form.
	 *
	 * @param callable|\OZONE\OZ\Forms\Form $form
	 *
	 * @return static
	 */
	public function form(callable|Form $form): static
	{
		$this->_form = $form;

		return $this;
	}

	/**
	 * Add parameter.
	 *
	 * @param string $name
	 * @param string $pattern
	 *
	 * @return static
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

		$this->_params[$name] = $pattern;

		return $this;
	}

	/**
	 * Add parameters.
	 *
	 * @param array $params
	 *
	 * @return static
	 */
	public function params(array $params): static
	{
		foreach ($params as $param => $pattern) {
			$this->param($param, $pattern);
		}

		return $this;
	}

	/**
	 * Gets parent.
	 *
	 * @return null|\OZONE\OZ\Router\RouteGroup
	 */
	public function getParent(): ?RouteGroup
	{
		return $this->_parent;
	}

	/**
	 * Gets name.
	 *
	 * @param bool $full
	 *
	 * @return string
	 */
	public function getName(bool $full = true): string
	{
		if ($full && $this->_parent) {
			$parent_name = $this->_parent->getName();
			if (!empty($parent_name)) {
				return $parent_name . '.' . $this->_name;
			}
		}

		return $this->_name;
	}

	/**
	 * Gets path.
	 *
	 * @param bool $full
	 *
	 * @return string
	 */
	public function getPath(bool $full = true): string
	{
		if ($full && $this->_parent) {
			$parent_path = $this->_parent->getPath();
			if (!empty($parent_path)) {
				return self::safePathConcat($parent_path, $this->_path);
			}
		}

		return $this->_path;
	}

	/**
	 * Gets route form bundle.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return null|\OZONE\OZ\Forms\Form
	 */
	public function getFormBundle(RouteInfo $ri): ?Form
	{
		$forms = $this->getForms($ri);

		if (!empty($forms)) {
			$bundle = new Form();

			foreach ($forms as $form) {
				$bundle->merge($form);
			}

			return $bundle;
		}

		return null;
	}

	/**
	 * Gets route forms.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Forms\Form[]
	 */
	public function getForms(RouteInfo $ri): array
	{
		$forms = $this->_parent?->getForms($ri) ?? [];

		if (null !== $this->_form) {
			if ($this->_form instanceof Form) {
				$forms[] = $this->_form;
			} else {
				$form_builder = $this->_form;
				$result       = $form_builder($ri);

				if (null !== $result) {
					if ($result instanceof Form) {
						$forms[] = $result;
					} else {
						throw (new RuntimeException(\sprintf(
							'Route form builder should return instance of "%s" or "null" not: %s',
							Form::class,
							\get_debug_type($result)
						))
						)->suspectCallable($form_builder);
					}
				}
			}
		}

		return $forms;
	}

	/**
	 * Gets route parameters.
	 *
	 * @return array
	 */
	public function getParams(): array
	{
		$params = $this->_parent?->getParams() ?? [];

		return \array_merge($params, $this->_params);
	}

	/**
	 * Gets route guards.
	 *
	 * @return array<RouteGuardInterface>
	 */
	public function getGuards(RouteInfo $ri): array
	{
		$results = $this->_parent?->getGuards($ri) ?? [];
		foreach ($this->_guards as $guard) {
			if (\is_callable($guard)) {
				$provider = $guard;
				$guard    = $provider($ri);

				if ((null !== $guard) && !($guard instanceof RouteGuardInterface)) {
					throw (new RuntimeException(\sprintf(
						'Route guard provider should return instance of "%s" or "null" not: %s',
						RouteGuardInterface::class,
						\get_debug_type($guard)
					))
					)->suspectCallable($provider);
				}
			}

			if ($guard instanceof RouteGuardInterface) {
				$results[] = $guard;
			}
		}

		return $results;
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

	/**
	 * Concat two path.
	 *
	 * @param string $prefix
	 * @param string $path
	 *
	 * @return string
	 */
	private static function safePathConcat(string $prefix, string $path): string
	{
		if (empty($prefix)) {
			return $path;
		}

		if (empty($path)) {
			return $prefix;
		}

		return \rtrim($prefix, '/') . '/' . \ltrim($path, '/');
	}
}
