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

namespace OZONE\Core\Forms;

/**
 * Registry that maps named keys to {@see Form} instances.
 *
 * Forms register themselves here when {@see Form::key()} is called.
 * The registry is process-scoped — it is reset on each PHP request.
 * It is primarily used for form discovery: the router can serve the
 * serialized form definition for a named form without executing the
 * route handler.
 *
 * @internal
 */
final class FormRegistry
{
	/**
	 * @var array<string, Form>
	 */
	private static array $registry = [];

	/**
	 * Register a form under the given key.
	 *
	 * Overwrites any previously registered form with the same key.
	 *
	 * @param string $key  The form key (must be non-empty)
	 * @param Form   $form The form to register
	 */
	public static function register(string $key, Form $form): void
	{
		self::$registry[$key] = $form;
	}

	/**
	 * Remove a previously registered form by its key.
	 *
	 * No-op when the key is not registered.
	 *
	 * @param string $key The form key to remove
	 */
	public static function unregister(string $key): void
	{
		unset(self::$registry[$key]);
	}

	/**
	 * Retrieve a previously registered form by its key.
	 *
	 * @param string $key The form key
	 *
	 * @return null|Form The registered form, or null if not found
	 */
	public static function get(string $key): ?Form
	{
		return self::$registry[$key] ?? null;
	}

	/**
	 * Returns all registered forms keyed by their registered name.
	 *
	 * @return array<string, Form>
	 */
	public static function all(): array
	{
		return self::$registry;
	}

	/**
	 * Clears the entire registry.
	 *
	 * Intended for use in tests to ensure isolation between test cases.
	 */
	public static function clear(): void
	{
		self::$registry = [];
	}
}
