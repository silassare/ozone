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

use Gobl\DBAL\Relations\Interfaces\RelationInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMRequest;
use Gobl\ORM\Utils\ORMClassKind;
use InvalidArgumentException;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\RESTFulAPIRequest;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use PHPUtils\Str;
use Throwable;

/**
 * Class RESTFulService.
 */
trait RESTFulService
{
	protected static array $available_actions = [
		'get_one'      => true,
		'get_all'      => true,
		'get_relation' => true,
		'update_one'   => true,
		'update_all'   => true,
		'delete_one'   => true,
		'delete_all'   => true,
		'create_one'   => true,
	];

	/**
	 * Gets the route name for the given action.
	 */
	public static function routeName(string $action): string
	{
		if (!isset(static::$available_actions[$action])) {
			throw new InvalidArgumentException('Invalid action: ' . $action);
		}

		return static::SERVICE_PATH . '.' . $action;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function apiDoc(ApiDoc $doc): void
	{
		$table               = db()->getTableOrFail(self::TABLE_NAME);
		$api_doc_meta        =  $doc->tableMeta($table);
		$singular_name       = $api_doc_meta['singular_name'];
		$plural_name         = $api_doc_meta['plural_name'];
		$a_an                = $api_doc_meta['use_an'] ? 'an' : 'a';
		$operation_id_prefix = Str::stringToURLSlug($singular_name, '_');
		$tag                 = $doc->addTag($plural_name, $api_doc_meta['description']);
		$entity_read         = $doc->entitySchemaForRead(self::TABLE_NAME);
		$entity_create       = $doc->entitySchemaForCreate(self::TABLE_NAME);
		$entity_update       = $doc->entitySchemaForUpdate(self::TABLE_NAME);
		$api_key_column      = self::KEY_COLUMN;

		// create_one
		$doc->addOperationFromRoute(
			self::routeName('create_one'),
			'POST',
			\sprintf('Creates a new `%s`.', $singular_name),
			[
				$doc->success(
					$doc->object(['item' => $entity_read]),
					\sprintf('The `%s` was created successfully.', $singular_name)
				),
			],
			[
				'tags'        => [$tag->name],
				'summary'     => 'Create',
				'operationId' => \sprintf('%s.create_one', $operation_id_prefix),
				'requestBody' => $doc->requestBody([
					$doc->json($entity_create),
				]),
			]
		);

		// get_all
		$doc->addOperationFromRoute(
			self::routeName('get_all'),
			'GET',
			\sprintf('Gets all `%s`.', $plural_name),
			[
				$doc->success(
					$doc->apiPaginated($entity_read),
					\sprintf('All `%s` were retrieved successfully.', $plural_name)
				),
			],
			[
				'tags'        => [$tag->name],
				'summary'     => 'List',
				'operationId' => \sprintf('%s.get_all', $operation_id_prefix),
			]
		);

		// update_all
		$doc->addOperationFromRoute(
			self::routeName('update_all'),
			'PATCH',
			\sprintf('Updates all `%s`.', $plural_name),
			[
				$doc->success(
					$doc->object(['affected' => $doc->integer('The number of affected rows.')]),
					\sprintf('All `%s` were updated successfully.', $plural_name)
				),
			],
			[
				'tags'        => [$tag->name],
				'requestBody' => $doc->requestBody([
					$doc->json($entity_update),
				]), ]
		);

		// delete_all
		$doc->addOperationFromRoute(
			self::routeName('delete_all'),
			'DELETE',
			\sprintf('Deletes all `%s`.', $plural_name),
			[
				$doc->success(
					$doc->object(['affected' => $doc->integer('The number of affected rows.')]),
					\sprintf('All `%s` were deleted successfully.', $plural_name)
				),
			],
			['tags' => [$tag->name]]
		);

		// get_one
		$doc->addOperationFromRoute(
			self::routeName('get_one'),
			'GET',
			\sprintf('Gets the `%s` with the given `:%s`.', $singular_name, $api_key_column),
			[
				$doc->success(
					$doc->object(['item' => $entity_read]),
					\sprintf('The `%s` was retrieved successfully.', $singular_name)
				),
			],
			['tags' => [$tag->name]]
		);

		// update_one
		$doc->addOperationFromRoute(
			self::routeName('update_one'),
			'PATCH',
			\sprintf('Updates the `%s` with the given `:%s`.', $singular_name, $api_key_column),
			[
				$doc->success(
					$doc->object(['item' => $entity_read]),
					\sprintf('The `%s` was updated successfully.', $singular_name)
				),
			],
			[
				'tags'        => [$tag->name],
				'requestBody' => $doc->requestBody([
					$doc->json($entity_update),
				]),
			]
		);

		// delete_one
		$doc->addOperationFromRoute(
			self::routeName('delete_one'),
			'DELETE',
			\sprintf('Deletes the `%s` with the given `:%s`.', $singular_name, $api_key_column),
			[
				$doc->success(
					$doc->object(['item' => $entity_read]),
					\sprintf('The `%s` was deleted successfully.', $singular_name)
				),
			],
			['tags' => [$tag->name]]
		);

		$relations           = $table->getRelations();
		$v_relations         = $table->getVirtualRelations();
		$relations_responses = [];
		foreach ($relations as $relation) {
			$relation_name                 = $relation->getName();
			$relation_target_entity_schema = $doc->entitySchemaForRead($relation->getTargetTable());

			$relations_responses[] = $doc->success(
				$relation->isPaginated() ? $doc->apiPaginated(
					$relation_target_entity_schema,
					$relation_name
				) : $doc->object([
					$relation_name => $relation_target_entity_schema,
				]),
				\sprintf('The `%s` was retrieved successfully.', $relation_name)
			);
		}

		foreach ($v_relations as $vr) {
			$vr_name               = $vr->getName();
			$vr_relative_schema    = $doc->typeSchema($vr->getRelativeType());
			$relations_responses[] = $doc->success(
				$vr->isPaginated() ? $doc->apiPaginated($vr_relative_schema, $vr_name) : $doc->object([
					$vr_name => $vr_relative_schema,
				]),
				\sprintf('The `%s` was retrieved successfully.', $vr_name)
			);
		}

		$doc->addOperationFromRoute(
			self::routeName('get_relation'),
			'GET',
			\sprintf('Gets the `:relation` for the `%s` with the given `:%s`.', $singular_name, $api_key_column),
			$relations_responses,
			['tags' => [$tag->name]]
		);
	}

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
	public function actionCreateOne(RESTFulAPIRequest $req): void
	{
		$db = ORM::getDatabase($this->table->getNamespace());

		$db->runInTransaction(function () use ($req) {
			$controller = $this->controller();
			$values     = $req->getFormData($this->table);
			$entity     = $controller->addItem($values);

			$this->processRelations($entity, $req, false);

			$this->json()
				->setDone(
					$controller
						->getCRUD()
						->getMessage()
				)
				->setData(['item' => $entity]);
		});
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
	public function actionUpdateOne(RESTFulAPIRequest $req): void
	{
		$db = ORM::getDatabase($this->table->getNamespace());

		$db->runInTransaction(function () use ($req) {
			$values  = $req->getFormData($this->table);
			$filters = $req->getFilters();

			$controller = $this->controller();
			$entity     = $controller->updateOneItem($filters, $values);

			if (!$entity) {
				throw new NotFoundException();
			}

			$this->processRelations($entity, $req, false);

			$this->json()
				->setDone(
					$controller
						->getCRUD()
						->getMessage()
				)
				->setData(['item' => $entity]);
		});
	}

	/**
	 * Updates all items in the table that matches some filters.
	 *
	 * @param RESTFulAPIRequest $req
	 *
	 * @throws Throwable
	 */
	public function actionUpdateAll(RESTFulAPIRequest $req): void
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
	public function actionDeleteOne(RESTFulAPIRequest $req): void
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
	public function actionGetOne(RESTFulAPIRequest $req): void
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

			if ($paginated_relation) {
				$r = $found->getController()->list($entity, $req, $total_records);
			} else {
				$r = $found->getController()->get($entity, $req);
			}
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
				$service->actionCreateOne(self::buildRequest($r));

				return $service->respond();
			})->name(self::routeName('create_one'));

