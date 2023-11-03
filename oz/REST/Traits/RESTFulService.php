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

namespace OZONE\Core\REST\Traits;

use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\Utils\ORMClassKind;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\REST\RESTFulAPIRequest;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class RESTFulService.
 */
trait RESTFulService
{
	// ========================================================
	// =	POST REQUEST METHODS
	// ========================================================

	/**
	 * Creates a new entry.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionCreateEntity(RESTFulAPIRequest $req): void
	{
		$values = $req->getFormData($this->table);

		$controller = $this->controller();
		$entity     = $controller->addItem($values);

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
			->setData(['item' => $entity]);
	}

	// ========================================================
	// =	PATCH REQUEST METHODS
	// ========================================================

	/**
	 * Updates only one item in the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionUpdateOneItem(RESTFulAPIRequest $req): void
	{
		$values  = $req->getFormData($this->table);
		$filters = $req->getFilters();

		$controller = $this->controller();
		$entity     = $controller->updateOneItem($filters, $values);

		if (!$entity) {
			throw new NotFoundException();
		}

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
			->setData(['item' => $entity]);
	}

	/**
	 * Updates all items in the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionUpdateAllItems(RESTFulAPIRequest $req): void
	{
		$values   = $req->getFormData($this->table);
		$filters  = $req->getFilters();
		$order_by = $req->getOrderBy();
		$max      = $req->getMax();

		$controller = $this->controller();
		$count      = $controller->updateAllItems($filters, $values, $max, $order_by);

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
			->setData(['affected' => $count]);
	}

	// ========================================================
	// =	DELETE REQUEST METHODS
	// ========================================================

	/**
	 * Deletes only one item in the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionDeleteEntity(RESTFulAPIRequest $req): void
	{
		$filters = $req->getFilters();

		$controller = $this->controller();
		$entity     = $controller->deleteOneItem($filters);

		if (!$entity) {
			throw new NotFoundException();
		}

		$this->json()
			->setDone(
				$controller->getCRUD()
					->getMessage()
			)
			->setData(['item' => $entity]);
	}

	/**
	 * Deletes all items in the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionDeleteAll(RESTFulAPIRequest $req): void
	{
		$filters  = $req->getFilters();
		$order_by = $req->getOrderBy();
		$max      = $req->getMax();

		$controller = $this->controller();
		$count      = $controller->deleteAllItems($filters, $max, $order_by);

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
			->setData(['affected' => $count]);
	}

	// ========================================================
	// =	GET REQUEST METHODS
	// ========================================================

	/**
	 * Gets only one item from the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionGetEntity(RESTFulAPIRequest $req): void
	{
		$filters  = $req->getFilters();
		$order_by = $req->getOrderBy();

		$controller = $this->controller();
		$entity     = $controller->getItem($filters, $order_by);

		if (!$entity) {
			throw new NotFoundException();
		}

		$relations = $this->entityNonPaginatedRelations($entity, $req);

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
			->setData([
				'item'      => $entity,
				'relations' => $relations,
			]);
	}

	/**
	 * Gets all items from the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionGetAll(RESTFulAPIRequest $req): void
	{
		$collection = $req->getRequestedCollection();

		$filters       = $req->getFilters();
		$order_by      = $req->getOrderBy();
		$max           = $req->getMax();
		$offset        = $req->getOffset();
		$page          = $req->getPage();
		$total_records = 0;

		$controller = $this->controller();

		if ($collection) {
			$collection = $this->table->getCollection($req->getRequestedCollection());

			if (!$collection) {
				throw new NotFoundException();
			}

			$results = $collection->getItems($req, $total_records);
		} else {
			$results = $controller->getAllItems($filters, $max, $offset, $order_by, $total_records);
		}

		$relations = [];

		if (\count($results)) {
			$relations = $this->entitiesNonPaginatedRelations($results, $req);
		}

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
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
	 * @param RESTFulAPIRequest $req
	 * @param array             $entity_filters
	 * @param string            $relation_name
	 *
	 * @throws Throwable
	 */
	public function actionGetRelation(RESTFulAPIRequest $req, array $entity_filters, string $relation_name): void
	{
		if (empty($entity_filters)) {
			throw new NotFoundException();
		}

		if (!$relation_name) {
			throw new NotFoundException();
		}

		$controller = $this->controller();
		$entity     = $controller->getItem($entity_filters);

		if (!$entity) {
			throw new NotFoundException();
		}

		$max                = $req->getMax();
		$page               = $req->getPage();
		$total_records      = 0;
		$paginated_relation = false;

		if ($this->table->hasRelation($relation_name)) {
			/** @var Relation $found */
			$found = $this->table->getRelation($relation_name);

			if ($found->isPaginated()) {
				$paginated_relation = true;
				$r                  = $this->getRelationItemsList($found, $entity, $req, $total_records);
			} else {
				$r = $this->getRelationItem($found, $entity);
			}
		} elseif ($this->table->hasVirtualRelation($relation_name)) {
			/** @var VirtualRelation $found */
			$found              = $this->table->getVirtualRelation($relation_name);
			$paginated_relation = $found->isPaginated();
			$r                  = $found->get($entity, $req, $total_records);
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

		$this->json()
			->setDone()
			->setData($data);
	}

	/**
	 * Registers RESTFul service routes.
	 *
	 * @param Router $router
	 *
	 * @throws Throwable
	 */
	protected static function registerRESTRoutes(Router $router): void
	{
		$table = db()
			->getTableOrFail(self::TABLE_NAME);
		$key_column  = $table->getColumnOrFail(self::KEY_COLUMN);
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

		$router->group(self::SERVICE_PATH, static function (Router $router) {
			$router->post(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionCreateEntity(self::buildRequest($r));

				return $service->respond();
			});

			$router->get(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionGetAll(self::buildRequest($r));

				return $service->respond();
			});

			$router->patch(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionUpdateAllItems(self::buildRequest($r));

				return $service->respond();
			});

			$router->delete(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionDeleteAll(self::buildRequest($r));

				return $service->respond();
			});

