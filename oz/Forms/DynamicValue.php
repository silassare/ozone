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

use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class DynamicValue.
 *
 * A server-side value resolved from {@see FormData} at validation time.
 *
 * When constructed with only a runtime factory, the value is opaque to the
 * client: `toArray()` returns `['$dynamic' => true, '$preview' => null]`.
 * The client must send a server round-trip to evaluate rules that depend on it.
 *
 * When a preview factory is also provided, a preview can be produced at
 * form-discovery time (inside a {@see self::withDiscovery()} callback).
 * `toArray()` then returns `['$dynamic' => true, '$preview' => ['value' => <T>]]`
 * so the client can evaluate the rule locally without a round-trip.
 *
 * The `$preview` shape is intentionally wrapped:
 *  - `$preview: null`           -> no preview available
 *  - `$preview: ['value': null]` -> preview IS available and its value is null
 *
 * @template T The type of the dynamic value.
 */
final class DynamicValue implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	private static bool $s_discovery_mode = false;

	/**
	 * @var callable(FormData):T
	 */
	private $factory;

	/**
	 * @var null|callable():T
	 */
	private $preview_factory;

	/**
	 * DynamicValue constructor.
	 *
	 * @param callable(FormData):T $factory         runtime factory; receives cleaned FormData
	 * @param null|callable():T    $preview_factory Optional preview factory; receives no arguments
	 *                                              because discovery has no user-submitted context.
	 *                                              When provided, {@see self::isClientResolvable()}
	 *                                              returns true and the preview value is embedded in
	 *                                              the serialized form during discovery.
	 */
	public function __construct(callable $factory, ?callable $preview_factory = null)
	{
		$this->factory         = $factory;
		$this->preview_factory = $preview_factory;
	}

	public function __destruct()
	{
		unset($this->factory, $this->preview_factory);
	}

	/**
	 * Runs $cb with discovery mode active for all DynamicValue instances.
	 *
	 * Inside $cb, any `DynamicValue` that has a preview factory will return
	 * `['$dynamic' => true, '$preview' => ['value' => <T>]]` from `toArray()`.
	 *
	 * Resets discovery mode in a finally block so it is always cleared,
	 * even when $cb throws.
	 *
	 * @template R
	 *
	 * @param callable():R $cb
	 *
	 * @return R
	 */
	public static function withDiscovery(callable $cb): mixed
	{
		self::$s_discovery_mode = true;

		try {
			return $cb();
		} finally {
			self::$s_discovery_mode = false;
		}
	}

	/**
	 * Returns true when code is executing inside a {@see self::withDiscovery()} callback.
	 */
	public static function isDiscovering(): bool
	{
		return self::$s_discovery_mode;
	}

	/**
	 * Gets the runtime value, evaluated against cleaned form data.
	 *
	 * @param FormData $fd
	 *
	 * @return T
	 */
	public function getValue(FormData $fd): mixed
	{
		return \call_user_func($this->factory, $fd);
	}

	/**
	 * Whether this value can be resolved by the client.
	 *
	 * Returns true when a preview factory was provided: the preview data is
	 * embedded in the serialized form at discovery time, so the client does not
	 * need a server round-trip to evaluate rules that use this value.
	 */
	public function isClientResolvable(): bool
	{
		return null !== $this->preview_factory;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Outside discovery mode always returns `['$dynamic' => true, '$preview' => null]`.
	 *
	 * Inside a {@see self::withDiscovery()} callback, when a preview factory was
	 * supplied, returns `['$dynamic' => true, '$preview' => ['value' => <T>]]`.
	 *
	 * @return array{
	 *  '$dynamic': true,
	 *  '$preview': null|array{value: T}
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$preview = null;

		if (self::$s_discovery_mode && null !== $this->preview_factory) {
			$preview = ['value' => \call_user_func($this->preview_factory)];
		}

		return [
			'$dynamic' => true,
			'$preview' => $preview,
		];
	}
}
