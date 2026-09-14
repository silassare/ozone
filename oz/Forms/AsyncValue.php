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
 * Class AsyncValue.
 *
 * A server-side value resolved at validation time.
 *
 * When constructed with only a runtime factory, the value is opaque to the
 * client: `toArray()` returns `['$async' => true, '$preview' => null]`.
 * The client must send a server round-trip to evaluate rules that depend on it.
 *
 * When a preview factory is also provided, a preview can be produced at
 * form-discovery time (inside a {@see self::withPreview()} callback).
 * `toArray()` then returns `['$async' => true, '$preview' => ['value' => <T>]]`
 * so the client can evaluate the rule locally without a round-trip.
 *
 * The `$preview` shape is intentionally wrapped:
 *  - `$preview: null`           -> no preview available
 *  - `$preview: ['value': null]` -> preview IS available and its value is null
 *
 * @template T The type of the dynamic value.
 */
final class AsyncValue implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	private static int $s_wp_counter = 0;

	/**
	 * @var callable(FormValidationContext):T
	 */
	private $factory;

	/**
	 * @var null|callable():T
	 */
	private $preview_factory;

	/**
	 * AsyncValue constructor.
	 *
	 * @param callable(FormValidationContext):T $factory         runtime factory; receives the validation context
	 * @param null|callable():T                 $preview_factory Optional preview factory; receives no arguments
	 *                                                           because discovery has no user-submitted context.
	 *                                                           When provided, {@see self::isClientResolvable()}
	 *                                                           returns true and the preview value is embedded in
	 *                                                           the serialized form during discovery.
	 */
	public function __construct(callable $factory, ?callable $preview_factory = null)
	{
		$this->factory         = $factory;
		$this->preview_factory = $preview_factory;
	}

	/**
	 * Runs $cb with discovery mode active for all {@see AsyncValue} instances.
	 *
	 * Inside $cb, any `AsyncValue` that has a preview factory will return
	 * `['$async' => true, '$preview' => ['value' => <T>]]` from `toArray()`.
	 *
	 * @template R
	 *
	 * @param callable():R $cb
	 *
	 * @return R
	 */
	public static function withPreview(callable $cb): mixed
	{
		++self::$s_wp_counter;

		try {
			return $cb();
		} finally {
			--self::$s_wp_counter;
		}
	}

	/**
	 * Gets the runtime value, evaluated against the current validation context.
	 *
	 * The factory receives the full {@see FormValidationContext} so it can read
	 * either the raw payload or the cleaned data, whichever it needs.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return T
	 */
	public function getValue(FormValidationContext $ctx): mixed
	{
		return \call_user_func($this->factory, $ctx);
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
	 * Outside discovery mode always returns `['$async' => true, '$preview' => null]`.
	 *
	 * Inside a {@see self::withPreview()} callback, when a preview factory was
	 * supplied, returns `['$async' => true, '$preview' => ['value' => <T>]]`.
	 *
	 * @return array{
	 *  '$async': true,
	 *  '$preview': null|array{value: T}
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$preview = null;

		$run_preview = self::$s_wp_counter > 0;

		if ($run_preview && null !== $this->preview_factory) {
			$preview = ['value' => \call_user_func($this->preview_factory)];
		}

		return [
			'$async'   => true,
			'$preview' => $preview,
		];
	}
}
