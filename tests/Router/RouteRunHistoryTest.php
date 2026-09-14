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

namespace OZONE\Tests\Router;

use OZONE\Core\App\Context;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteRunHistoryTest.
 *
 * The recursion guard of `Router::runRoute()` counts the routes run in **one request tree**, owned
 * by the root context. It used to count them in one *process*, through a `static` that was never
 * reset, so a single request making many sub-requests hit the limit, and a persistent worker hit it
 * on its eleventh route run whatever the request.
 *
 * Isolation between *requests* is `RequestIsolationTest`: this suite shares one root context, so the
 * assertions here are about growth within a tree.
 *
 * @internal
 *
 * @covers \OZONE\Core\App\Navigator
 */
final class RouteRunHistoryTest extends TestCase
{
	public function testTheHistoryIsSharedByARequestAndItsSubRequests(): void
	{
		$root = self::context();
		$sub  = self::context($root);

		$before = \count($root->navigator()->recordRouteRun('probe', '/probe'));

		$root->navigator()->recordRouteRun('a', '/a');
		$trail = $sub->navigator()->recordRouteRun('b', '/b');

		// A sub-request must see the parent's trail: that is what catches a loop going through one.
		self::assertCount($before + 2, $trail);
		self::assertSame(['name' => 'a', 'path' => '/a'], $trail[\count($trail) - 2]);
		self::assertSame(['name' => 'b', 'path' => '/b'], $trail[\count($trail) - 1]);
	}

	public function testDeepSubRequestsAccumulateWithinTheOneRequest(): void
	{
		$root    = self::context();
		$current = $root;

		$before = \count($root->navigator()->recordRouteRun('probe', '/probe'));

		// A chain of sub-requests, each one a child of the last: they all report to the root, so a
		// loop is caught however deep it goes.
		for ($depth = 0; $depth < 5; ++$depth) {
			$current = self::context($current);
			$trail   = $current->navigator()->recordRouteRun('d' . $depth, '/d');
		}

		self::assertCount($before + 5, $trail);
	}

	private static function context(?Context $parent = null): Context
	{
		return new Context(
			HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
			null,
			$parent ?? Context::root()
		);
	}
}
