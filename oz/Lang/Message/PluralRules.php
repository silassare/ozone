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

namespace OZONE\Core\Lang\Message;

use MessageFormatter;

/**
 * The CLDR plural category of a number in a language (`one`, `few`, ...), from ext-intl.
 *
 * Without ext-intl there are no rules: a category never matches, and a text chooses with `=N`, the
 * comparisons and `other` alone, which the browser reads the same way.
 */
final class PluralRules
{
	private const CARDINAL = '{0, plural, zero {zero} one {one} two {two} few {few} many {many} other {other}}';

	private const ORDINAL = '{0, selectordinal, zero {zero} one {one} two {two} few {few} many {many} other {other}}';

	/** @var array<string, null|MessageFormatter> */
	private static array $formatters = [];

	/**
	 * Whether the rules are there: ext-intl is loaded.
	 */
	public static function available(): bool
	{
		return \extension_loaded('intl');
	}

	/**
	 * The category of a number, or null when there are no rules for it.
	 *
	 * @param bool $ordinal the ordinal rules (`selectordinal`: 1st, 2nd) rather than the cardinal ones
	 */
	public static function category(float|int $n, string $lang, bool $ordinal): ?string
	{
		if (!self::available()) {
			return null;
		}

		$id = $lang . ($ordinal ? ':o' : ':c');

		if (!\array_key_exists($id, self::$formatters)) {
			self::$formatters[$id] = MessageFormatter::create($lang, $ordinal ? self::ORDINAL : self::CARDINAL);
		}

		$category = self::$formatters[$id]?->format([$n]);

		return \is_string($category) ? $category : null;
	}
}
