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

namespace OZONE\Tests\REST;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\ApiDoc\OperationBuilder;
use OZONE\Core\Router\Router;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The host a route answers on, as the OpenAPI document tells it: an operation's own `servers`
 * entry, one server variable per host parameter.
 *
 * @internal
 */
#[CoversClass(OperationBuilder::class)]
final class ApiDocRouteHostTest extends TestCase
{
	private ?Context $previous = null;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->previous = self::currentContext()->getValue();
	}

	#[Override]
	protected function tearDown(): void
	{
		self::currentContext()->setValue(null, $this->previous);
		ApiDoc::release();

		parent::tearDown();
	}

	public function testARouteOnAHostTemplateHasAServerWithAVariablePerHostParam(): void
	{
		$operation = self::operation('docs.example.org', '/doc-host-projects/:id');

		self::assertSame([[
			'url'         => 'http://{tenant}.{zone}.example.com',
			'description' => 'The host the route answers on: `{tenant}.{zone}.example.com`.',
			'variables'   => [
				'tenant' => [
					'default'     => 'tenant',
					'description' => 'The host parameter `tenant`, matching the pattern `[^.]+`. The default is a'
						. ' placeholder to replace: this document is not served on a host of the route.',
				],
				'zone'   => [
					'default'     => 'zone',
					'description' => 'The host parameter `zone`, matching the pattern `[a-z]+`. The default is a'
						. ' placeholder to replace: this document is not served on a host of the route.',
				],
			],
		]], $operation['servers'] ?? null);
		// The extensions are what they were.
		self::assertSame(['auth_methods', 'guards'], \array_keys($operation['x-oz-security']['value'] ?? []));
	}

	public function testADefaultIsTheValueOfTheHostServingTheDocument(): void
	{
		$operation = self::operation('acme.eu.example.com:8080', '/doc-host-projects/:id');
		$variables = $operation['servers'][0]['variables'] ?? [];

		self::assertSame('http://{tenant}.{zone}.example.com:8080', $operation['servers'][0]['url'] ?? null);
		self::assertSame('acme', $variables['tenant']['default'] ?? null);
		self::assertSame('eu', $variables['zone']['default'] ?? null);
	}

	public function testALiteralHostHasAServerWithNoVariableAndARouteOfEveryHostNone(): void
	{
		$literal = self::operation('docs.example.org', '/doc-host-admin');
		$any     = self::operation('docs.example.org', '/doc-host-any');

		self::assertSame([[
			'url'         => 'http://admin.example.com',
			'description' => 'The host the route answers on: `admin.example.com`.',
		]], $literal['servers'] ?? null);
		self::assertArrayNotHasKey('servers', $any);
	}

	/**
	 * The operation documented for a route of {@see self::router()}, the document built on a request
	 * to the given host.
	 */
	private static function operation(string $served_host, string $path): array
	{
		$context = new Context(HTTPEnvironment::mock(['HTTP_HOST' => $served_host]), null, Context::root());

		self::currentContext()->setValue(null, $context);

		$router = self::router();
		$route  = TestUtils::route($router, match ($path) {
			'/doc-host-projects/:id' => 'doc_host.project',
			'/doc-host-admin'        => 'doc_host.admin',
			default                  => 'doc_host.any',
		});
		$doc = ApiDoc::get();

		$doc->operations()->fromRoute($route, 'GET', 'A page', []);

		$spec = \json_decode(\json_encode($doc->toArray()), true)['spec'];

		return $spec['paths'][$path]['get'];
	}

	private static function router(): Router
	{
		$router = new Router();
		$h      = static fn () => null;

		$router->get('/doc-host-projects/:id', $h)
			->name('doc_host.project')
			->withAuthenticatedUser('user')
			->host('{tenant}.{zone}.example.com')
			->param('zone', '[a-z]+');
		$router->get('/doc-host-admin', $h)->name('doc_host.admin')->host('admin.example.com');
		$router->get('/doc-host-any', $h)->name('doc_host.any');

		return $router;
	}

	private static function currentContext(): ReflectionProperty
	{
		return new ReflectionProperty(Context::class, 'current_context');
	}
}
