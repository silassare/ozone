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

namespace OZONE\Tests\CRUD;

use OZONE\Core\App\Context;
use OZONE\Core\CRUD\Interfaces\TableCRUDListenerInterface;
use OZONE\Core\CRUD\TableCRUDListener;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\OZone;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class TableCRUDListenerContextTest.
 *
 * CRUD listeners are registered once per process, while a worker serves many requests: every access
 * check has to be made for the request being handled. A listener that kept the context it was
 * registered with checked every request against the boot context -- no request, no user.
 *
 * The suite's own root context is saved and restored around each case, as in RequestIsolationTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\CRUD\TableCRUDListener
 */
final class TableCRUDListenerContextTest extends TestCase
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
		self::writeStatic('root_context', $this->saved['root_context']);
		self::writeStatic('current_context', $this->saved['current_context']);

		parent::tearDown();
	}

	public function testAListenerReadsTheContextOfEachRequest(): void
	{
		// Built once, like a listener registered at bootstrap.
		$listener = new class extends TableCRUDListener {
			public static function register(): void {}

			public function contextNow(): Context
			{
				return $this->context();
			}
		};

		$seen = [];

		for ($request = 0; $request < 3; ++$request) {
			OZone::endRequest();

			$context = new Context(HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET']));

			self::assertSame($context, $listener->contextNow(), 'request ' . $request . ' was checked as another');

			$seen[] = \spl_object_id($listener->contextNow());
		}

		self::assertCount(3, \array_unique($seen));
	}

	public function testRegistrationTakesNoContext(): void
	{
		// Nothing to keep: a context given at registration is the boot one.
		$register = new ReflectionMethod(TableCRUDListenerInterface::class, 'register');

		self::assertSame(0, $register->getNumberOfParameters());
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
