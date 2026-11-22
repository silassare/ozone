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

namespace OZONE\Core\Revisions;

use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\ORM\ORMOptions;
use OZONE\Core\App\Context;
use OZONE\Core\Db\OZRevision;
use OZONE\Core\Db\OZRevisionsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Revisions\Events\RevisionWritten;
use PDO;

/**
 * Class Revisions.
 *
 * Resources kept with their history, under a namespace and a key: every write, reset and restore is
 * a row of `oz_revisions`, and the current content is the latest one. Nothing is ever updated or
 * deleted, so going back is a write like any other and stays in the history too.
 *
 * Not a store ({@see \OZONE\Core\Stores\KeyValueStore}): a store answers what a value is now and is
 * right to forget the rest, where this answers what it was, who made it and how to go back, and
 * must never forget. It borrows the store's rule for two writers: a write names the revision it was
 * based on, and one based on a revision that is no longer current is refused with the current one.
 *
 * Who may write what is not this class's question: the owner of a namespace guards its own way in.
 */
final class Revisions
{
	/** The longest namespace the table holds. */
	public const int NAMESPACE_MAX = 60;

	/** The longest key the table holds. */
	public const int KEY_MAX = 255;

	private function __construct(private readonly string $namespace) {}

	/**
	 * The resources of a namespace.
	 *
	 * @throws RuntimeException when the name is empty or longer than the table holds
	 */
	public static function of(string $namespace): self
	{
		if ('' === $namespace || \strlen($namespace) > self::NAMESPACE_MAX) {
			throw new RuntimeException(\sprintf(
				'A revisions namespace is 1 to %d characters, not "%s".',
				self::NAMESPACE_MAX,
				$namespace
			));
		}

		return new self($namespace);
	}

	/**
	 * The digest of a content: SHA-256 of its JSON with the keys of every object sorted, so the
	 * same content written twice in another key order has one digest. A list keeps its order.
	 *
	 * @param array<array-key, mixed> $content
	 */
	public static function digestOf(array $content): string
	{
		return \hash('sha256', (string) \json_encode(
			self::canonical($content),
			\JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION
		));
	}

	/**
	 * The latest revision of a resource, or null when it has none. A removal is returned as it is.
	 */
	public function current(string $key): ?Revision
	{
		$row = new OZRevisionsQuery()
			->whereNamespaceIs($this->namespace)
			->whereKeyIs($key)
			->find(ORMOptions::makePaginated(1, 1, [OZRevision::COL_NUMBER => 'DESC']))
			->fetchClass();

		return null === $row ? null : Revision::of($row);
	}

	/**
	 * The current revision of every key whose latest revision is not a removal, by key.
	 *
	 * Read in two steps -- the latest id of each key, then those rows -- so a long history costs a
	 * grouped scan of ids and not the transfer of every content ever written. The latest id is the
	 * latest number, rows being only ever appended.
	 *
	 * @return array<string, Revision>
	 */
	public function currentAll(): array
	{
		$db = db();
		$qb = new QBSelect($db);

		// The table and not its name: a project's prefix is part of what the database calls it.
		$qb->from($db->getTableOrFail(OZRevision::TABLE_NAME))
			->select(null, ['MAX(' . OZRevision::COL_ID . ') AS latest'], false)
			->where(OZRevision::COL_NAMESPACE . ' = :namespace')
			->bindNamed('namespace', $this->namespace)
			->groupBy([OZRevision::COL_KEY]);

		/** @var list<int|string> $ids */
		$ids = $qb->execute()->fetchAll(PDO::FETCH_COLUMN);

		if ([] === $ids) {
			return [];
		}

		$rows    = new OZRevisionsQuery()->whereIdIsIn($ids)->find();
		$current = [];

		while ($row = $rows->fetchClass()) {
			$revision = Revision::of($row);

			if (!$revision->isRemoval()) {
				$current[$revision->key] = $revision;
			}
		}

		\ksort($current, \SORT_STRING);

		return $current;
	}

