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
use OZONE\Core\Http\Uri;
use PHPUtils\PortablePattern;

/**
 * Class Route.
 */
final class Route
{
	public const string DEFAULT_PARAM_PATTERN      = '[^/]+';
	public const string REG_DELIMITER              = '~';
	public const string ROUTE_PARAM_REG            = '~^[a-zA-Z_][a-zA-Z0-9_]*$~';

	/**
	 * What a parameter of a host template matches when no pattern is declared for it: one label of
	 * the host name, never the dot that ends it.
	 */
	public const string DEFAULT_HOST_PARAM_PATTERN = '[^.]+';

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
	 * The regular expression a request host must match: null when the route declares no host.
	 */
	private ?string $host_regexp = null;

	/**
	 * Whether each parameter of the host is required, by name: false for one in an optional part.
	 *
	 * @var array<string, bool>
	 */
	private array $host_params_found = [];

	/**
	 * Route constructor.
	 *
	 * @param string $source  the route provider that maps the route, '' for none
	 * @param int    $ordinal the position of the route among those its source maps
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
	 */
	public function buildPath(Context $context, array $params = []): string
	{
		$path = $this->options->getPath();
		if (!$this->isDynamic()) {
			return $path;
		}

		return new RoutePathParser($path, $this->router)->buildPath($context, $params);
	}

	/**
	 * Builds the host of this route with given parameters values: null when the route declares no
	 * host.
	 *
	 * @throws InvalidArgumentException when a required parameter has no value, or the values build a
	 *                                  host this route does not match (a value outside its declared
	 *                                  pattern, or one that would change the URI's authority)
	 */
	public function buildHost(Context $context, array $params = []): ?string
	{
		$template = $this->options->getHost();

		if (null === $template) {
			return null;
		}

		$host = new RoutePathParser($template, $this->router, self::DEFAULT_HOST_PARAM_PATTERN)
			->buildPath($context, $params);

		if (null === $this->matchHost($host) || !self::isHostName($host)) {
			throw new InvalidArgumentException(\sprintf(
				'The host "%s" built for the route "%s" is not one it matches: %s',
				$host,
				$this->getName(),
				$template
			));
		}

		return $host;
	}

	/**
	 * Returns the host template this route matches, as declared on it or on a parent: null when the
	 * route matches any host.
	 */
	public function getHost(): ?string
	{
		return $this->options->getHost();
	}

	/**
	 * Returns the regular expression a request host must match, run case-insensitively, like
	 * {@see getRegExp()} otherwise: null when the route declares no host.
	 */
	public function getHostRegExp(): ?string
	{
		$this->ensureParsed();

		return $this->host_regexp;
	}

	/**
	 * Returns the parameters found after parsing the route host if any.
	 *
	 * @return list<string>
	 */
	public function getHostParams(): array
	{
		$this->ensureParsed();

		return \array_keys($this->host_params_found);
	}

	/**
	 * Checks if a parameter of the host is required: false for one in an optional part or not in
	 * the host.
	 */
	public function isHostParamRequired(string $name): bool
	{
		$this->ensureParsed();

		return $this->host_params_found[$name] ?? false;
	}

	/**
	 * Matches a request host: the parameters it gives, by name, an empty list when the route declares
	 * no host and so matches any; null when the route declares a host this one is not, or no host is
	 * given.
	 *
	 * @return null|array<string, string>
	 */
	public function matchHost(?string $host): ?array
	{
		$this->ensureParsed();

		if (null === $this->host_regexp) {
			return [];
		}

		$matches = [];

		if (null === $host || 1 !== \preg_match($this->host_regexp, $host, $matches)) {
			return null;
		}

		return \array_filter($matches, \is_string(...), \ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Returns the route path as defined.
	 */
	public function getPath(bool $full = true): string
	{
		return $this->options->getPath($full);
	}

	/**
	 * Returns the route handler callable.
	 */
	public function getHandler(): callable
	{
		return $this->handler;
	}

	/**
	 * Returns the route options.
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
	 */
	public function key(): string
	{
		$methods = $this->methods;
		\sort($methods);

		return $this->getName() . '|' . \implode(',', $methods) . '|' . $this->getPath();
	}

	/**
	 * Checks if this route accept a given HTTP request method.
	 */
	public function accept(string $method): bool
	{
		return \in_array(\strtoupper($method), $this->methods, true);
	}

	/**
	 * Returns parser result.
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
	 * Checks if a given path, on a given host, matches this route: the parameters of the host join
	 * those of the path.
	 *
	 * A route declaring a host matches no request whose host is not given.
	 */
	public function is(string $path, ?array &$params = [], ?string $host = null): bool
	{
		$this->ensureParsed();

		$matches = [];

		if ($this->isDynamic()) {
			if (1 !== \preg_match($this->getRegExp(), $path, $matches)) {
				return false;
			}
		} elseif ($path !== $this->options->getPath()) {
			return false;
		}

		$host_params = $this->matchHost($host);

		if (null === $host_params) {
			return false;
		}

		$params = $matches + $host_params;

		return true;
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

			$this->parseHost();

			$this->parsed = true;
		}
	}

	/**
	 * Parses the host template, once the path is: a host parameter may not take the name of a path
	 * one, since both are read from the same list of parameters.
	 */
	private function parseHost(): void
	{
		$this->host_regexp       = null;
		$this->host_params_found = [];

		$host = $this->options->getHost();

		if (null === $host) {
			return;
		}

		$found  = [];
		$parser = new RoutePathParser($host, $this->router, self::DEFAULT_HOST_PARAM_PATTERN);
		$result = $parser->parse($this->getDeclaredParams(), $found);

		foreach (\array_keys($found) as $name) {
			if (\array_key_exists($name, $this->params_found)) {
				throw new InvalidArgumentException(\sprintf(
					'Route parameter name "%s" is taken by both the host "%s" and the path "%s".',
					$name,
					$host,
					$this->options->getPath()
				));
			}
		}

		$this->host_regexp       = self::REG_DELIMITER . '^' . $result . '$' . self::REG_DELIMITER . 'uDi';
		$this->host_params_found = \array_map(\boolval(...), $found);
	}

	/**
	 * Checks that a built host is a host name and nothing more: a value carrying `/`, `@`, `:`, `?`
	 * or `#` would move the URI's authority to another host.
	 */
	private static function isHostName(string $host): bool
	{
		try {
			return Uri::createFromString('http://' . $host)->getHost() === \strtolower($host);
		} catch (InvalidArgumentException) {
			return false;
		}
	}
}
