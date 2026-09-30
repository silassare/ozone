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

use OZONE\Core\Forms\Form;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteExport;
use OZONE\Core\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteExportTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Router\RouteExport
 */
final class RouteExportTest extends TestCase
{
	public function testExportsTheNamedRoutesWithWhatAClientNeedsToCallThem(): void
	{
		$router = new Router();

		$router->group('/events', static function (Router $router): void {
			$router->get('', static fn () => null)->name('list');
			$router->get('/:id/tickets/:ticket', static fn () => null)
				->name('ticket')
				->param('id', '[0-9]+');
			$router->post('', static fn () => null)
				->name('create')
				->form(new Form())
				->withAuthenticatedUser('user');
			$router->post('/book', static fn () => null)
				->name('book')
				->form(new Form())
				->resumable()
				->guard(static fn () => null);
		})->name('events');

		self::assertSame([
			[
				'name'          => 'events.book',
				'methods'       => ['POST'],
				'path'          => '/events/book',
				'params'        => [],
				'form'          => true,
				'resumable'     => true,
				'guards'        => [],
				'opaque_guards' => 1,
			],
			[
				'name'          => 'events.create',
				'methods'       => ['POST'],
				'path'          => '/events',
				'params'        => [],
				'form'          => true,
				'resumable'     => false,
				'guards'        => [['type' => 'authenticated_user', 'allowed_types' => ['user']]],
				'opaque_guards' => 0,
			],
			[
				'name'          => 'events.list',
				'methods'       => ['GET'],
				'path'          => '/events',
				'params'        => [],
				'form'          => false,
				'resumable'     => false,
				'guards'        => [],
				'opaque_guards' => 0,
			],
			[
				'name'          => 'events.ticket',
				'methods'       => ['GET'],
				'path'          => '/events/:id/tickets/:ticket',
				'params'        => [
					['name' => 'id', 'pattern' => '[0-9]+', 'required' => true, 'global' => false],
					['name' => 'ticket', 'pattern' => '[^/]+', 'required' => true, 'global' => false],
				],
				'form'          => false,
				'resumable'     => false,
				'guards'        => [],
				'opaque_guards' => 0,
			],
		], RouteExport::of($router));
	}

	public function testTellsOptionalAndGlobalParams(): void
	{
		$router = new Router();

		$router->addGlobalParam('lang', '[a-z]{2}', static fn () => 'en');
		$router->get('/:lang/users/:id/articles[/:state[/:page]]', static fn () => null)
			->name('articles')
			->param('page', '[0-9]+');

		self::assertSame([
			['name' => 'lang', 'pattern' => '[a-z]{2}', 'required' => true, 'global' => true],
			['name' => 'id', 'pattern' => '[^/]+', 'required' => true, 'global' => false],
			['name' => 'state', 'pattern' => '[^/]+', 'required' => false, 'global' => false],
			['name' => 'page', 'pattern' => '[0-9]+', 'required' => false, 'global' => false],
		], RouteExport::of($router)[0]['params']);
	}

	public function testLeavesOutAutoNamedAndInternalRoutes(): void
	{
		$router = new Router();

		$router->get('/unnamed', static fn () => null);
		$router->get(OZone::INTERNAL_PATH_PREFIX . 'step', static fn () => null)->name('internal');
		$router->get('/named', static fn () => null)->name('named');

		self::assertSame(['named'], \array_column(RouteExport::of($router), 'name'));
	}
}
