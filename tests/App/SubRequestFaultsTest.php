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

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Runtime\Interfaces\RuntimeInterface;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class SubRequestFaultsTest.
 *
 * What one request making sub-requests must survive: several of them failing, one failing inside a
 * buffer its caller opened, many of them one after the other, a chain of them as deep as a page can
 * honestly make.
 *
 * Run under a worker runtime: the last resort of a report that failed ends a console process, which
 * here would be PHPUnit's own, where a worker's unwinds and leaves something to assert on.
 *
 * @internal
 */
#[CoversClass(BaseException::class)]
#[CoversClass(Router::class)]
final class SubRequestFaultsTest extends TestCase
{
	private ?RuntimeInterface $runtime = null;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$router = OZone::getApiRouter();

		if (null !== $router->getRoute('faults:fail')) {
			return;
		}

		$router->get('/faults/fail', static function (): never {
			throw new RuntimeException('Failing on purpose.');
		})->name('faults:fail');

		$router->get('/faults/ok', static fn (RouteInfo $ri): Response => $ri->getContext()->getResponse())
			->name('faults:ok');

		$router->get('/faults/fail-twice', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();
			$first   = $context->callRoute('faults:fail', [], [], false);
			$second  = $context->callRoute('faults:fail', [], [], false);

			return $context->getResponse()
				->withHeader('X-Statuses', $first->getStatusCode() . ',' . $second->getStatusCode());
		})->name('faults:fail-twice');

		$router->get('/faults/buffer', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();

			\ob_start();

			echo 'kept';

			$sub    = $context->callRoute('faults:fail', [], [], false);
			$output = \ob_get_clean();

			return $context->getResponse()
				->withHeader('X-Buffer', false === $output ? '(closed)' : $output)
				->withHeader('X-Sub', (string) $sub->getStatusCode());
		})->name('faults:buffer');

		$router->get('/faults/siblings', static function (RouteInfo $ri): Response {
			$context  = $ri->getContext();
			$statuses = [];

			for ($i = 0; $i < 12; ++$i) {
				$statuses[] = $context->callRoute('faults:ok', [], [], false)->getStatusCode();
			}

			return $context->getResponse()->withHeader('X-Statuses', \implode(',', $statuses));
		})->name('faults:siblings');

		$router->get('/faults/deep/:n', static function (RouteInfo $ri): Response {
			$context = $ri->getContext();
			$n       = (int) $ri->param('n');

			if (0 === $n) {
				return $context->getResponse()->withHeader('X-Depth', (string) $context->subRequestDepth());
			}

			return $context->callRoute('faults:deep', ['n' => $n - 1], [], false);
		})->param('n', '[0-9]+')->name('faults:deep');
	}

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->runtime = Runtime::current();

		Runtime::set(new WorkerRuntime('sub-request-faults'));
	}

	#[Override]
	protected function tearDown(): void
	{
		if (null !== $this->runtime) {
			Runtime::set($this->runtime);
		}

		parent::tearDown();
	}

	/**
	 * An error is reported once its response is built, and the request goes on: a second failing
	 * sub-request, and the request itself after them, are answered like the first. The flag that
	 * stops a failure to report from looping used to stay set until the request ended, so the second
	 * one died as an error met while reporting an error.
	 */
	public function testTwoFailingSubRequestsLeaveTheRequestAnswered(): void
	{
		$response = self::request('/faults/fail-twice');

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('500,500', $response->getHeaderLine('X-Statuses'));
	}

	/**
	 * A failing sub-request drops what it wrote, and nothing its caller had opened: it used to close
	 * every buffer down to the first, the handler's that made it among them.
	 */
	public function testAFailingSubRequestLeavesItsCallersBufferAlone(): void
	{
		$level    = \ob_get_level();
		$response = self::request('/faults/buffer');

		self::assertSame($level, \ob_get_level());
		self::assertSame(200, $response->getStatusCode());
		self::assertSame('500', $response->getHeaderLine('X-Sub'));
		self::assertSame('kept', $response->getHeaderLine('X-Buffer'));
	}

	/**
	 * Sub-requests made one after the other are no recursion: the guard counted every route the
	 * request ran, and refused the tenth.
	 */
	public function testManySubRequestsInARowAreServed(): void
	{
		$response = self::request('/faults/siblings');

		self::assertSame(200, $response->getStatusCode());
		self::assertSame(\implode(',', \array_fill(0, 12, '200')), $response->getHeaderLine('X-Statuses'));
	}

	/**
	 * What is bounded is how deep a chain goes: as deep as `OZ_SUB_REQUEST_MAX_DEPTH` and no deeper.
	 */
	public function testAChainOfSubRequestsIsBoundedByItsDepth(): void
	{
		$max = (int) Settings::get('oz.request', 'OZ_SUB_REQUEST_MAX_DEPTH');

		self::assertSame(16, $max);

		// The request below is a sub-request of the suite's root already: depth 1, then one more per
		// step, the last one at the bound.
		$reached = self::request('/faults/deep/' . ($max - 1));

		self::assertSame(200, $reached->getStatusCode());
		self::assertSame((string) $max, $reached->getHeaderLine('X-Depth'));

		self::assertSame(500, self::request('/faults/deep/' . $max)->getStatusCode());
	}

	/**
	 * A request of the suite, as a sub-request of its root, answered.
	 */
	private static function request(string $path): Response
	{
		$context = new Context(HTTPEnvironment::mock([
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => $path,
			'HTTP_ACCEPT'    => 'application/json',
		]), null, Context::root());

		return $context->handle()->getResponse();
	}
}
