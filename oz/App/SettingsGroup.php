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
 * Class Settings.
 *
 * A custom store class for settings group that applies the merge strategy when merging data.
 *
 * Keys that contain characters invalid for a DotPath plain segment (anything outside `[a-zA-Z0-9_.]`)
 * are automatically wrapped in bracket notation so {@see DotPath} treats them as literal
 * strings. This is the common case for FQCNs used as keys in `oz.routes.*` groups.
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
