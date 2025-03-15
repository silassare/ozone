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

namespace OZONE\Tests\Auth;

use OZONE\Core\Auth\AuthAccessRights;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use PHPUnit\Framework\TestCase;

/**
 * Class AuthAccessRightsTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthAccessRightsTest extends TestCase
{
	public function testAllow(): void
	{
		$a = new AuthAccessRights();
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

	public function testDeny(): void
	{
		$a = new AuthAccessRights();

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

	public function testCan(): void
	{
		$a = (new AuthAccessRights());
		$a->allow('users.*');

		// wildcard allow
		self::assertTrue($a->can('users.create'));

		// users.delete is not allowed so users.* should return false
		$a->deny('users.delete');
		self::assertFalse($a->can('users.*'));

		// users.medias.* is not allowed so users.medias.read should return false
		$a->deny('users.medias.*');
		self::assertFalse($a->can('users.medias.read'));

		$b = new AuthAccessRights();
		$b->allow('users.medias.*');
		$b->deny('users.*');

		// while users.* is denied, users.medias.* is allowed so users.medias.read should return true
		self::assertTrue($b->can('users.medias.upload'));

		$c = new AuthAccessRights();

		// users.* is allowed so users.medias.* should return true
		$c->allow('users.*');
		self::assertTrue($c->can('users.medias.upload'));

		// groups.* is denied so groups.medias.upload should return false and groups.attachments.* should return false
		$c->deny('groups.*');
		self::assertFalse($c->can('groups.medias.upload'));
		self::assertFalse($c->can('groups.attachments.*'));
	}

	/**
	 * @throws UnauthorizedActionException
	 */
	public function testAssertCan(): void
	{
		$a = new AuthAccessRights();

		$a->allow('users.*');
		$a->deny('users.delete');

		$this->expectException(UnauthorizedActionException::class);
		$a->assertCan('users.delete');
	}

	public function tesToArray(): void
	{
		$a = new AuthAccessRights();

		$a->allow('users.*');
		$a->deny('users.delete');

		self::assertSame([
			'users.*'      => 1,
			'users.delete' => 0,
		], $a->toArray());
	}
}