	/**
	 * Every revision of a resource, newest first.
	 *
	 * @return list<Revision>
	 */
	public function history(string $key, ?int $max = null): array
	{
		$rows = new OZRevisionsQuery()
			->whereNamespaceIs($this->namespace)
			->whereKeyIs($key)
			->find(ORMOptions::makePaginated($max, null, [OZRevision::COL_NUMBER => 'DESC']));

		$history = [];

		while ($row = $rows->fetchClass()) {
			$history[] = Revision::of($row);
		}

		return $history;
	}

	/**
	 * What the namespace was written up to: the id of its latest revision, or null when it has none.
	 * A new one is a namespace that changed, which is what a cache of what it holds is keyed on.
	 */
	public function version(): ?string
	{
		$row = new OZRevisionsQuery()
			->whereNamespaceIs($this->namespace)
			->find(ORMOptions::makePaginated(1, 1, [OZRevision::COL_ID => 'DESC']))
			->fetchClass();

		return $row?->getID();
	}

	/**
	 * Writes a revision of a resource, based on the revision its author read.
	 *
	 * Refused, with the current revision, when `$based_on` is not the current number (null: the
	 * author read no revision, so there must be none). Two writers based on the same revision both
	 * ask the database for the next number, and its unique key keeps one of them: the other is
	 * refused like any stale write.
	 *
	 * @param null|array<array-key, mixed> $content  the content, or null to remove the resource
	 * @param null|int                     $based_on the number of the revision the author read
	 * @param array<array-key, mixed>      $meta     what the owner of the namespace records with it
	 *
	 * @throws RuntimeException when the key is empty or longer than the table holds
	 */
	public function write(
		string $key,
		?array $content,
		?int $based_on,
		?string $note = null,
		array $meta = []
	): RevisionWrite {
		if ('' === $key || \strlen($key) > self::KEY_MAX) {
			throw new RuntimeException(
				\sprintf('A revision key is 1 to %d characters, not "%s".', self::KEY_MAX, $key)
			);
		}

		$current = $this->current($key);

		if ($current?->number !== $based_on) {
			return RevisionWrite::refused($current);
		}

		$digest = null === $content ? null : self::digestOf($content);
		$row    = OZRevision::new()
			->setNamespace($this->namespace)
			->setKey($key)
			->setNumber(($based_on ?? 0) + 1)
			->setContent($content)
			->setDigest($digest)
			->setMeta($meta)
			->setNote($note);

		if (Context::hasRoot()) {
			$context = Context::current();

			if ($context->hasAuthenticatedUser()) {
				$user = $context->auth()->user();

				$row->setAuthorType($user->getAuthUserType())
					->setAuthorID($user->getAuthIdentifier());
			}
		}

		try {
			$row->save();
		} catch (DBALUniqueViolationException) {
			return RevisionWrite::refused($this->current($key));
		}

		$written = Revision::of($row);

		new RevisionWritten($this->namespace, $key, $written->number, $digest)->dispatch();

		return RevisionWrite::applied($written);
	}

	/**
	 * Writes again what a past revision held, as a new revision based on the current one.
	 *
	 * @param null|int $based_on the number of the revision the author read
	 *
	 * @throws RuntimeException when the resource has no revision of that number
	 */
	public function restore(string $key, int $number, ?int $based_on, ?string $note = null): RevisionWrite
	{
		$past = new OZRevisionsQuery()
			->whereNamespaceIs($this->namespace)
			->whereKeyIs($key)
			->whereNumberIs($number)
			->find(ORMOptions::makePaginated(1, 1))
			->fetchClass();

		if (null === $past) {
			throw new RuntimeException(\sprintf(
				'"%s" in revisions "%s" has no revision %d.',
				$key,
				$this->namespace,
				$number
			));
		}

		$restored = Revision::of($past);

		return $this->write($key, $restored->content, $based_on, $note ?? \sprintf('Restored revision %d.', $number));
	}

	/**
	 * A value with the keys of every object sorted, recursively; a list keeps its order.
	 */
	private static function canonical(mixed $value): mixed
	{
		if (!\is_array($value)) {
			return $value;
		}

		$value = \array_map(self::canonical(...), $value);

		if (!\array_is_list($value)) {
			\ksort($value, \SORT_STRING);
		}

		return $value;
	}
}
