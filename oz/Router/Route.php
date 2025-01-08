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

use OZONE\Core\App\Context;

/**
 * Class Route.
 */
final class Route
{
	public const DEFAULT_PARAM_PATTERN = '[^/]+';
	public const REG_DELIMITER         = '~';
	public const ROUTE_PARAM_REG       = '~^[a-zA-Z_][a-zA-Z0-9_]*$~';

	/**
	 * @var callable
	 */
	private $handler;

	private bool $parsed = false;
	private string $parser_result;
	private array $params_found = [];

	/**
	 * Route constructor.
	 *
	 * @param Router       $router
	 * @param array        $methods
	 * @param callable     $callable
	 * @param RouteOptions $options
	 */
	public function __construct(
		private readonly Router $router,
		private readonly array $methods,
		callable $callable,
		private readonly RouteOptions $options
	) {
		$this->handler = $callable;
	}

	/**
	 * Gets route name.
	 *
	 * Shortcut of {@see \OZONE\Core\Router\RouteOptions::getName()}
	 */
	public function getName(): string
	{
		return $this->options->getName();
	}

	/**
	 * Checks if this route is dynamic.
	 *
	 * @return bool
	 */
	public function isDynamic(): bool
	{
		$path = $this->options->getPath();

		return \str_contains($path, '{')
			|| \str_contains($path, ':')
			|| \str_contains($path, '[');
	}

	/**
	 * Builds the route path with given parameters values.
	 *
	 * @param Context $context
	 * @param array   $params
	 *
	 * @return string
	 */
	public function buildPath(Context $context, array $params = []): string
	{
		$path = $this->options->getPath();
		if (!$this->isDynamic()) {
			return $path;
		}

		return (new RoutePathParser($path, $this->router))->buildPath($context, $params);
	}

	/**
	 * Returns the route path as defined.
	 *
	 * @param bool $full
	 *
	 * @return string
	 */
	public function getPath(bool $full = true): string
	{
		return $this->options->getPath($full);
	}

	/**
	 * Returns the route handler callable.
	 *
	 * @return callable
	 */
	public function getHandler(): callable
	{
		return $this->handler;
	}

	/**
	 * Returns the route options.
	 *
	 * @return RouteOptions
	 */
	public function getOptions(): RouteOptions
	{
		return $this->options;
	}

	/**
	 * Returns this route allowed HTTP request methods.
	 *
	 * @return array
	 */
	public function getMethods(): array
	{
		return $this->methods;
	}

	/**
	 * Checks if this route accept a given HTTP request method.
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	public function accept(string $method): bool
	{
		return \in_array(\strtoupper($method), $this->methods, true);
	}

	/**
	 * Returns parser result.
	 *
	 * @return string
	 */
	public function getParserResult(): string
	{
		$this->ensureParsed();

		return $this->parser_result;
	}

	/**
	 * Returns parameters.
	 *
	 * @return array
	 */
	public function getParams(): array
	{
		$this->ensureParsed();

		return $this->params_found;
	}

	/**
	 * Checks if a given path matches this route.
	 *
	 * @param string     $path
	 * @param null|array $params
	 *
	 * @return bool
	 */
	public function is(string $path, ?array &$params = []): bool
	{
		$this->ensureParsed();

		if (!$this->isDynamic()) {
			return $path === $this->options->getPath();
		}

		$regexp  = self::REG_DELIMITER . '^' . $this->parser_result . '$' . self::REG_DELIMITER;
		$matches = [];
		$passed  = 1 === \preg_match($regexp, $path, $matches);

		if ($passed) {
			$params = $matches;
		}

		return $passed;
	}

	/**
	 * This will lazily parse the route path.
	 */
	private function ensureParsed(): void
	{
		if (!$this->parsed) {
			$path = $this->options->getPath();
			if ($this->isDynamic()) {
				$params_found        = [];
				$declared_params     = \array_merge($this->router->getGlobalParams(), $this->options->getParams());
				$parser              = new RoutePathParser($path, $this->router);
				$this->parser_result = $parser->parse($path, $declared_params, $params_found);
				$this->params_found  = \array_keys($params_found);
			} else {
				$this->parser_result = $path;
			}

			$this->parsed = true;
		}
	}
}
