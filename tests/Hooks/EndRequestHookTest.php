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
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\OZone;
use OZONE\Core\REST\ApiDoc;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Class EndRequestHookTest.
 *
 * Per-request state kept outside the Context releases itself on `EndRequestHook`, from a listener
 * registered once in `boot()` -- the framework's own as much as an application's.
 *
 * The suite's own root context is saved and restored around each case, as in RequestIsolationTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Hooks\Events\EndRequestHook
 * @covers \OZONE\Core\OZone
 */
final class EndRequestHookTest extends TestCase
{
	/** @var array<string, null|Context> */
	private array $saved = [];

	/** @var list<callable():void> */
	private array $detach = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->saved = [
			'root_context'    => self::readStatic('root_context'),
			'current_context' => self::readStatic('current_context'),
		];
	}

	protected function tearDown(): void
	{
		foreach ($this->detach as $detach) {
			$detach();
		}

		self::writeStatic('root_context', $this->saved['root_context']);
		self::writeStatic('current_context', $this->saved['current_context']);

		parent::tearDown();
	}

	public function testAListenerSeesTheRequestBeingReleased(): void
	{
		OZone::endRequest();

		$context = new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));
		$seen    = [];

		$this->detach[] = EndRequestHook::listen(static function (EndRequestHook $hook) use (&$seen): void {
			$seen[] = [$hook->context, Context::hasRoot()];
		});

		OZone::endRequest();

		// The listener runs while the context is still there, and the tree is released after it.
		self::assertSame([[$context, true]], $seen);
		self::assertFalse(Context::hasRoot());

		// Between two requests there is nothing to release, and the listener is told so.
		OZone::endRequest();

		self::assertNull($seen[1][0]);
	}

	public function testTheContextIsReleasedWhateverAListenerDoes(): void
	{
		OZone::endRequest();

		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

		$this->detach[] = EndRequestHook::listen(static function (): void {
			throw new RuntimeException('a cleanup that fails');
		});

		OZone::endRequest();

		// A failing cleanup is logged; the next request can still own a root.
		self::assertFalse(Context::hasRoot());
		self::assertInstanceOf(Context::class, new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET'])));
	}

	public function testTheApiDocumentationIsBuiltPerRequest(): void
	{
		OZone::endRequest();
		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

		$first = ApiDoc::get();

		self::assertSame($first, ApiDoc::get(), 'one instance per request');

		// Released by the framework's own listener (MainBootHookReceiver::boot()).
		OZone::endRequest();
		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

		self::assertNotSame($first, ApiDoc::get());
	}

	private static function readStatic(string $name): ?Context
	{
		$property = (new ReflectionClass(Context::class))->getProperty($name);
		$property->setAccessible(true);

		/** @var null|Context $value */
		return $property->getValue();
	}

	private static function writeStatic(string $name, ?Context $value): void
	{
		$property = (new ReflectionClass(Context::class))->getProperty($name);
		$property->setAccessible(true);
		$property->setValue(null, $value);
	}
}
