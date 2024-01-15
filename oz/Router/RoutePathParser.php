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

/**
 * Class RoutePathParser.
 */
final class RoutePathParser
{
	private int $cursor = 0;
	private int $len;

	/**
	 * RoutePathParser constructor.
	 *
	 * @param string $route_path
	 * @param Router $router
	 */
	public function __construct(
		private readonly string $route_path,
		private readonly Router $router
	) {
		$this->len = \strlen($route_path);
	}

	/**
	 * Checks if a parameter is valid.
	 *
	 * @param $str
	 *
	 * @return bool
	 */
	public static function isValidParameter($str): bool
	{
		return 1 === \preg_match(Route::ROUTE_PARAM_REG, $str);
	}

	/**
	 * Builds dynamic path.
	 *
	 * @param Context     $context
	 * @param array       $params
	 * @param null|string $original_route_path
	 *
	 * @return string
	 */
	public function buildPath(
		Context $context,
		array $params = [],
		?string $original_route_path = null
	): string {
		$this->reset();
		$original_route_path = $original_route_path ?? $this->route_path;
		$path                = '';

		while ($this->cursor < $this->len) {
			$c = $this->route_path[$this->cursor];

			if ('{' === $c || ':' === $c) {
				$this->move();
				if ('{' === $c) {
					$name = $this->searchUntilCloseTag('{', '}', false);
					$this->move();
				} else {
					$name = $this->searchWhile([self::class, 'isValidParameter']);
				}

				$required = ($this->route_path === $original_route_path ? 1 : 0);

				if (isset($params[$name])) {
					$path .= $params[$name];
				} else {
					$value = $this->router->getGlobalParamValue($context, $name);

					if (null !== $value) {
						$path .= $value;
					} elseif ($required) {
						throw new InvalidArgumentException(\sprintf(
							'Missing required parameter value: %s',
							$name
						));
					} else {
						// we are in optional part and
						// there is missing param value
						// so we ignore the optional part
						return '';
					}
				}
			} elseif ('[' === $c) {
				$this->move();
				$optional = $this->searchUntilCloseTag('[', ']', true);
				$this->move();

				if ('' === $optional) {
					throw new InvalidArgumentException(\sprintf(
						'Optional part should not be empty: %s',
						$original_route_path
					));
				}

				$sub_parser = new self($optional, $this->router);
				$path .= $sub_parser->buildPath($context, $params, $original_route_path);
			} else {
				$path .= $c;
				$this->move();
			}
		}

		return $path;
	}

	/**
	 * Dynamic route parser.
	 *
	 * @param string      $route_path
	 * @param array       $declared_params
	 * @param array       &$params_found
	 * @param null|string $original_route
	 *
	 * @return string
	 */
	public function parse(
		string $route_path,
		array $declared_params = [],
		array &$params_found = [],
		?string $original_route = null
	): string {
		$this->reset();
		$original_route = $original_route ?? $route_path;
		$pattern        = '';

		while ($this->cursor < $this->len) {
			$c = $route_path[$this->cursor];

			if ('{' === $c || ':' === $c) {
				$this->move();
				if ('{' === $c) {
					$name = $this->searchUntilCloseTag('{', '}', false);
					$this->move();
				} else {
					$name = $this->searchWhile([self::class, 'isValidParameter']);
				}

				if ('' === $name) {
					throw new InvalidArgumentException(\sprintf(
						'Route parameter name should not be empty: %s',
						$original_route
					));
				}

				if (!\preg_match(Route::ROUTE_PARAM_REG, $name)) {
					throw new InvalidArgumentException(\sprintf(
						'Route parameter name contains invalid characters: %s -> %s',
						$name,
						$original_route
					));
				}

				if (\array_key_exists($name, $params_found)) {
					throw new InvalidArgumentException(\sprintf(
						'Route parameter name duplicated: %s -> %s',
						$name,
						$original_route
					));
				}

				$param_pattern = $declared_params[$name] ?? Route::DEFAULT_PARAM_PATTERN;

				// use (?P<name> insteadof (?<name> for backward compatibility
				$pattern .= "(?P<{$name}>{$param_pattern})";
				$required            = ($route_path === $original_route ? 1 : 0);
				$params_found[$name] = $required;
			} elseif ('[' === $c) {
				$this->move();
				$optional = $this->searchUntilCloseTag('[', ']', true);
				$this->move();
				if ('' === $optional) {
					throw new InvalidArgumentException(\sprintf(
						'Optional part should not be empty: %s',
						$original_route
					));
				}

				$sub_parser   = new self($optional, $this->router);
				$optional_reg = $sub_parser->parse($optional, $declared_params, $params_found, $original_route);
				$pattern .= '(?:' . $optional_reg . ')?';
			} else {
				$pattern .= \preg_quote($c, Route::REG_DELIMITER);
				$this->move();
			}
		}

		return $pattern;
	}

	/**
	 * Move the cursor.
	 */
	private function move(): void
	{
		++$this->cursor;
	}

	/**
	 * Resets the cursor.
	 */
	private function reset(): void
	{
		$this->cursor = 0;
	}

	/**
	 * Search for close tag.
	 *
	 * @param string $open      open tag
	 * @param string $close     close tag
	 * @param bool   $go_deeply go deeply
	 *
	 * @return string
	 */
	private function searchUntilCloseTag(
		string $open,
		string $close,
		bool $go_deeply
	): string {
		$found       = false;
		$accumulator = '';
		$from        = $this->cursor;
		$stack       = 0;

		while ($this->cursor < $this->len) {
			$c = $this->route_path[$this->cursor];

			if ($c === $open) {
				if ($go_deeply) {
					++$stack;
				} else {
					throw new InvalidArgumentException(\sprintf(
						'The open tag "%s" at index %s was not closed before opening new tag at index %s',
						$open,
						$from,
						$this->cursor
					));
				}
			}

			if ($c === $close) {
				if ($stack) {
					--$stack;
				} else {
					$found = true;

					break;
				}
			}

			$accumulator .= $c;
			++$this->cursor;
		}

		if (!$found) {
			throw new InvalidArgumentException(\sprintf(
				'Unexpected end of string missing close tag "%s" at the end of "%s"',
				$close,
				$this->route_path
			));
		}

		return $accumulator;
	}

	/**
	 * Search while a given callable return true.
	 *
	 * @param callable $predicate the predicate function
	 *
	 * @return string
	 */
	private function searchWhile(callable $predicate): string
	{
		$accumulator = '';

		while ($this->cursor < $this->len) {
			$char = $this->route_path[$this->cursor];

			if (!$predicate($accumulator . $char)) {
				break;
			}

			$accumulator .= $char;
			++$this->cursor;
		}

		return $accumulator;
	}
}
