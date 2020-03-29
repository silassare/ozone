<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Router;

use InvalidArgumentException;

\defined('OZ_SELF_SECURITY_CHECK') || die;

class Route
{
	/**
	 * @var string
	 */
	private $route_path = '';

	/**
	 * @var string
	 */
	private $parsed = '';

	/**
	 * @var bool
	 */
	private $is_dynamic;

	/**
	 * @var array
	 */
	private $placeholders = [];

	/**
	 * @var array
	 */
	private $methods = [];

	/**
	 * @var array
	 */
	private $options = [];

	/**
	 * @var callable
	 */
	private $callable;

	/**
	 * Route constructor.
	 *
	 * @param array    $methods
	 * @param string   $route_path
	 * @param callable $callable
	 * @param array    $options
	 */
	public function __construct(array $methods, $route_path, callable $callable, array $options = [])
	{
		$this->route_path = $route_path;
		$this->methods    = $methods;
		$this->callable   = $callable;
		$this->options    = $options;

		if ($this->isDynamic()) {
			$placeholders       = [];
			$this->parsed       = self::parse($route_path, $options, $placeholders);
			$this->placeholders = \array_keys($placeholders);
		} else {
			$this->parsed = $route_path;
		}
	}

	/**
	 * Checks if this route is dynamic.
	 *
	 * @return bool
	 */
	public function isDynamic()
	{
		if (!isset($this->is_dynamic)) {
			$this->is_dynamic = (
				false !== \strpos($this->route_path, '{') || false !== \strpos($this->route_path, '[')
			);
		}

		return $this->is_dynamic;
	}

	/**
	 * Builds the route with given placeholders value.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function toPath(array $args)
	{
		if (!$this->isDynamic()) {
			return $this->route_path;
		}

		return self::pathBuilder($this->route_path, $this->options, $args);
	}

	/**
	 * Returns the route path as defined.
	 *
	 * @return string
	 */
	public function getRoutePath()
	{
		return $this->route_path;
	}

	/**
	 * Returns the route callable.
	 *
	 * @return callable
	 */
	public function getCallable()
	{
		return $this->callable;
	}

	/**
	 * Returns the route options.
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * Returns the route option with the given name.
	 *
	 * @param string $name
	 * @param mixed  $def
	 *
	 * @return null|mixed
	 */
	public function getOption($name, $def = null)
	{
		if (isset($this->options[$name])) {
			return $this->options[$name];
		}

		return $def;
	}

	/**
	 * Returns this route allowed HTTP request methods.
	 *
	 * @return array
	 */
	public function getMethods()
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
	public function accept($method)
	{
		return \in_array(\strtoupper($method), $this->methods);
	}

	/**
	 * Returns parsed route.
	 *
	 * @return string
	 */
	public function getParsed()
	{
		return $this->parsed;
	}

	/**
	 * Returns placeholders.
	 *
	 * @return array
	 */
	public function getPlaceholders()
	{
		return $this->placeholders;
	}

	/**
	 * Checks if a given path matches this route.
	 *
	 * @param string     $path
	 * @param null|array $args
	 *
	 * @return bool
	 */
	public function is($path, array &$args = [])
	{
		if (!$this->isDynamic()) {
			return $path === $this->route_path;
		}

		$regexp  = '~^' . $this->parsed . '$~';
		$matches = [];

		$result = (1 === \preg_match($regexp, $path, $matches));

		if ($result) {
			foreach ($this->placeholders as $name) {
				$args[$name] = isset($matches[$name]) ? $matches[$name] : null;
			}
		}

		return $result;
	}

