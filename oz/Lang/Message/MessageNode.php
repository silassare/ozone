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

/**
 * One piece of a parsed message.
 *
 * - `text`: `value` is written as it is.
 * - `hash`: the `#` of a plural branch, the number.
 * - `include`: `key` is another text of the catalog, filled with the same data (`{{KEY}}`).
 * - `argument`: the value at `path`, through `filters` (`{a.b | f: 1, "x"}`).
 * - `choice`: `kind` (`plural`, `selectordinal`, `select`) over the value at `path`, and its
 *   `branches`, each a selector and the nodes of its text.
 */
final class MessageNode
{
	/**
	 * @param 'argument'|'choice'|'hash'|'include'|'text'                                       $type
	 * @param list<string>                                                                      $path
	 * @param list<array{name: string, args: list<float|int|string>}>                           $filters
	 * @param list<array{selector: string, nodes: list<MessageNode>}>                           $branches
	 */
	private function __construct(
		public readonly string $type,
		public readonly string $value = '',
		public readonly array $path = [],
		public readonly array $filters = [],
		public readonly string $kind = '',
		public readonly array $branches = [],
	) {}

	public static function text(string $value): self
	{
		return new self('text', $value);
	}

	public static function hash(): self
	{
		return new self('hash');
	}

	public static function include(string $key): self
	{
		return new self('include', $key);
	}

	/**
	 * @param list<string>                                             $path
	 * @param list<array{name: string, args: list<float|int|string>}> $filters
	 */
	public static function argument(array $path, array $filters): self
	{
		return new self('argument', '', $path, $filters);
	}

	/**
	 * @param list<string>                                             $path
	 * @param 'plural'|'select'|'selectordinal'                        $kind
	 * @param list<array{selector: string, nodes: list<MessageNode>}> $branches
	 */
	public static function choice(array $path, string $kind, array $branches): self
	{
		return new self('choice', '', $path, [], $kind, $branches);
	}
}
