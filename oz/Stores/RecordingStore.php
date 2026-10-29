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

use Gobl\DBAL\Types\Utils\JsonPatch;
use JsonSerializable;
use Override;
use PHPUtils\DotPath;
use PHPUtils\Store\Store;

/**
 * Class RecordingStore.
 *
 * A store that keeps the writes made to it as the paths they changed, for a document several
 * requests hold at once and each own part of.
 *
 * Why the writes are recorded rather than worked out at the end: a patch built by comparing the
 * document a request started with against the one it ends with has to decide what the writer meant,
 * and the two documents do not hold the answer. Whether a key that is gone was one entry of a map
 * or the branch itself, whether an array of numbers is a list or a map keyed by id, whether a branch
 * this request is the first to fill is a branch at all -- every reading of that is a guess, and each
 * guess that came out wrong wrote a branch where one key was meant and took a concurrent write with
 * it. A write that says what it is cannot be read wrong.
 *
 * A write that goes around this API -- the data reached through {@see self::getData()} and changed
 * in place -- is not recorded, and is not lost either: {@see self::recordedPatch()} answers null
 * when the paths no longer account for the document, and the caller works the change out the old way
 * knowing that it had to.
 *
 * @extends Store<array>
 */
abstract class RecordingStore extends Store
{
	/**
	 * The writes, as the paths they changed, over the document this store started with.
	 */
	private JsonPatch $patch;

	/**
	 * Whether the paths can still answer for the document.
	 *
	 * Set aside by a whole-document write and by a value no path can carry.
	 */
	private bool $recording = true;

	/**
	 * RecordingStore constructor.
	 */
	public function __construct(array|object $data)
	{
		parent::__construct($data);

		$this->patch = new JsonPatch(\is_array($data) ? $data : []);
	}

	#[Override]
	public function set(string $key, mixed $value): static
	{
		if (self::isRecordable($value)) {
			$this->patch->set($key, $value);
		} else {
			$this->recording = false;
		}

		return parent::set($key, $value);
	}

	#[Override]
	public function remove(string $key): static
	{
		$this->patch->remove($key);

		return parent::remove($key);
	}

	#[Override]
	public function setData(array|object $data): static
	{
		// The whole document, which is not a change to the one that was there.
		$this->recording = false;

		return parent::setData($data);
	}

	/**
	 * One branch of this store, holding one entry per key.
	 *
	 * How per-entry state is written: an entry at a time, under a name that says it is a member's
	 * name. See {@see StoreEntries}.
	 *
	 * @param non-empty-string $branch the branch, as a dot path
	 */
	public function entries(string $branch): StoreEntries
	{
		return new StoreEntries($this, $branch, DotPath::parse($branch)->getSegments());
	}

	/**
	 * Writes a value at the given path, as segments.
	 *
	 * Every segment is a member's name, whatever it looks like: for a caller that knows the shape of
	 * what it keeps and does not want it read off the values. {@see self::set()} takes a dot path.
	 *
	 * @param non-empty-list<string> $segments
	 *
	 * @internal for {@see StoreEntries}
	 */
	public function writeAt(array $segments, mixed $value): static
	{
		if (self::isRecordable($value)) {
			$this->patch->setAt($segments, $value);
		} else {
			$this->recording = false;
		}

		return parent::set(self::asDotPath($segments), $value);
	}

	/**
	 * Removes the value at the given path, as segments; the counterpart of {@see self::writeAt()}.
	 *
	 * @param non-empty-list<string> $segments
	 *
	 * @internal for {@see StoreEntries}
	 */
	public function eraseAt(array $segments): static
	{
		$this->patch->removeAt($segments);

		return parent::remove(self::asDotPath($segments));
	}

	/**
	 * The writes this store recorded, or null when the paths cannot answer for its document.
	 *
	 * Null is the answer when the whole document was replaced, when a value reached the store that
	 * no path can carry, and when the document was changed around this API: the paths are compared
	 * with what the store actually holds, so a write that bypassed them is noticed here rather than
	 * silently left out of the UPDATE.
	 */
	public function recordedPatch(): ?JsonPatch
	{
		if (!$this->recording) {
			return null;
		}

		$data = $this->getData();

		if (!\is_array($data) || !self::sameDocument($this->patch->toArray(), $data)) {
			return null;
		}

		return $this->patch;
	}

	/**
	 * The recorded writes, in the order they were made.
	 *
	 * For a caller that writes them somewhere other than the column the store was read from, and so
	 * has to move their paths.
	 *
	 * @return list<array{op: 'remove'|'set', path: non-empty-list<int|string>, value?: mixed}>
	 */
	public function recordedOperations(): array
	{
		return $this->recordedPatch()?->getOperations() ?? [];
	}

	/**
	 * Segments as the path string the store itself reads, a segment that is not a plain name written
	 * in brackets so that a dot or a bracket in it stays one segment.
	 *
	 * @param non-empty-list<string> $segments
	 */
	private static function asDotPath(array $segments): string
	{
		return (string) new DotPath($segments);
	}

	/**
	 * Whether a value can be carried by a path.
	 */
	private static function isRecordable(mixed $value): bool
	{
		return null === $value
			|| \is_scalar($value)
			|| \is_array($value)
			|| $value instanceof JsonSerializable;
	}

	/**
	 * Whether two documents hold the same thing, whatever order their keys are in.
	 */
	private static function sameDocument(array $a, array $b): bool
	{
		return self::sorted($a) === self::sorted($b);
	}

	/**
	 * A document with every map's keys in one order, so that comparing two of them answers what they
	 * hold and not the order they were written in.
	 */
	private static function sorted(array $data): array
	{
		foreach ($data as $key => $value) {
			if (\is_array($value)) {
				$data[$key] = self::sorted($value);
			}
		}

		if (!\array_is_list($data)) {
			\ksort($data);
		}

		return $data;
	}
}
