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

use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\ORM\ORM;
use MY_DB_NS\MyController;
use MY_DB_NS\MyEntity;
use OZONE\OZ\Core\ORMRequest;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\BadRequestException;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
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
final class MyService extends Service
{
	// ========================================================
	// =	POST REQUEST METHODS
	// ========================================================

	/**
	 * Creates a new entry in the table `my_table`.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionCreateEntity(ORMRequest $orm_request): void
	{
		$values = $orm_request->getFormData(self::table());

		$controller = new MyController();
		$entity     = $controller->addItem($values);

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData(['item' => $entity]);
	}

	// ========================================================
	// =	PATCH REQUEST METHODS
	// ========================================================

	/**
	 * Updates only one item in the table `my_table` that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionUpdateOneItem(ORMRequest $orm_request): void
	{
		$orm_request = $orm_request->createScopedInstance(self::table());
		$values      = $orm_request->getFormData();
		$filters     = $orm_request->getFilters();

		$controller = new MyController();
		$entity     = $controller->updateOneItem($filters, $values);

		if ($entity instanceof MyEntity) {
			$this->getJSONResponse()
				->setDone($controller->getCRUD()
					->getMessage())
				->setData(['item' => $entity]);
		} else {
			throw new NotFoundException();
		}
	}

	/**
	 * Updates all items in the table `my_table` that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionUpdateAllItems(ORMRequest $orm_request): void
	{
		$orm_request = $orm_request->createScopedInstance(self::table());
		$values      = $orm_request->getFormData();
		$filters     = $orm_request->getFilters();

		$controller = new MyController();
		$count      = $controller->updateAllItems($filters, $values);

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData(['affected' => $count]);
	}

	// ========================================================
	// =	DELETE REQUEST METHODS
	// ========================================================

	/**
	 * Deletes only one item in the table `my_table` that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionDeleteEntity(ORMRequest $orm_request): void
	{
		$filters = $orm_request->getFilters(self::table());

		$controller = new MyController();
		$entity     = $controller->deleteOneItem($filters);

		if ($entity instanceof MyEntity) {
			$this->getJSONResponse()
				->setDone($controller->getCRUD()
					->getMessage())
				->setData(['item' => $entity]);
		} else {
			throw new NotFoundException();
		}
	}

	/**
	 * Deletes all items in the table `my_table` that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionDeleteAll(ORMRequest $orm_request): void
	{
		$filters = $orm_request->getFilters(self::table());

		$controller = new MyController();
		$count      = $controller->deleteAllItems($filters);

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData(['affected' => $count]);
	}

	// ========================================================
	// =	GET REQUEST METHODS
	// ========================================================

	/**
	 * Gets only one item from the table `my_table` that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionGetEntity(ORMRequest $orm_request): void
	{
		$orm_request = $orm_request->createScopedInstance(self::table());
		$filters     = $orm_request->getFilters();
		$order_by    = $orm_request->getOrderBy();

		$controller = new MyController();
		$entity     = $controller->getItem($filters, $order_by);

		if (!$entity) {
			throw new NotFoundException();
		}

		$relations = $this->entityNotPaginatedRelations($entity, $orm_request);

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData([
				'item'      => $entity,
				'relations' => $relations,
			]);
	}

	/**
	 * Gets all items from the table `my_table` that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionGetAll(ORMRequest $orm_request): void
	{
		$collection = $orm_request->getCollection();

		$orm_request   = $orm_request->createScopedInstance(self::table());
		$filters       = $orm_request->getFilters();
		$order_by      = $orm_request->getOrderBy();
		$max           = $orm_request->getMax();
		$offset        = $orm_request->getOffset();
		$page          = $orm_request->getPage();
		$total_records = 0;

		$controller = new MyController();

		if ($collection) {
			$table      = self::table();
			$collection = $table->getCollection($orm_request->getCollection());

			if (!$collection) {
				throw new NotFoundException();
			}

			$results = $collection->getItems($orm_request, $total_records);
		} else {
			$results = $controller->getAllItems($filters, $max, $offset, $order_by, $total_records);
		}

		$relations = [];

		if (\count($results)) {
			$relations = $this->entitiesNotPaginatedRelations($results, $orm_request);
		}

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData([
				'items'     => $results,
				'max'       => $max,
				'page'      => $page,
				'total'     => $total_records,
				'relations' => $relations,
			]);
	}

	/**
	 * Gets relation item(s) that matches some filters.
	 *
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 * @param string                    $relation_name
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws Throwable
	 */
	public function actionGetRelation(ORMRequest $orm_request, string $relation_name): void
	{
		if (!$orm_request->getColumnFilters('my_pk_column_const')) {
			throw new NotFoundException();
		}

		$filters = $orm_request->getFilters(self::table());

		if (!$relation_name) {
			throw new NotFoundException();
		}

		$controller = new MyController();
		$entity     = $controller->getItem($filters);

		if (!$entity) {
			throw new NotFoundException();
		}

		$max                = $orm_request->getMax();
		$page               = $orm_request->getPage();
		$total_records      = 0;
		$paginated_relation = false;

		$table = self::table();

		if ($table->hasRelation($relation_name)) {
			/** @var Relation $found */
			$found = $table->getRelation($relation_name);

			if ($found->isPaginated()) {
				$paginated_relation = true;
				$r                  = $this->getRelationItemsList($found, $entity, $orm_request, $total_records);
			} else {
				$r = $this->getRelationItem($found, $entity);
			}
		} elseif ($table->hasVirtualRelation($relation_name)) {
			/** @var VirtualRelation $found */
			$found              = $table->getVirtualRelation($relation_name);
			$paginated_relation = $found->isPaginated();
			$r                  = $found->get($entity, $orm_request, $total_records);
		} else {
			throw new NotFoundException();
		}

		if (null === $r) {
			throw new NotFoundException();
		}

		$data[$relation_name] = $r;

		if ($paginated_relation) {
			$data['page']  = $page;
			$data['max']   = $max;
			$data['total'] = $total_records;
		}

		$this->getJSONResponse()
			->setDone()
			->setData($data);
	}