			$router->group('/:' . self::KEY_COLUMN, static function (Router $router) {
				$router->get(static function (RouteInfo $r) {
					$req = self::buildRequest($r, [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					]);

					$service = new self($r);
					$service->actionGetEntity($req);

					return $service->respond();
				});

				$router->patch(static function (RouteInfo $r) {
					$req = self::buildRequest($r, [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					]);

					$service = new self($r);
					$service->actionUpdateOneItem($req);

					return $service->respond();
				});

				$router->delete(static function (RouteInfo $r) {
					$req = self::buildRequest($r, [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					]);

					$service = new self($r);
					$service->actionDeleteEntity($req);

					return $service->respond();
				});

				$router->get('/:relation', static function (RouteInfo $r) {
					$service = new self($r);
					$service->actionGetRelation(self::buildRequest($r), [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					], $r->param('relation'));

					return $service->respond();
				});
			});
		})
			->param('relation', $relation_param)
			->param(self::KEY_COLUMN, $id_param);
	}

	/**
	 * Returns {@link RESTFulAPIRequest} instance.
	 *
	 * @param RouteInfo $ri
	 * @param array     $filters
	 *
	 * @return RESTFulAPIRequest
	 *
	 * @throws ORMQueryException
	 */
	protected static function buildRequest(RouteInfo $ri, array $filters = []): RESTFulAPIRequest
	{
		$form = $ri->getUnsafeFormData();

		$req = new RESTFulAPIRequest($ri->getContext(), $form);

		if (!empty($filters)) {
			$req->ensureOnlyFilters($filters);
		}

		return $req;
	}

	/**
	 * Returns the table controller instance.
	 *
	 * @return ORMController
	 */
	protected function controller(): ORMController
	{
		return new (ORMClassKind::CONTROLLER->getClassFQN($this->table));
	}

	/**
	 * Make sure to load non-paginated relations for a single entity.
	 *
	 * @param ORMEntity         $entity
	 * @param RESTFulAPIRequest $req
	 *
	 * @return array
	 *
	 * @throws BadRequestException
	 */
	protected function entityNonPaginatedRelations(ORMEntity $entity, RESTFulAPIRequest $req): array
	{
		$query_relations = $req->getRequestedRelations();
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
				$results[$name] = $rel->get($entity, $req);
			}
		}

		return $results;
	}

	/**
	 * Make sure to load non-paginated relations for a list of entities.
	 *
	 * @param ORMEntity[]       $entities
	 * @param RESTFulAPIRequest $req
	 *
	 * @return array
	 *
	 * @throws BadRequestException
	 */
	protected function entitiesNonPaginatedRelations(array $entities, RESTFulAPIRequest $req): array
	{
		$query_relations = $req->getRequestedRelations();
		$results         = [];

		if (!empty($query_relations)) {
			$list = $this->resolveRelations($query_relations, false);

			/** @var Relation[] $relations */
			$relations = $list[Relation::class] ?? [];

			/** @var VirtualRelation[] $v_relations */
			$v_relations = $list[VirtualRelation::class] ?? [];

			foreach ($relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{self::KEY_COLUMN};
					$results[$name][$id] = $this->getRelationItem($rel, $entity);
				}
			}

			foreach ($v_relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{self::KEY_COLUMN};
					$results[$name][$id] = $rel->get($entity, $req);
				}
			}
		}

		return $results;
	}

	/**
	 * Gets a relation items list.
	 *
	 * @param Relation          $relation
	 * @param ORMEntity         $entity
	 * @param RESTFulAPIRequest $req
	 * @param null|int          $total_records
	 *
	 * @return array
	 *
	 * @throws ORMQueryException
	 */
	protected function getRelationItemsList(
		Relation $relation,
		ORMEntity $entity,
		RESTFulAPIRequest $req,
		?int &$total_records = null
	): array {
		$req             = $req->createScopedInstance($relation->getName());
		$relation_getter = $relation->getGetterName();

		return \call_user_func_array([
			$entity,
			$relation_getter,
		], [
			$req->getFilters(),
			$req->getMax(),
			$req->getOffset(),
			$req->getOrderBy(),
			&$total_records,
		]);
	}

	/**
	 * Get relation item.
	 *
	 * @param Relation  $relation
	 * @param ORMEntity $entity
	 *
	 * @return mixed
	 */
	protected function getRelationItem(Relation $relation, ORMEntity $entity): mixed
	{
		$relation_getter = $relation->getGetterName();

		return $entity->{$relation_getter}();
	}

	/**
	 * Resolve relations.
	 *
	 * @param array $relations_names_list
	 * @param bool  $allow_paginated
	 *
	 * @return array
	 *
	 * @throws BadRequestException
	 */
	protected function resolveRelations(array $relations_names_list, bool $allow_paginated): array
	{
		$table       = $this->table;
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
				throw new BadRequestException(
					'OZ_RELATION_IS_PAGINATED_AND_SHOULD_BE_RETRIEVED_WITH_DEDICATED_ENDPOINT',
					['relation' => $name]
				);
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
