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
use PHPUtils\PortablePattern;

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

	/**
	 * Whether each parameter of the path is required, by name: false for one in an optional part.
	 *
	 * @var array<string, bool>
	 */
	private array $params_found = [];

	/**
	 * Route constructor.
	 *
	 * @param Router       $router
	 * @param array        $methods
	 * @param callable     $callable
	 * @param RouteOptions $options
	 * @param string       $source   the route provider that maps the route, '' for none
	 * @param int          $ordinal  the position of the route among those its source maps
	 */
	public function __construct(
		private readonly Router $router,
		private readonly array $methods,
		callable $callable,
		private readonly RouteOptions $options,
		private readonly string $source = '',
		private readonly int $ordinal = 0
	) {
		$this->handler = $callable;
	}

	/**
	 * Gets route name.
	 *
	 * Shortcut of {@see RouteOptions::getName()}
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
	 * The route provider that maps this route: '' when none does (a {@see Events\RouterCreated}
	 * listener, a test).
	 *
	 * @internal read by {@see Router::compileTable()}
	 */
	public function getSource(): string
	{
		return $this->source;
	}

	/**
	 * The position of this route among the routes its source maps: with the source, what identifies
	 * it in a {@see RouteTable}.
	 *
	 * @internal read by {@see Router::compileTable()}
	 */
	public function getOrdinal(): int
	{
		return $this->ordinal;
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
	 * Returns a stable identity for this route: its full name, sorted methods and full path.
	 *
	 * Built from the definition only, so it is identical across requests and processes.
	 * Used to scope per-route state such as form sessions and form resume caches.
	 *
	 * @return string
	 */
	public function key(): string
	{
		$methods = $this->methods;
		\sort($methods);

		return $this->getName() . '|' . \implode(',', $methods) . '|' . $this->getPath();
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
	 * Returns the regular expression a path must match, run as a portable pattern is
	 * ({@see PortablePattern::toPcre()}): in Unicode mode, and with `$` at the very end of the path,
	 * so a client checking a param with its pattern reads it the way the router does.
	 */
	public function getRegExp(): string
	{
		return self::REG_DELIMITER . '^' . $this->getParserResult() . '$' . self::REG_DELIMITER . 'uD';
	}

	/**
	 * Returns the parameters found after parsing the route path if any.
	 *
	 * @return array
	 */
	public function getPathParams(): array
	{
		$this->ensureParsed();

		return \array_keys($this->params_found);
	}

	/**
	 * Checks if a parameter of the path is required: false for one in an optional part (`[/:state]`)
	 * or not in the path.
	 */
	public function isPathParamRequired(string $name): bool
	{
		$this->ensureParsed();

		return $this->params_found[$name] ?? false;
	}

	/**
	 * Asserts that a parameter pattern is one a route may declare: a portable pattern body
	 * ({@see PortablePattern}), so a client checks a value the way the router matches it, without
	 * `^` and `$`, which the router adds.
	 *
	 * @throws InvalidArgumentException
	 */
	public static function assertParamPattern(string $name, string $pattern): void
	{
		$reason = null;

		if (\str_starts_with($pattern, '^')) {
			$reason = 'should not start with "^"';
		} elseif (\str_ends_with($pattern, '$')) {
			$reason = 'should not end with "$"';
		} else {
			try {
				PortablePattern::assertPortable(self::REG_DELIMITER . $pattern . self::REG_DELIMITER);
			} catch (InvalidArgumentException $e) {
				$reason = $e->getMessage();
			}
		}

		if (null !== $reason) {
			throw new InvalidArgumentException(\sprintf(
				'Route parameter "%s" pattern "%s" is not valid: %s',
				$name,
				$pattern,
				$reason
			));
		}
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

		$matches = [];
		$passed  = 1 === \preg_match($this->getRegExp(), $path, $matches);

		if ($passed) {
			$params = $matches;
		}

		return $passed;
	}

	/**
	 * Returns the declared parameters.
	 *
	 * This will include global parameters.
	 *
	 * @return array<string, string>
	 */
	public function getDeclaredParams(): array
	{
		return \array_merge($this->router->getGlobalParams(), $this->options->getParams());
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
				$declared_params     = $this->getDeclaredParams();
				$parser              = new RoutePathParser($path, $this->router);
				$this->parser_result = $parser->parse($declared_params, $params_found);
				$this->params_found  = \array_map(\boolval(...), $params_found);
			} else {
				$this->parser_result = $path;
			}

			$this->parsed = true;
		}
	}
}
