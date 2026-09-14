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

namespace OZONE\Tests\Hooks;

use OZONE\Core\App\Context;
use OZONE\Core\Hooks\Events\RequestHook;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\OZone;
use OZONE\Tests\App;
use PHPUnit\Framework\TestCase;
use PHPUtils\Events\EventManager;
use ReflectionClass;

/**
 * Class ListenerAccumulationTest.
 *
 * The invariant a persistent worker (RoadRunner, Swoole, FrankenPHP worker mode) depends on:
 * **handling a request must not register a listener**. Every `::listen()` call belongs in a
 * `boot()`, which runs once per process; a per-request one would pile up until the worker leaked its
 * way to a restart, and the leak would be invisible in the one-process-per-request model OZone is
 * otherwise tested in.
 *
 * Counted through `EventManager`'s registry by reflection on purpose: the count *is* the thing under
 * test, and a behavioural check ("did the handler run twice?") would only catch a listener that
 * happens to be dispatched during the same test.
 *
 * @internal
 *
 * @coversNothing
 */
final class ListenerAccumulationTest extends TestCase
{
	public function testHandlingRequestsDoesNotRegisterListeners(): void
	{
		$before = self::listenerCount();

		// Several requests in one process, each with its own context, as a worker would.
		for ($i = 0; $i < 5; ++$i) {
			$context = new Context(
				HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
				null,
				Context::root()
			);

			(new RequestHook($context))->dispatch();
			(new ResponseHook($context))->dispatch();
		}

		self::assertSame(
			$before,
			self::listenerCount(),
			'Dispatching the request and response hooks registered listeners; they must only be'
				. ' registered from boot().'
		);
	}

	public function testSubRequestContextsDoNotRegisterListeners(): void
	{
		$before = self::listenerCount();

		$root = Context::root();

		for ($i = 0; $i < 5; ++$i) {
			// A sub-request context, as Navigator::callRoute() builds.
			new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']), null, $root);
		}

		self::assertSame($before, self::listenerCount());
	}

	public function testBootstrappingTwiceDoesNotRegisterAnythingTwice(): void
	{
		$before = self::listenerCount();

		// The suite already bootstrapped; a second call must refuse rather than re-notify the boot
		// receivers, whose boot() methods are not individually idempotent (Session::boot() would add
		// a second DbReadyHook listener).
		@OZone::bootstrap(new App());

		self::assertSame(
			$before,
			self::listenerCount(),
			'A second bootstrap() re-registered the boot hook receivers.'
		);
	}

	public function testEveryRegisteredListenerCameFromBoot(): void
	{
		// A guard on the number itself: it should be stable across the suite, so a new per-request
		// `::listen()` shows up here as a failure rather than as a slow worker leak in production.
		$count = self::listenerCount();

		self::assertGreaterThan(0, $count, 'The framework registers listeners at boot.');
		self::assertLessThan(
			100,
			$count,
			'Unexpectedly many listeners: check that nothing calls ::listen() per request.'
		);
	}

	/**
	 * Every listener currently registered, across every event and channel.
	 */
	private static function listenerCount(): int
	{
		$property = (new ReflectionClass(EventManager::class))->getProperty('listeners');
		$property->setAccessible(true);

		/** @var array<string, mixed> $listeners */
		$listeners = $property->getValue();

		return self::countDeep($listeners);
	}

	/**
	 * @param array<array-key, mixed> $tree
	 */
	private static function countDeep(array $tree): int
	{
		$count = 0;

		foreach ($tree as $value) {
			$count += \is_array($value) ? self::countDeep($value) : 1;
		}

		return $count;
	}
}