	/**
	 * Dynamic route parser.
	 *
	 * @param string      $route
	 * @param array       $options
	 * @param array       $placeholders
	 * @param null|string $original_route
	 *
	 * @return string
	 */
	private static function parse($route, array $options = [], &$placeholders = [], $original_route = null)
	{
		$len            = \strlen($route);
		$original_route = null === $original_route ? $route : $original_route;
		$reg            = '';
		$pos            = 0;

		while ($pos < $len) {
			$c = $route[$pos];

			if ($c === '{') {
				$name = self::searchUntilCloseTag('{', '}', $route, $pos + 1, $pos, false);

				if (!\strlen($name)) {
					throw new InvalidArgumentException(\sprintf(
						'Placeholder should not be empty: %s',
						$original_route
					));
				}

				if (!\preg_match('~^[a-z_][a-z0-9_]*$~', $name)) {
					throw new InvalidArgumentException(\sprintf(
						'Placeholder contains invalid characters: %s -> %s',
						$name,
						$original_route
					));
				}

				if (\array_key_exists($name, $placeholders)) {
					throw new InvalidArgumentException(\sprintf(
						'Placeholder name duplicated: %s -> %s',
						$name,
						$original_route
					));
				}

				$placeholder_reg = '[^/]+';

				if (isset($options[$name])) {
					$placeholder_reg = $options[$name];

					if (self::hasCatchingGroupOrIsInvalid($placeholder_reg)) {
						throw new InvalidArgumentException(\sprintf(
							'Placeholder regexp should not contains invalid char or catching group. Keep it simple: %s -> %s',
							$name,
							$placeholder_reg
						));
					}
				}

				$reg .= "(?<$name>$placeholder_reg)";
				$required            = ($route === $original_route ? 1 : 0);
				$placeholders[$name] = $required;
			} elseif ($c === '[') {
				$optional = self::searchUntilCloseTag('[', ']', $route, $pos + 1, $pos, true);

				if (!\strlen($optional)) {
					throw new InvalidArgumentException(\sprintf(
						'Optional part should not be empty: %s',
						$original_route
					));
				}

				$optional_reg = self::parse($optional, $options, $placeholders, $original_route);
				$reg .= '(?:' . $optional_reg . ')?';
			} else {
				$reg .= \preg_quote($c, '~');
			}

			$pos++;
		}

		return $reg;
	}

	/**
	 * Builds dynamic path.
	 *
	 * @param string      $route
	 * @param array       $options
	 * @param array       $args
	 * @param null|string $original_route
	 *
	 * @return string
	 */
	private static function pathBuilder($route, array $options = [], array $args = [], $original_route = null)
	{
		$len            = \strlen($route);
		$original_route = null === $original_route ? $route : $original_route;
		$path           = '';
		$pos            = 0;

		while ($pos < $len) {
			$char = $route[$pos];

			if ($char === '{') {
				$name = self::searchUntilCloseTag('{', '}', $route, $pos + 1, $pos, false);

				$required = ($route === $original_route ? 1 : 0);

				if (isset($args[$name])) {
					$path .= (string) $args[$name];
				} elseif ($required) {
					throw new InvalidArgumentException(\sprintf(
						'Missing required placeholder value: %s',
						$name
					));
				} else {
					// we are in optional part and
					// there is missing placeholder value
					// so we ignore the optional part
					return '';
				}
			} elseif ($char === '[') {
				$optional = self::searchUntilCloseTag('[', ']', $route, $pos + 1, $pos, true);

				if (!\strlen($optional)) {
					throw new InvalidArgumentException(\sprintf(
						'Optional part should not be empty: %s',
						$original_route
					));
				}

				$path .= self::pathBuilder($optional, $options, $args, $original_route);
			} else {
				$path .= $char;
			}

			$pos++;
		}

		return $path;
	}

	/**
	 * Search for close tag.
	 *
	 * @param string $open
	 * @param string $close
	 * @param string $string
	 * @param int    $from
	 * @param int    $found_at
	 * @param bool   $go_deeply
	 *
	 * @return string
	 */
	private static function searchUntilCloseTag($open, $close, $string, $from, &$found_at, $go_deeply = false)
	{
		$found  = false;
		$ret    = '';
		$cursor = $from;
		$len    = \strlen($string);
		$stack  = 0;

		while ($cursor < $len) {
			$char = $string[$cursor];

			if ($char === $open) {
				if ($go_deeply) {
					$stack++;
				} else {
					throw new InvalidArgumentException(\sprintf(
						'The open tag %s at index %s was not closed before opening new tag at index %s',
						$open,
						$from - 1,
						$cursor
					));
				}
			}

			if ($char === $close) {
				if ($stack) {
					$stack--;
				} else {
					$found    = true;
					$found_at = $cursor;

					break;
				}
			}

			$ret .= $char;
			$cursor++;
		}

		if ($found === false) {
			throw new InvalidArgumentException(\sprintf(
				'Unexpected end of string missing close tag %s at the end of %s',
				$close,
				$string
			));
		}

		return $ret;
	}

	/**
	 * Checks for invalid characters in regexp.
	 *
	 * @param string $regexp
	 *
	 * @return bool
	 */
	private static function hasCatchingGroupOrIsInvalid($regexp)
	{
		// we want to know if the regular expression has catching group
		// or is complex
		// we are dealing with path so let keep it simple

		return
			false !== \strpos($regexp, '(')
			|| false !== \strpos($regexp, ')')
			|| false !== \strpos($regexp, '?')
			|| false !== \strpos($regexp, ':')
			|| 0 === \strpos($regexp, '^') /* starts with ^ */
			|| \strlen($regexp) - 1 === \strpos($regexp, '$'); /* ends with $ */
	}
}
