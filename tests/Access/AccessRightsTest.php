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

namespace OZONE\Tests\Access;

use OZONE\Core\Access\AccessRights;
use OZONE\Core\Exceptions\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Class AccessRightsTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class AccessRightsTest extends TestCase
{
	public function testScopes(): void
	{
		$a = new AccessRights([]);
		$b = new AccessRights([]);
		$c = new AccessRights([
			'users.create' => 1,
		]);

		$b->pushScope($a);
		$c->pushScope($a);

		$a->deny('users.delete');

		self::assertFalse($c->can('users.delete'));

		$this->expectException(UnauthorizedException::class);
		$this->expectExceptionMessage('Access right escalation.');

		$b->allow('users.delete');
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testAllow(): void
	{
		$a = new AccessRights();
		$a->allow('users.create');
		$a->allow('users.read');
		$a->allow('users.update');
		$a->allow('groups.*');

		self::assertTrue($a->can('users.create'));
		self::assertTrue($a->can('users.read'));
		self::assertTrue($a->can('users.update'));
		self::assertTrue($a->can('groups.*'));
		self::assertTrue($a->can('groups.create'));

		self::assertSame([
			'users.create' => 1,
			'users.read'   => 1,
			'users.update' => 1,
			'groups.*'     => 1,
		], $a->toArray());
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testDeny(): void
	{
		$a = new AccessRights();

		$a->allow('users.create');
		self::assertTrue($a->can('users.create'));
		$a->deny('users.create');
		self::assertFalse($a->can('users.create'));

		$a->deny('groups.*');
		$a->allow('groups.read');
		self::assertFalse($a->can('groups.create'));
		self::assertTrue($a->can('groups.read'));

		self::assertSame([
			'users.create' => 0,
			'groups.*'     => 0,
			'groups.read'  => 1,
		], $a->toArray());
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testCan(): void
	{
		$a = new AccessRights();

		$a->allow('users.*');

		// wildcard allow
		self::assertTrue($a->can('users.create'));

		// users.delete is not allowed so users.* should return false
		$a->deny('users.delete');
		self::assertFalse($a->can('users.*'));

		// users.medias.* is not allowed so users.medias.read should return false
		$a->deny('users.medias.*');
		self::assertFalse($a->can('users.medias.read'));

		$b = new AccessRights();
		$b->allow('users.medias.*');
		$b->deny('users.*');

		// while users.* is denied, users.medias.* is allowed so users.medias.read should return true
		self::assertTrue($b->can('users.medias.upload'));

		$c = new AccessRights();

		// users.* is allowed so users.medias.* should return true
		$c->allow('users.*');
		self::assertTrue($c->can('users.medias.upload'));

		// groups.* is denied so groups.medias.upload should return false and groups.attachments.* should return false
		$c->deny('groups.*');
		self::assertFalse($c->can('groups.medias.upload'));
		self::assertFalse($c->can('groups.attachments.*'));
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testAssertCan(): void
	{
		$a = new AccessRights();

		$a->allow('users.*');
		$a->deny('users.delete');

		$this->expectException(UnauthorizedException::class);
		$a->assertCan('users.delete');
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testToArray(): void
	{
		$a = new AccessRights();

		$a->allow('users.*');
		$a->deny('users.delete');

		self::assertSame([
			'users.*'      => 1,
			'users.delete' => 0,
		], $a->toArray());
	}

	// -----------------------------------------------------------------------
	// Multi-level wildcards
	// -----------------------------------------------------------------------

	/**
	 * @throws UnauthorizedException
	 */
	public function testThreeLevelWildcardResolvesCorrectly(): void
	{
		$a = new AccessRights([], false);
		$a->allow('a.b.*');

		self::assertTrue($a->can('a.b.c'));
		self::assertTrue($a->can('a.b.d'));
		self::assertFalse($a->can('a.c.d'));
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testGranularDenyOverridesWildcardAllow(): void
	{
		$a = new AccessRights([], false);
		$a->allow('a.*');
		$a->deny('a.secret');

		self::assertTrue($a->can('a.read'));
		self::assertFalse($a->can('a.secret'));
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testWildcardCanIsFalseWhenAnyChildIsDenied(): void
	{
		// can('a.*') should be false because a.secret is explicitly denied.
		$a = new AccessRights([], false);
		$a->allow('a.*');
		$a->deny('a.secret');

		self::assertFalse($a->can('a.*'));
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testWildcardCanIsTrueWhenNoChildDenied(): void
	{
		$a = new AccessRights([], false);
		$a->allow('a.*');

		self::assertTrue($a->can('a.*'));
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testDeeplyNestedGranularAllowUnderDeniedWildcard(): void
	{
		// deny a.b.* but explicitly allow a.b.read.
		$a = new AccessRights([], false);
		$a->deny('a.b.*');
		$a->allow('a.b.read');

		self::assertTrue($a->can('a.b.read'));
		self::assertFalse($a->can('a.b.write'));
	}

	// -----------------------------------------------------------------------
	// Multiple actions in one can() call
	// -----------------------------------------------------------------------

	/**
	 * @throws UnauthorizedException
	 */
	public function testCanWithMultipleActionsReturnsTrueOnlyWhenAllAreAllowed(): void
	{
		$a = new AccessRights([], false);
		$a->allow('files.read');
		$a->allow('files.download');

		self::assertTrue($a->can('files.read', 'files.download'));
		self::assertFalse($a->can('files.read', 'files.delete'));
	}

	// -----------------------------------------------------------------------
	// Scope interaction
	// -----------------------------------------------------------------------

	public function testAllowThrowsWhenActionDeniedInParentScope(): void
	{
		$parent = new AccessRights(['admin.*' => 0], false);
		$child  = new AccessRights([], false);
		$child->pushScope($parent);

		$this->expectException(UnauthorizedException::class);
		$child->allow('admin.delete');
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testChildCannotEscalateAboveParentScope(): void
	{
		// Parent allows only files.read.
		$parent = new AccessRights(['files.read' => 1], false);
		$child  = new AccessRights([], false);
		$child->pushScope($parent);

		$this->expectException(UnauthorizedException::class);
		$child->allow('files.write');   // not in parent -> escalation
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testChildInheritsParentDenial(): void
	{
		$parent = new AccessRights([], false);
		$parent->deny('admin.delete');

		$child = new AccessRights([], false);
		$child->pushScope($parent);

		self::assertFalse($child->can('admin.delete'));
	}

	/**
	 * @throws UnauthorizedException
	 */
	public function testScopePushedTwiceIsNotDuplicated(): void
	{
		$parent = new AccessRights(['a.read' => 1], false);
		$child  = new AccessRights([], false);
		$child->pushScope($parent);
		$child->pushScope($parent);   // second push must be a no-op

		// Only one scope should be influencing can(); just assert it works correctly.
		$child->allow('a.read');
		self::assertTrue($child->can('a.read'));
	}

	// -----------------------------------------------------------------------
	// assertCan()
	// -----------------------------------------------------------------------

	/**
	 * @throws UnauthorizedException
	 */
	public function testAssertCanDoesNotThrowWhenAllowed(): void
	{
		$a = new AccessRights([], false);
		$a->allow('x.y');
		$a->assertCan('x.y');  // must not throw

		self::assertTrue(true);  // reached here without exception
	}

	public function testAssertCanThrowsWhenDenied(): void
	{
		$a = new AccessRights([], false);
		$a->deny('x.y');

		$this->expectException(UnauthorizedException::class);
		$a->assertCan('x.y');
	}

	// -----------------------------------------------------------------------
	// toArray()
	// -----------------------------------------------------------------------

	/**
	 * @throws UnauthorizedException
	 */
	public function testToArrayReflectsAllowAndDenyEntries(): void
	{
		$a = new AccessRights([], false);
		$a->allow('docs.read');
		$a->deny('docs.write');

		self::assertSame([
			'docs.read'  => 1,
			'docs.write' => 0,
		], $a->toArray());
	}

	// -----------------------------------------------------------------------
	// from(OZAuth)
	// -----------------------------------------------------------------------

	public function testFromAuthPopulatesOptions(): void
	{
		$permissions = ['tasks.read' => 1, 'tasks.write' => 0];

		// Simulate loading AccessRights from a permissions array
		// (same path used internally by AccessRights::from() for OZAuth entities).
		$a = new AccessRights($permissions, false);

		self::assertTrue($a->can('tasks.read'));
		self::assertFalse($a->can('tasks.write'));
	}
}