			$router->get(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionGetAll(self::buildRequest($r));

				return $service->respond();
			})->name(self::routeName('get_all'));

			$router->patch(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionUpdateAll(self::buildRequest($r));

				return $service->respond();
			})->name(self::routeName('update_all'));

			$router->delete(static function (RouteInfo $r) {
				$service = new self($r);
				$service->actionDeleteAll(self::buildRequest($r));

				return $service->respond();
			})->name(self::routeName('delete_all'));

			$router->group('/:' . self::KEY_COLUMN, static function (Router $router) {
				$router->get(static function (RouteInfo $r) {
					$req = self::buildRequest($r, [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					]);

					$service = new self($r);
					$service->actionGetOne($req);

					return $service->respond();
				})->name(self::routeName('get_one'));

				$router->patch(static function (RouteInfo $r) {
					$req = self::buildRequest($r, [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					]);

					$service = new self($r);
					$service->actionUpdateOne($req);

					return $service->respond();
				})->name(self::routeName('update_one'));

				$router->delete(static function (RouteInfo $r) {
					$req = self::buildRequest($r, [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					]);

					$service = new self($r);
					$service->actionDeleteOne($req);

					return $service->respond();
				})->name(self::routeName('delete_one'));

				$router->get('/:relation', static function (RouteInfo $r) {
					$service = new self($r);
					$service->actionGetRelation(self::buildRequest($r), [
						self::KEY_COLUMN,
						'eq',
						$r->param(self::KEY_COLUMN),
					], $r->param('relation'));

					return $service->respond();
				})->name(self::routeName('get_relation'));
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
				$results[$name] = $rel->getController()->get($entity, $req);
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
					$results[$name][$id] = $rel->getController()->get($entity, $req);
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

	/**
	 * Creates, patches or delete relations.
	 *
	 * @param ORMEntity  $entity
	 * @param ORMRequest $req
	 * @param bool       $patch
	 *
	 * @throws InvalidFormException
	 */
	protected function processRelations(ORMEntity $entity, ORMRequest $req, bool $patch): void
	{
		$table = $this->table;

		/** @var array<RelationInterface> $relations */
		$relations = [...$table->getRelations(), ...$table->getVirtualRelations()];

		foreach ($relations as $relation) {
			$relation_name    = $relation->getName();
			$relation_payload = $req->getFormField($relation_name);

			if ($relation_payload) {
				if ($relation->isPaginated()) {
					if (!\is_array($relation_payload)) {
						throw new InvalidFormException(
							'OZ_RELATION_IS_PAGINATED_ARRAY_OF_ARRAY_EXPECTED',
							['relation' => $relation_name]
						);
					}

					foreach ($relation_payload as $rel_entry) {
						if (empty($rel_entry)) {
							continue;
						}

						if (!\is_array($rel_entry)) {
							throw new InvalidFormException(
								'OZ_RELATION_IS_PAGINATED_ARRAY_OF_ARRAY_EXPECTED',
								['relation' => $relation_name]
							);
						}

						$this->processRelative($relation, $entity, $rel_entry, $patch);
					}
				} else {
					$rel_entry = $relation_payload;
					if (empty($rel_entry)) {
						continue;
					}
					if (!\is_array($rel_entry)) {
						throw new InvalidFormException(
							'OZ_RELATION_ARRAY_EXPECTED',
							['relation' => $relation_name]
						);
					}

					$this->processRelative($relation, $entity, $rel_entry, $patch);
				}
			}
		}
	}

	/**
	 * Creates, patches or delete relations.
	 *
	 * @throws InvalidFormException
	 */
	private function processRelative(
		RelationInterface $relation,
		ORMEntity $entity,
		array $rel_entry,
		bool $patch
	): void {
		try {
			$r_ctrl = $relation->getController();
			if ($patch) {
				$delete = $rel_entry[ORMRequest::DELETE_PARAM] ?? false;
				if ($delete) {
					$r_ctrl->delete($entity, $rel_entry);
				} else {
					$r_ctrl->update($entity, $rel_entry);
				}
			} else {
				$r_ctrl->create($entity, $rel_entry);
			}
		} catch (Throwable $t) {
			$data = [
				'relation' => $relation->getName(),
			];

			if ($t instanceof ORMQueryException) {
				$data['previous'] = [
					'error' => $t->getMessage(),
					'data'  => $t->getData(),
				];
			}

			throw new InvalidFormException('OZ_RELATION_PROCESSING_FAILED', $data, $t);
		}
	}
}
