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

namespace OZONE\Core\Stores;

/**
 * Class StoreEntries.
 *
 * One branch of a store, holding one entry per key: the uploads a session has in flight, the links
 * it has been handed, the captchas it has been asked.
 *
 * What a handle buys over writing `'<branch>.' . $key` into the store:
 *
 *  - an entry is written and removed one at a time, so a request can never take the branch -- and
 *    another request's entries with it -- by writing the key it meant;
 *  - the key is a **member's name** and says so, whatever it looks like. A key of all digits down a
 *    dot path is ambiguous (PHP reads `$a['12']` and `$a[12]` as one slot, JSON does not), and the
 *    engines write a member and a position differently; here there is nothing to read off the
 *    values, because the handle knows the branch holds entries;
 *  - a key holding a dot or a bracket is still one key, where a dot path would read it as several.
 */
final readonly class StoreEntries
{
	/**
	 * StoreEntries constructor.
	 *
	 * @param RecordingStore   $store    the store the branch lives in
	 * @param non-empty-string $branch   the branch, as a dot path of its own
	 * @param list<string>     $segments the branch's keys, which an entry's name is appended to
	 */
	public function __construct(
		private RecordingStore $store,
		private string $branch,
		private array $segments
	) {}

	/**
	 * The entry under the given name, or null when the branch does not hold it.
	 */
	public function get(string $name): mixed
	{
		$entries = $this->all();

		return $entries[$name] ?? null;
	}

	/**
	 * Whether the branch holds an entry under the given name.
	 */
	public function has(string $name): bool
	{
		return \array_key_exists($name, $this->all());
	}

	/**
	 * Writes one entry, leaving every other entry of the branch where it is.
	 */
	public function set(string $name, mixed $value): static
	{
		$this->store->writeAt([...$this->segments, $name], $value);

		return $this;
	}

	/**
	 * Removes one entry, leaving every other entry of the branch where it is.
	 */
	public function remove(string $name): static
	{
		$this->store->eraseAt([...$this->segments, $name]);

		return $this;
	}

	/**
	 * Every entry of the branch, keyed by name.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array
	{
		$entries = $this->store->get($this->branch);

		return \is_array($entries) ? $entries : [];
	}

	/**
	 * The names the branch holds.
	 *
	 * @return list<string>
	 */
	public function names(): array
	{
		return \array_map('strval', \array_keys($this->all()));
	}
}
