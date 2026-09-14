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
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\Enums\RESTFulAction;
use OZONE\Core\REST\RESTFulAPIRequest;
use OZONE\Core\REST\RESTFulService;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\RouteOptions;
use OZONE\Core\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Class RESTFulServiceTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\REST\RESTFulApiDoc
 * @covers \OZONE\Core\REST\RESTFulService
 */
final class RESTFulServiceTest extends TestCase
{
	private static bool $registered = false;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// Access rights of a table can only be registered once per process. The routes go on the
		// context router, where the API docs look them up.
		if (!self::$registered) {
			self::$registered = true;

			$router = context()->getRouter();

			StubUsersRESTService::registerRoutes($router);
			StubFilesRESTService::registerRoutes($router);
		}
	}

	public function testRoutesFollowTheActionTable(): void
	{
		foreach (RESTFulAction::cases() as $action) {
			$route = self::route(StubUsersRESTService::routeName($action));

			if (!StubUsersRESTService::isActionEnabled($action)) {
				self::assertNull($route, $action->value);

				continue;
			}

			self::assertNotNull($route, $action->value);
			self::assertSame([$action->httpMethod()], $route->getMethods(), $action->value);
			self::assertSame(
				'/stub-rest-users' . ($action->onEntry() ? '/:id' : '') . $action->path(),
				$route->getPath(),
				$action->value
			);
		}
	}

	public function testRouteNameAcceptsAnAction(): void
	{
		self::assertSame('/stub-rest-users.get_one', StubUsersRESTService::routeName(RESTFulAction::GET_ONE));
		self::assertSame(
			StubUsersRESTService::routeName('get_one'),
			StubUsersRESTService::routeName(RESTFulAction::GET_ONE)
		);
	}

	public function testDisabledActionsAreNotRegistered(): void
	{
		self::assertFalse(StubUsersRESTService::isActionEnabled(RESTFulAction::DELETE_ONE));
		self::assertFalse(StubUsersRESTService::isActionEnabled('get_relation'));
		self::assertTrue(StubUsersRESTService::isActionEnabled(RESTFulAction::GET_ONE));

		self::assertNull(self::route(StubUsersRESTService::routeName(RESTFulAction::DELETE_ONE)));
		self::assertNull(self::route(StubUsersRESTService::routeName(RESTFulAction::GET_RELATION)));

		// Another service keeps its own actions.
		self::assertTrue(StubFilesRESTService::isActionEnabled(RESTFulAction::DELETE_ONE));
		self::assertNotNull(self::route(StubFilesRESTService::routeName(RESTFulAction::DELETE_ONE)));
	}

	public function testDisabledActionsAreNotDocumented(): void
	{
		// Otherwise there would be no relation operation to leave out.
		self::assertNotEmpty(db()->getTableOrFail('oz_users')->getRelations(false));

		$doc = ApiDoc::get();

		StubUsersRESTService::apiDoc($doc);

		$paths = \json_decode(\json_encode($doc->toArray()), true)['spec']['paths'];
		$entry = $paths['/stub-rest-users/:id'] ?? [];

		self::assertArrayHasKey('get', $entry);
		self::assertArrayHasKey('patch', $entry);
		self::assertArrayNotHasKey('delete', $entry);
		self::assertSame([], \array_values(\array_filter(
			\array_keys($paths),
			static fn (string $path) => \str_starts_with($path, '/stub-rest-users/:id/')
		)));
	}

	public function testEveryEnabledActionAndRelationIsDocumented(): void
	{
		$doc = ApiDoc::get();

		StubFilesRESTService::apiDoc($doc);

		$spec       = \json_decode(\json_encode($doc->toArray()), true)['spec'];
		$operations = [];

		foreach ($spec['paths'] as $path => $item) {
			if (!\str_starts_with($path, '/stub-rest-files')) {
				continue;
			}

			foreach ($item as $method => $operation) {
				if (isset($operation['operationId'])) {
					$operations[$operation['operationId']] = \strtoupper($method) . ' ' . $path;
				}
			}
		}

		\ksort($operations);

		self::assertSame([
			'file.create_one'               => 'POST /stub-rest-files',
			'file.delete_all'               => 'DELETE /stub-rest-files',
			'file.delete_one'               => 'DELETE /stub-rest-files/:id',
			'file.get_all'                  => 'GET /stub-rest-files',
			'file.get_one'                  => 'GET /stub-rest-files/:id',
			'file.get_relation.cloned_from' => 'GET /stub-rest-files/:id/cloned_from',
			'file.get_relation.clones'      => 'GET /stub-rest-files/:id/clones',
			'file.get_relation.source'      => 'GET /stub-rest-files/:id/source',
			'file.update_all'               => 'PATCH /stub-rest-files',
			'file.update_one'               => 'PATCH /stub-rest-files/:id',
		], $operations);

		$get_all_params = \array_column($spec['paths']['/stub-rest-files']['get']['parameters'], 'name');

		foreach (['max', 'page', 'filters', 'order_by', 'cursor', 'relations'] as $param) {
			self::assertContains($param, $get_all_params);
		}
	}

	public function testConfigureRouteOnlyConfiguresThatActionRoute(): void
	{
		$types = static fn (RESTFulAction $action) => \array_column(
			self::route(StubFilesRESTService::routeName($action))->getOptions()->getGuardDescriptors(),
			'type'
		);

		self::assertSame(['role'], $types(RESTFulAction::DELETE_ALL));
		self::assertSame([], $types(RESTFulAction::GET_ALL));
		self::assertSame([], $types(RESTFulAction::DELETE_ONE));
	}

	public function testHooksRunAroundTheAction(): void
	{
		$route = self::route(StubUsersRESTService::routeName(RESTFulAction::GET_ALL));
		$ri    = new RouteInfo(context(), $route, []);

		StubUsersRESTService::$calls = [];

		$response = ($route->getHandler())($ri);

		self::assertInstanceOf(Response::class, $response);
		self::assertSame(['before:get_all', 'action:get_all', 'after:get_all'], StubUsersRESTService::$calls);
	}

	private static function route(string $name): ?Route
	{
		return context()->getRouter()->getRoute($name);
	}
}

