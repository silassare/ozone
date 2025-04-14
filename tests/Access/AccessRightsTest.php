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
		$a = (new AccessRights());
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
	public function tesToArray(): void
	{
		$a = new AccessRights();

		$a->allow('users.*');
		$a->deny('users.delete');

		self::assertSame([
			'users.*'      => 1,
			'users.delete' => 0,
		], $a->toArray());
	}
}
