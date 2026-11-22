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

namespace OZONE\Tests\Revisions;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Revisions\Events\RevisionWritten;
use OZONE\Core\Revisions\Revision;
use OZONE\Core\Revisions\Revisions;
use OZONE\Core\Revisions\RevisionWrite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class RevisionsTest.
 *
 * Resources kept with their history, in the sandbox's database. Each test has a namespace of its
 * own, so none reads what another wrote. Two writers racing for one number are the integration
 * suite's, on each real database.
 *
 * @internal
 */
#[CoversClass(Revisions::class)]
#[CoversClass(Revision::class)]
#[CoversClass(RevisionWrite::class)]
final class RevisionsTest extends TestCase
{
	public function testEachWriteBasedOnTheCurrentRevisionIsTheNextOne(): void
	{
		$revisions = self::revisions();

		self::assertSame(1, self::written($revisions->write('page', '{"title":"One"}', null))->number);
		self::assertSame(2, self::written($revisions->write('page', '{"title":"Two"}', 1, 'Retitled'))->number);

		$current = $revisions->current('page');

		self::assertNotNull($current);
		self::assertSame('{"title":"Two"}', $current->content);
		self::assertSame('Retitled', $current->note);
		// Written outside a request: nobody is its author.
		self::assertNull($current->authorId);
	}

	public function testAWriteBasedOnAStaleRevisionIsRefusedWithTheCurrentOne(): void
	{
		$revisions = self::revisions();

		$revisions->write('page', '{"title":"One"}', null);
		$revisions->write('page', '{"title":"Two"}', 1);

		$stale = $revisions->write('page', '{"title":"Mine"}', 1);

		$current = $stale->revision;

		self::assertFalse($stale->applied);
		self::assertNotNull($current);
		self::assertSame(2, $current->number);
		self::assertSame('{"title":"Two"}', $current->content);

		// Based on nothing where there is something is stale too: a second author creating it.
		self::assertFalse($revisions->write('page', '{"title":"New"}', null)->applied);
		// And based on something where there is nothing.
		self::assertFalse($revisions->write('other', '{"title":"New"}', 3)->applied);
		self::assertNull($revisions->write('other', '{"title":"New"}', 3)->revision);
	}

	public function testARemovalIsARevisionAndTheKeyHoldsNothingAfterIt(): void
	{
		$revisions = self::revisions();

		$revisions->write('a', '{"n":1}', null);
		$revisions->write('b', '{"n":1}', null);
		$revisions->write('b', '{"n":2}', 1);

		$removal = self::written($revisions->write('a', null, 1, 'Reset'));

		self::assertTrue($removal->isRemoval());
		self::assertNull($removal->digest);
		self::assertSame(['b'], \array_keys($revisions->currentAll()));
		self::assertSame('{"n":2}', $revisions->currentAll()['b']->content);
		// Kept in the history, newest first.
		self::assertSame([2, 1], \array_map(static fn (Revision $r): int => $r->number, $revisions->history('a')));
	}

	public function testARestoreWritesWhatAPastRevisionHeldAsANewOne(): void
	{
		$revisions = self::revisions();

		$revisions->write('page', '{"title":"One"}', null);
		$revisions->write('page', null, 1);

		$restored = self::written($revisions->restore('page', 1, 2));

		self::assertSame(3, $restored->number);
		self::assertSame('{"title":"One"}', $restored->content);
		self::assertSame('Restored revision 1.', $restored->note);
		// Based on a stale revision, refused like any write.
		self::assertFalse($revisions->restore('page', 1, 2)->applied);
	}

	public function testRestoringARevisionThatIsNotThereSaysSo(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/has no revision 4/');

		self::revisions()->restore('page', 4, null);
	}

	public function testTheContentIsKeptAsWrittenAndDigestedByItsBytes(): void
	{
		$revisions = self::revisions();
		// What a PHP array cannot hold: an empty object one level down, beside an empty list.
		$content = "{\n\t\"header\": {},\n\t\"items\": []\n}\n";
		$written = self::written($revisions->write('page', $content, null));

		self::assertSame($content, $revisions->current('page')?->content);
		self::assertSame('sha256:' . \hash('sha256', $content), $written->digest);
		self::assertSame(Revisions::digestOf($content), $written->digest);
	}

	public function testTheVersionMovesWithEveryWrite(): void
	{
		$revisions = self::revisions();

		self::assertNull($revisions->version());

		$revisions->write('a', '{"n":1}', null);
		$first = $revisions->version();

		$revisions->write('b', '{"n":1}', null);

		self::assertNotNull($first);
		self::assertNotSame($first, $revisions->version());
		// A refused write is no change.
		$before = $revisions->version();
		$revisions->write('a', '{"n":2}', null);
		self::assertSame($before, $revisions->version());
	}

	public function testAWriteIsAnnouncedWithItsValues(): void
	{
		$revisions = self::revisions();
		$heard     = [];
		$detach    = RevisionWritten::listen(static function (RevisionWritten $event) use (&$heard): void {
			$heard[] = [$event->namespace, $event->key, $event->number, $event->digest];
		});

		try {
			$revisions->write('page', '{"title":"One"}', null);
			$revisions->write('page', '{"title":"Two"}', null);
		} finally {
			$detach();
		}

		// The refused one is not announced.
		self::assertCount(1, $heard);
		self::assertSame(['page', 1, Revisions::digestOf('{"title":"One"}')], \array_slice($heard[0], 1));
	}

	public function testANamespaceTheTableCannotHoldIsRefused(): void
	{
		$this->expectException(RuntimeException::class);

		Revisions::of(\str_repeat('n', Revisions::NAMESPACE_MAX + 1));
	}

	public function testAnEmptyKeyIsRefused(): void
	{
		$this->expectException(RuntimeException::class);

		self::revisions()->write('', '{"n":1}', null);
	}

	private static function revisions(): Revisions
	{
		return Revisions::of('test:' . \bin2hex(\random_bytes(6)));
	}

	/**
	 * The revision a write that must have applied wrote.
	 */
	private static function written(RevisionWrite $write): Revision
	{
		self::assertTrue($write->applied, 'The write was refused.');
		self::assertNotNull($write->revision);

		return $write->revision;
	}
}
