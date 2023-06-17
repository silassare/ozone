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

namespace OZONE\Core\App;

use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\Utils\ORMClassKind;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Router\RouteInfo;
use Throwable;

/**
 * Class ORMService.
 */
abstract class ORMService extends Service
{
	private Table $table;

	/**
	 * ORMService constructor.
	 *
	 * @param \OZONE\Core\App\Context|\OZONE\Core\Router\RouteInfo $context
	 * @param string                                               $table_name
	 * @param string                                               $key_column
	 */
	public function __construct(
		RouteInfo|Context $context,
		protected string $table_name,
		protected string $key_column
	) {
		parent::__construct($context);

		$this->table = db()
			->getTableOrFail($this->table_name);
	}

	// ========================================================
	// =	POST REQUEST METHODS
	// ========================================================

	/**
	 * Creates a new entry.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionCreateEntity(ORMRequest $orm_request): void
	{
		$values = $orm_request->getFormData($this->table);

		$controller = $this->controller();
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
	 * Updates only one item in the table that matches some filters.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionUpdateOneItem(ORMRequest $orm_request): void
	{
		$values  = $orm_request->getFormData($this->table);
		$filters = $orm_request->getFilters();

		$controller = $this->controller();
		$entity     = $controller->updateOneItem($filters, $values);

		if (!$entity) {
			throw new NotFoundException();
		}

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData(['item' => $entity]);
	}

	/**
	 * Updates all items in the table that matches some filters.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionUpdateAllItems(ORMRequest $orm_request): void
	{
		$values  = $orm_request->getFormData($this->table);
		$filters = $orm_request->getFilters();

		$controller = $this->controller();
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
	 * Deletes only one item in the table that matches some filters.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionDeleteEntity(ORMRequest $orm_request): void
	{
		$filters = $orm_request->getFilters();

		$controller = $this->controller();
		$entity     = $controller->deleteOneItem($filters);

		if (!$entity) {
			throw new NotFoundException();
		}

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData(['item' => $entity]);
	}

	/**
	 * Deletes all items in the table that matches some filters.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionDeleteAll(ORMRequest $orm_request): void
	{
		$filters = $orm_request->getFilters();

		$controller = $this->controller();
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
	 * Gets only one item from the table that matches some filters.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionGetEntity(ORMRequest $orm_request): void
	{
		$filters  = $orm_request->getFilters();
		$order_by = $orm_request->getOrderBy();

		$controller = $this->controller();
		$entity     = $controller->getItem($filters, $order_by);

		if (!$entity) {
			throw new NotFoundException();
		}

		$relations = $this->entityNonPaginatedRelations($entity, $orm_request);

		$this->getJSONResponse()
			->setDone($controller->getCRUD()
				->getMessage())
			->setData([
				'item'      => $entity,
				'relations' => $relations,
			]);
	}

	/**
	 * Gets all items from the table that matches some filters.
	 *
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @throws Throwable
	 */
	public function actionGetAll(ORMRequest $orm_request): void
	{
		$collection = $orm_request->getRequestedCollection();

		$filters       = $orm_request->getFilters();
		$order_by      = $orm_request->getOrderBy();
		$max           = $orm_request->getMax();
		$offset        = $orm_request->getOffset();
		$page          = $orm_request->getPage();
		$total_records = 0;

		$controller = $this->controller();

		if ($collection) {
			$collection = $this->table->getCollection($orm_request->getRequestedCollection());

			if (!$collection) {
				throw new NotFoundException();
			}

			$results = $collection->getItems($orm_request, $total_records);
		} else {
			$results = $controller->getAllItems($filters, $max, $offset, $order_by, $total_records);
		}

		$relations = [];

		if (\count($results)) {
			$relations = $this->entitiesNonPaginatedRelations($results, $orm_request);
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
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 * @param array                      $entity_filters
	 * @param string                     $relation_name
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 */
	public function actionGetRelation(ORMRequest $orm_request, array $entity_filters, string $relation_name): void
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

		$max                = $orm_request->getMax();
		$page               = $orm_request->getPage();
		$total_records      = 0;
		$paginated_relation = false;

		if ($this->table->hasRelation($relation_name)) {
			/** @var Relation $found */
			$found = $this->table->getRelation($relation_name);

			if ($found->isPaginated()) {
				$paginated_relation = true;
				$r                  = $this->getRelationItemsList($found, $entity, $orm_request, $total_records);
			} else {
				$r = $this->getRelationItem($found, $entity);
			}
		} elseif ($this->table->hasVirtualRelation($relation_name)) {
			/** @var VirtualRelation $found */
			$found              = $this->table->getVirtualRelation($relation_name);
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
	 * Returns {@link \OZONE\Core\App\ORMRequest} instance.
	 *
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 * @param array                        $filters
	 *
	 * @return \OZONE\Core\App\ORMRequest
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	protected static function toORMRequest(RouteInfo $ri, array $filters = []): ORMRequest
	{
		$form = $ri->getUnsafeFormData();

		$req = new ORMRequest($ri->getContext(), $form);

		if (!empty($filters)) {
			$req->ensureOnlyFilters($filters);
		}

		return $req;
	}

	/**
	 * Returns the table controller instance.
	 *
	 * @return \Gobl\ORM\ORMController
	 */
	private function controller(): ORMController
	{
		return new (ORMClassKind::CONTROLLER->getClassFQN($this->table));
	}

	/**
	 * Make sure to load non-paginated relations for a single entity.
	 *
	 * @param \Gobl\ORM\ORMEntity        $entity
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @return array
	 *
	 * @throws \OZONE\Core\Exceptions\BadRequestException
	 */
	private function entityNonPaginatedRelations(ORMEntity $entity, ORMRequest $orm_request): array
	{
		$query_relations = $orm_request->getRequestedRelations();
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
	 * Make sure to load non-paginated relations for a list of entities.
	 *
	 * @param ORMEntity[]                $entities
	 * @param \OZONE\Core\App\ORMRequest $orm_request
	 *
	 * @return array
	 *
	 * @throws \OZONE\Core\Exceptions\BadRequestException
	 */
	private function entitiesNonPaginatedRelations(array $entities, ORMRequest $orm_request): array
	{
		$query_relations = $orm_request->getRequestedRelations();
		$results         = [];

		if (!empty($query_relations)) {
			$list = $this->resolveRelations($query_relations, false);

			/** @var Relation[] $relations */
			$relations = $list[Relation::class] ?? [];

			/** @var VirtualRelation[] $v_relations */
			$v_relations = $list[VirtualRelation::class] ?? [];

			foreach ($relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{$this->key_column};
					$results[$name][$id] = $this->getRelationItem($rel, $entity);
				}
			}

			foreach ($v_relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{$this->key_column};
					$results[$name][$id] = $rel->get($entity, $orm_request);
				}
			}
		}

		return $results;
	}

	/**
	 * Gets a relation items list.
	 *
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param \Gobl\ORM\ORMEntity           $entity
	 * @param \OZONE\Core\App\ORMRequest    $orm_request
	 * @param null|int                      $total_records
	 *
	 * @return array
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private function getRelationItemsList(
		Relation $relation,
		ORMEntity $entity,
		ORMRequest $orm_request,
		?int &$total_records = null
	): array {
		$orm_request     = $orm_request->createScopedInstance($relation->getName());
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
	 * Get relation item.
	 *
	 * @param \Gobl\DBAL\Relations\Relation $relation
	 * @param \Gobl\ORM\ORMEntity           $entity
	 *
	 * @return mixed
	 */
	private function getRelationItem(Relation $relation, ORMEntity $entity): mixed
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
	 * @throws \OZONE\Core\Exceptions\BadRequestException
	 */
	private function resolveRelations(array $relations_names_list, bool $allow_paginated): array
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