	/**
	 * @return \Gobl\DBAL\Table
	 */
	public static function table(): Table
	{
		return ORM::getDatabase('MY_DB_NS')
			->getTable(MyEntity::TABLE_NAME);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		/** @var \Gobl\DBAL\Column $column */
		$column      = self::table()
			->getColumn('my_pk_column_const');
		$type_obj    = $column->getTypeObject();
		$bigint_type = TypeBigint::class;
		$int_type    = TypeInt::class;
		$is_number   = ($type_obj instanceof $bigint_type || $type_obj instanceof $int_type);

		$id_param       = $is_number ? '[0-9]+' : '[^/]+';
		$relation_param = Relation::NAME_PATTERN;

		$router->post('/my_svc', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());

			$service = new self($context);
			$service->actionCreateEntity($orm_request);

			return $service->respond();
		});
		$router->get('/my_svc/{my_id}', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$orm_request->addColumnFilter('my_id', $r->getParam('my_id'));
			$service = new self($context);
			$service->actionGetEntity($orm_request);

			return $service->respond();
		})
			->param('my_id', $id_param);
		$router->get('/my_svc', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$service     = new self($context);
			$service->actionGetAll($orm_request);

			return $service->respond();
		});
		$router->get('/my_svc/{my_id}/{relation}', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$orm_request->addColumnFilter('my_id', $r->getParam('my_id'));

			$service = new self($context);
			$service->actionGetRelation($orm_request, $r->getParam('relation'));

			return $service->respond();
		})
			->param('my_id', $id_param)
			->param('relation', $relation_param);
		$router->patch('/my_svc/{my_id}', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$orm_request->addColumnFilter('my_id', $r->getParam('my_id'));

			$service = new self($context);
			$service->actionUpdateOneItem($orm_request);

			return $service->respond();
		})
			->param('my_id', $id_param);
		$router->patch('/my_svc', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$service     = new self($context);
			$service->actionUpdateAllItems($orm_request);

			return $service->respond();
		});
		$router->delete('/my_svc/{my_id}', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$orm_request->addColumnFilter('my_id', $r->getParam('my_id'));

			$service = new self($context);
			$service->actionDeleteEntity($orm_request);

			return $service->respond();
		})
			->param('my_id', $id_param);
		$router->delete('/my_svc', function (RouteInfo $r) {
			$context     = $r->getContext();
			$orm_request = new ORMRequest($context, $context->getRequest()
				->getFormData());
			$service     = new self($context);
			$service->actionDeleteAll($orm_request);

			return $service->respond();
		});
	}

	/**
	 * @param \MY_DB_NS\MyEntity        $entity
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @return array
	 *
	 * @throws \OZONE\OZ\Exceptions\BadRequestException
	 */
	private function entityNotPaginatedRelations(MyEntity $entity, ORMRequest $orm_request): array
	{
		$query_relations = $orm_request->getRelations();
		$results         = [];

		if (!empty($query_relations)) {
			$list = $this->resolveRelations($query_relations, false);

			/** @var Relation[] $relations */
			$relations = $list[Relation::class] ?? [];

			/** @var VirtualRelation[] $v_relations */
			$v_relations = $list[VirtualRelation::class] ?? [];

			foreach ($relations as $name => $rel) {
				$results[$name] = $this->getRelationItem($rel, $entity);
			}

			foreach ($v_relations as $name => $rel) {
				$results[$name] = $rel->get($entity, $orm_request);
			}
		}

		return $results;
	}

	/**
	 * @param \MY_DB_NS\MyEntity[]      $entities
	 * @param \OZONE\OZ\Core\ORMRequest $orm_request
	 *
	 * @return array
	 *
	 * @throws \OZONE\OZ\Exceptions\BadRequestException
	 */
	private function entitiesNotPaginatedRelations(array $entities, ORMRequest $orm_request): array
	{
		$query_relations = $orm_request->getRelations();
		$results         = [];

		if (!empty($query_relations)) {
			$list = $this->resolveRelations($query_relations, false);

			/** @var Relation[] $relations */
			$relations = $list[Relation::class] ?? [];

			/** @var VirtualRelation[] $v_relations */
			$v_relations = $list[VirtualRelation::class] ?? [];

			foreach ($relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{'my_id'};
					$results[$name][$id] = $this->getRelationItem($rel, $entity);
				}
			}

			foreach ($v_relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{'my_id'};
					$results[$name][$id] = $rel->get($entity, $orm_request);
				}
			}
		}

		return $results;
	}

	/**
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param \MY_DB_NS\MyEntity            $entity
	 * @param \OZONE\OZ\Core\ORMRequest     $orm_request
	 * @param int                           &$total_records
	 *
	 * @return array
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private function getRelationItemsList(
		Relation $relation,
		MyEntity $entity,
		ORMRequest $orm_request,
		int &$total_records
	): array {
		$target          = $relation->getTargetTable();
		$orm_request     = $orm_request->createScopedInstance($target);
		$relation_getter = $relation->getGetterName();

		return \call_user_func_array([
			$entity,
			$relation_getter,
		], [
			$orm_request->getFilters(),
			$orm_request->getMax(),
			$orm_request->getOffset(),
			$orm_request->getOrderBy(),
			&$total_records,
		]);
	}

	/**
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param \MY_DB_NS\MyEntity            $entity
	 *
	 * @return mixed
	 */
	private function getRelationItem(Relation $relation, MyEntity $entity): mixed
	{
		$relation_getter = $relation->getGetterName();

		return $entity->{$relation_getter}();
	}

	/**
	 * @param array $relations_names_list
	 * @param bool  $allow_paginated
	 *
	 * @return array
	 *
	 * @throws \OZONE\OZ\Exceptions\BadRequestException
	 */
	private function resolveRelations(array $relations_names_list, bool $allow_paginated): array
	{
		$table       = self::table();
		$missing     = [];
		$relations   = [];
		$v_relations = [];

		// we firstly check all relation
		foreach ($relations_names_list as $name) {
			$rel = null;

			if ($table->hasRelation($name)) {
				$rel = $relations[$name] = $table->getRelation($name);
			} elseif ($table->hasVirtualRelation($name)) {
				$rel = $v_relations[$name] = $table->getVirtualRelation($name);
			} else {
				$missing[] = $name;
			}

			if ($rel && !$allow_paginated && $rel->isPaginated()) {
				throw new BadRequestException('OZ_RELATION_IS_PAGINATED_AND_SHOULD_BE_RETRIEVED_WITH_DEDICATED_ENDPOINT', ['relation' => $name]);
			}
		}

		// checks if there are missing relations
		if (\count($missing)) {
			throw new BadRequestException('OZ_RELATION_NOT_DEFINED', ['relations' => $missing]);
		}

		return [
			Relation::class        => $relations,
			VirtualRelation::class => $v_relations,
		];
	}
}
