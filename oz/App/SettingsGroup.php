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

namespace OZONE\Core\App;

use Override;
use PHPUtils\DotPath;
use PHPUtils\Store\Store;

/**
 * Class SettingsGroup.
 *
 * A custom store class for settings group that applies the merge strategy when merging data.
 *
 * ## Key format — IMPORTANT
 *
 * Settings keys follow {@see DotPath} conventions with one extension:
 *
 * - **Plain keys** (`[a-zA-Z0-9_]` only): single segment, e.g. `OZ_DB_HOST`.
 * - **Deep keys** (segments separated by `.`, all segments plain): full DotPath traversal is
 *   performed, e.g. `group.max_members_count` navigates `$data['group']['max_members_count']`.
 *   Consuming projects rely on this for structured settings.
 *   **Do NOT remove or bypass this traversal.**
 * - **Special-char keys** (containing characters outside `[a-zA-Z0-9_.]`, e.g. hyphens `-`,
 *   backslashes `\`, or colons `:`): a single literal segment that cannot be expressed as a
 *   plain DotPath token. `normalizeKey()` wraps these in bracket notation `['...']` so DotPath
 *   treats them as a single opaque key. Common examples: FQCNs in `oz.routes.*`, provider
 *   slugs like `auth:provider:email` in `oz.auth.providers`, service keys like `test-wizard`.
 *
 * All `has()`, `get()`, `set()`, and `remove()` calls run through `normalizeKey()` to produce
 * a DotPath-safe key, then delegate to the parent {@see Store} which uses
 * {@see StoreTrait::parentOf()} for traversal. Single-segment special-char keys are wrapped in
 * bracket notation by `normalizeKey()` and resolved correctly by `parentOf()`.
 *
 * @extends Store<array>
 *
 * @internal
 */
final class SettingsGroup extends Store
{
	/**
	 * SettingsGroup constructor.
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function merge(iterable $data): static
	{
		$bundle = Settings::applyMergeStrategy($this->toArray(), $data);

		return parent::setData($bundle);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function set(string $key, mixed $value): static
	{
		return parent::set(self::normalizeKey($key), $value);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function remove(string $key): static
	{
		return parent::remove(self::normalizeKey($key));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function has(string $key): bool
	{
		return parent::has(self::normalizeKey($key));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function get(string $key, mixed $default = null): mixed
	{
		return parent::get(self::normalizeKey($key), $default);
	}

	/**
	 * Normalizes a settings key for {@see DotPath}.
	 *
	 * Plain DotPath segments only allow `[a-zA-Z0-9_]`. Dots are used as
	 * segment separators, so a key like `foo.bar` intentionally traverses
	 * nested levels. Any key that contains characters outside `[a-zA-Z0-9_.]`
	 * (e.g. a FQCN like `Foo\Bar\Baz` containing backslashes) cannot be
	 * expressed as plain DotPath segments and is therefore wrapped in
	 * bracket-quoted notation so DotPath treats it as a single literal key.
	 *
	 * Keys that already contain `[` are passed through unchanged.
	 *
	 * @param string $key the raw settings key
	 *
	 * @return string the DotPath-safe key
	 */
	private static function normalizeKey(string $key): string
	{
		if (\str_contains($key, '[') || \preg_match('/^[a-zA-Z0-9_.]+$/', $key)) {
			return $key;
		}

		return "['" . \str_replace("'", "\\'", $key) . "']";
	}
}