/**
 * RESTFul service stub recording its action hooks, with two actions disabled.
 *
 * @internal
 */
final class StubUsersRESTService extends RESTFulService
{
	public const SERVICE_PATH = '/stub-rest-users';
	public const TABLE_NAME   = 'oz_users';
	public const KEY_COLUMN   = 'id';

	/**
	 * @var list<string>
	 */
	public static array $calls = [];

	protected static array $available_actions = [
		'get_one'      => true,
		'get_all'      => true,
		'get_relation' => false,
		'update_one'   => true,
		'update_all'   => true,
		'delete_one'   => false,
		'delete_all'   => true,
		'create_one'   => true,
	];

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		self::registerRESTRoutes($router);
	}

	/**
	 * Stands in for the database query.
	 */
	#[Override]
	public function actionGetAll(RESTFulAPIRequest $req): void
	{
		self::$calls[] = 'action:get_all';

		$this->json()->setDone();
	}

	#[Override]
	protected function beforeAction(RESTFulAction $action, RESTFulAPIRequest $req): void
	{
		self::$calls[] = 'before:' . $action->value;
	}

	#[Override]
	protected function afterAction(RESTFulAction $action, RESTFulAPIRequest $req): void
	{
		self::$calls[] = 'after:' . $action->value;
	}
}

/**
 * RESTFul service stub restricting one action.
 *
 * @internal
 */
final class StubFilesRESTService extends RESTFulService
{
	public const SERVICE_PATH = '/stub-rest-files';
	public const TABLE_NAME   = 'oz_files';
	public const KEY_COLUMN   = 'id';

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		self::registerRESTRoutes($router);
	}

	#[Override]
	protected static function configureRoute(RESTFulAction $action, RouteOptions $options): void
	{
		if (RESTFulAction::DELETE_ALL === $action) {
			$options->withAdminRole();
		}
	}
}
