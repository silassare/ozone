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

namespace OZONE\Tests\App;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\OZone;
use OZONE\Core\Stores\CacheRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Class RequestIsolationTest.
 *
 * Several requests in one process, which is what a persistent worker does (RoadRunner, Swoole,
 * FrankenPHP worker mode) and what OZone otherwise never does: the request lifecycle ends in `exit`,
 * and a second root context is refused while the first is alive.
 *
 * `OZone::endRequest()` is what makes it possible, and this asserts that what it releases really is
 * released -- and, just as importantly, that what it keeps (the routers, the settings, the database,
 * the registries: configuration) is still there afterwards.
 *
 * The suite's own root context is saved and restored by reflection around each case: releasing it is
 * a process-level operation, and every other test in the run depends on it.
 *
 * @internal
 *
 * @coversNothing
 */
final class RequestIsolationTest extends TestCase
{
	/** @var array<string, null|Context> */
	private array $saved = [];

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
		// Put the suite's context tree back, whatever the test did to it.
		self::writeStatic('root_context', $this->saved['root_context']);
		self::writeStatic('current_context', $this->saved['current_context']);

		parent::tearDown();
	}

	public function testASecondRootContextIsRefusedWithoutEndRequest(): void
	{
		// This is why a worker cannot simply keep going: the next request has nowhere to live.
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~Root context already set~');

		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));
	}

	public function testEndRequestLetsTheNextRequestOwnItsRoot(): void
	{
		$contexts = [];

		for ($request = 0; $request < 4; ++$request) {
			OZone::endRequest();

			$context    = new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']));
			$contexts[] = $context;

			self::assertSame($context, Context::root(), 'request ' . $request . ' does not own its root');
			self::assertFalse($context->isSubRequest());
		}

		// Four distinct roots, none of them each other.
		self::assertCount(4, \array_unique(\array_map('spl_object_id', $contexts)));
	}

	public function testTheRouteRunHistoryStartsEmptyInEachRequest(): void
	{
		for ($request = 0; $request < 12; ++$request) {
			OZone::endRequest();

			$context = new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));
			$trail   = $context->navigator()->recordRouteRun('r', '/r');

			// Ten route runs in one request is what the guard refuses; twelve requests of one run
			// each has to stay fine, however many a worker handles.
			self::assertCount(1, $trail, 'request ' . $request . ' inherited an earlier history');
		}
	}

	public function testTheRuntimeCacheDoesNotLeakBetweenRequests(): void
	{
		OZone::endRequest();
		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

		CacheRegistry::runtime(__METHOD__)->set('answer', 42);

		self::assertSame(42, CacheRegistry::runtime(__METHOD__)->get('answer'));

		OZone::endRequest();
		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

		// `CacheRegistry::runtime()` is documented as per-request memoization; it is held in a
		// process-wide array, so without the release the next request answers from this one.
		self::assertNull(
			CacheRegistry::runtime(__METHOD__)->get('answer'),
			'the runtime cache carried a value into the next request'
		);
	}

	public function testTheErrorHandledFlagIsResetBetweenRequests(): void
	{
		$flag = (new ReflectionClass(BaseException::class))->getProperty('just_die');
		$flag->setAccessible(true);

		// As a handled error leaves it: every later error would then die immediately, with no
		// response, for the rest of the process.
		$flag->setValue(null, true);

		OZone::endRequest();

		self::assertFalse($flag->getValue(), 'the next request would die without a response');
	}

	public function testEndRequestKeepsTheProcessWideConfiguration(): void
	{
		$settings_before = Settings::get('oz.config', 'OZ_PROJECT_NAME');
		$db_before       = db();

		OZone::endRequest();
		new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

		// Configuration is what a worker keeps: re-reading the settings or reconnecting per request
		// would make worker mode slower than the model it replaces.
		self::assertSame($settings_before, Settings::get('oz.config', 'OZ_PROJECT_NAME'));
		self::assertSame($db_before, db(), 'the database instance must survive a request');
		self::assertSame(OZone::getApiRouter(), OZone::getApiRouter(), 'the router is built once per process');
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
