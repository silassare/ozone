<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// @<%if(@length($.oz_header)){%><%$.oz_header%><%} else {%>
/*
 * Auto generated file
 *
 * WARNING: please don't edit,
 *
 * Proudly With: <%$.oz_version_name%>
 * Time: <%$.oz_time%>
 */
// @<%}%>
declare(strict_types=1);

namespace MY_SERVICE_NS;

use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use OZONE\Core\App\Context;
use OZONE\Core\App\ORMService;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class MyService.
 *
 * to add item to my_svc
 * - POST    /my_svc
 *
 * to update property(ies) of the item with the given :my_id
 * - PATCH     /my_svc/:my_id
 *
 * to update property(ies) of all items in `my_table`
 * - PATCH     /my_svc
 *
 * to delete item with the given :my_id
 * - DELETE  /my_svc/:my_id
 *
 * to delete all items in `my_table`
 * - DELETE  /my_svc
 *
 * to get the item with the given :my_id
 * - GET     /my_svc/:my_id
 *
 * to get all items in my_table
 * - GET     /my_svc
 *
 * to get relation for the item in `my_table` with the given :my_id
 * - GET     /my_svc/:my_id/relation
 */
final class MyService extends ORMService
{
	/**
	 * MyService constructor.
	 *
	 * @param \OZONE\Core\App\Context|\OZONE\Core\Router\RouteInfo $context
	 */
	public function __construct(RouteInfo|Context $context)
	{
		parent::__construct($context, 'my_table', 'my_id');
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public static function registerRoutes(Router $router): void
	{
		$table       = db()
			->getTableOrFail('my_table');
		$key_column  = $table->getColumnOrFail('my_pk_column_const');
		$type_obj    = $key_column->getType();
		$bigint_type = TypeBigint::class;
		$int_type    = TypeInt::class;
		$is_number   = ($type_obj instanceof $bigint_type || $type_obj instanceof $int_type);

		$relations_names = [];

		foreach ($table->getRelations() as $relation) {
			$relations_names[] = $relation->getName();
		}
		foreach ($table->getVirtualRelations() as $relation) {
			$relations_names[] = $relation->getName();
		}

		$id_param       = $is_number ? '[0-9]+' : '[^/]+';
		$relation_param = \implode('|', $relations_names);

		$router->group('/my_svc', static function (Router $router) {
			$router->post(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionCreateEntity(self::toORMRequest($r));

				return $service->respond();
			});

			$router->get(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionGetAll(self::toORMRequest($r));

				return $service->respond();
			});

			$router->patch(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionUpdateAllItems(self::toORMRequest($r));

				return $service->respond();
			});

			$router->delete(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionDeleteAll(self::toORMRequest($r));

				return $service->respond();
			});

			$router->group('/:my_id', static function (Router $router) {
				$router->get(static function (RouteInfo $r) {
					$orm_request = self::toORMRequest($r, [
						'my_id',
						'eq',
						$r->param('my_id'),
					]);

					$service = new self($r);
					$service->actionGetEntity($orm_request);

					return $service->respond();
				});

				$router->patch(static function (RouteInfo $r) {
					$orm_request = self::toORMRequest($r, [
						'my_id',
						'eq',
						$r->param('my_id'),
					]);

					$service = new self($r);
					$service->actionUpdateOneItem($orm_request);

					return $service->respond();
				});
				$router->delete(static function (RouteInfo $r) {
					$orm_request = self::toORMRequest($r, [
						'my_id',
						'eq',
						$r->param('my_id'),
					]);

					$service = new self($r);
					$service->actionDeleteEntity($orm_request);

					return $service->respond();
				});

				$router->get('/:relation', static function (RouteInfo $r) {
					$service = new self($r);
					$service->actionGetRelation(self::toORMRequest($r), [
						'my_id',
						'eq',
						$r->param('my_id'),
					], $r->param('relation'));

					return $service->respond();
				});
			});
		})
			->param('relation', $relation_param)
			->param('my_id', $id_param);
	}
}
