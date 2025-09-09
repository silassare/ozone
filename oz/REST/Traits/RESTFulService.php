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
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\ORM\Utils\ORMClassKind;
use InvalidArgumentException;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Schema;
use OZONE\Core\Access\AtomicAction;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\REST\RESTFulAPIRequest;
use OZONE\Core\REST\RESTFullRelationsHelper;
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
		$table = db()->getTableOrFail(self::TABLE_NAME);

		if (!$table->getMeta()->get('api.doc.enabled', true)) {
			return;
		}

		$api_doc_meta        = $doc->tableMeta($table);
		$singular_name       = $api_doc_meta['singular_name'];
		$plural_name         = $api_doc_meta['plural_name'];
		$a_an                = $api_doc_meta['use_an'] ? 'an' : 'a';
		$operation_id_prefix = Str::stringToURLSlug($singular_name, '_');
		$tag                 = $doc->addTag($plural_name, $api_doc_meta['description']);
		$entity_read         = $doc->entitySchemaForRead(self::TABLE_NAME);
		$entity_create       = $doc->entitySchemaForCreate(self::TABLE_NAME);
		$entity_update       = $doc->entitySchemaForUpdate(self::TABLE_NAME);
		$pk_key_column       = self::KEY_COLUMN;
		$collections         = $table->getCollections();

		$get_one_params = [];
		$get_all_params = [
			$doc->apiMaxParameter(),
			$doc->apiPageParameter(),
			$doc->apiFiltersParameter(),
			$doc->apiOrderByParameter(),
		];
		$update_one_params = [];
		$update_all_params = [
			$doc->apiMaxParameter(),
			$doc->apiFiltersParameter(),
			$doc->apiOrderByParameter(),
		];
		$delete_one_params = [];
		$delete_all_params = [
			$doc->apiMaxParameter(),
			$doc->apiFiltersParameter(),
			$doc->apiOrderByParameter(),
		];
		if (!empty($collections)) {
			$get_all_params[] = $doc->apiCollectionParameter(
				'query',
				\array_map(static fn ($c) => $c->getName(), $collections)
			);
		}

		$relatives_options   = self::relationsDocOptions($table, $doc);
		$relations           = $relatives_options['relations'];
		$v_relations         = $relatives_options['v_relations'];

		$non_paginated_relations_schemas = $relatives_options['non_paginated_relations_schemas'];

		if (isset($relatives_options['relations_parameter'])) {
			$get_one_params[] = $get_all_params[] = $relatives_options['relations_parameter'];
		}

		// create_one
		if ($table->getMeta()->get('api.doc.create_one.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('create_one'),
				'POST',
				\sprintf('Create %s', $singular_name),
				[
					$doc->success(
						$doc->object(['item' => $entity_read]),
						\sprintf('The `%s` was created successfully.', $singular_name)
					),
				],
				[
					'tags'            => [$tag->name],
					'description'     => \sprintf('Create new `%s`.', $singular_name),
					'operationId'     => \sprintf('%s.create_one', $operation_id_prefix),
					'requestBody'     => $doc->requestBody([
						$doc->json($entity_create),
					]),
				]
			);
		}

		// get_one
		if ($table->getMeta()->get('api.doc.get_one.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('get_one'),
				'GET',
				\sprintf('Get %s', $singular_name),
				[
					$doc->success(
						$doc->object([
							'item'      => $entity_read,
							'relations' => $doc->object($non_paginated_relations_schemas),
						]),
						\sprintf('The `%s` was retrieved successfully.', $singular_name)
					),
				],
				[
					'tags'        => [$tag->name],
					'description' => \sprintf(
						'Get %s `%s` identified by a given `%s`.',
						$a_an,
						$singular_name,
						ApiDoc::toHumanReadable($pk_key_column)
					),
					'operationId' => \sprintf('%s.get_one', $operation_id_prefix),
					'parameters'  => $get_one_params,
				]
			);
		}

		// update_one
		if ($table->getMeta()->get('api.doc.update_one.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('update_one'),
				'PATCH',
				\sprintf('Update %s', $singular_name),
				[
					$doc->success(
						$doc->object(['item' => $entity_read]),
						\sprintf('The `%s` was updated successfully.', $singular_name)
					),
				],
				[
					'tags'        => [$tag->name],
					'description' => \sprintf(
						'Update %s `%s` identified by a given `%s`',
						$a_an,
						$singular_name,
						ApiDoc::toHumanReadable($pk_key_column)
					),
					'operationId' => \sprintf('%s.update_one', $operation_id_prefix),
					'requestBody' => $doc->requestBody([
						$doc->json($entity_update),
					]),
					'parameters' => $update_one_params,
				]
			);
		}

		// delete_one
		if ($table->getMeta()->get('api.doc.delete_one.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('delete_one'),
				'DELETE',
				\sprintf('Delete %s', $singular_name),
				[
					$doc->success(
						$doc->object(['item' => $entity_read]),
						\sprintf('The `%s` was deleted successfully.', $singular_name)
					),
				],
				[
					'tags'        => [$tag->name],
					'description' => \sprintf(
						'Delete %s `%s` identified by a given `%s`.',
						$a_an,
						$singular_name,
						ApiDoc::toHumanReadable($pk_key_column)
					),
					'operationId' => \sprintf('%s.create_one', $operation_id_prefix),
					'parameters'  => $delete_one_params,
				]
			);
		}

		// get_all
		if ($table->getMeta()->get('api.doc.get_all.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('get_all'),
				'GET',
				\sprintf('List %s', $plural_name),
				[
					$doc->success(
						$doc->apiPaginated([
							'items'     => $doc->array($entity_read),
							'relations' => $doc->object(\array_map(
								static fn ($s) => $doc->object(['{' . $pk_key_column . '}' => $s]),
								$non_paginated_relations_schemas
							)),
						]),
						\sprintf('All `%s` were retrieved successfully.', $plural_name)
					),
				],
				[
					'tags'        => [$tag->name],
					'description' => \sprintf('Gets all `%s` that matches a given filters.', $plural_name),
					'operationId' => \sprintf('%s.get_all', $operation_id_prefix),
					'parameters'  => $get_all_params,
				]
			);
		}

		// update_all
		if ($table->getMeta()->get('api.doc.update_all.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('update_all'),
				'PATCH',
				\sprintf('Update %s', $plural_name),
				[
					$doc->success(
						$doc->object(['affected' => $doc->integer('The number of affected rows.')]),
						\sprintf('All `%s` were updated successfully.', $plural_name)
					),
				],
				[
					'tags'        => [$tag->name],
					'description' => \sprintf('Update all `%s` that matches a given filters.`', $plural_name),
					'operationId' => \sprintf('%s.update_all', $operation_id_prefix),
					'requestBody' => $doc->requestBody([
						$doc->json($entity_update),
					]),
					'parameters' => $update_all_params,
				]
			);
		}

		// delete_all
		if ($table->getMeta()->get('api.doc.delete_all.enabled', true)) {
			$doc->addOperationFromRoute(
				self::routeName('delete_all'),
				'DELETE',
				\sprintf('Delete %s', $plural_name),
				[
					$doc->success(
						$doc->object(['affected' => $doc->integer('The number of affected rows.')]),
						\sprintf('All `%s` were deleted successfully.', $plural_name)
					),
				],
				[
					'tags'        => [$tag->name],
					'description' => \sprintf('Delete all `%s` that matches a given filters.', $plural_name),
					'operationId' => \sprintf('%s.delete_all', $operation_id_prefix),
					'parameters'  => $delete_all_params,
				]
			);
		}

		$add_relative_operation = static function (
			RelationInterface $r,
			Schema $r_schema
		) use (
			$table,
			$operation_id_prefix,
			$tag,
			$pk_key_column,
			$singular_name,
			$doc,
		) {
			$r_name = $r->getName();
			if (!$table->getMeta()->get(\sprintf('api.doc.get_relation.%s.enabled', $r_name), true)) {
				return;
			}

			$r_params = $r->isPaginated() ? [
				$doc->apiMaxParameter(),
				$doc->apiPageParameter(),
				$doc->apiFiltersParameter(),
				$doc->apiOrderByParameter(),
			] : [
				$doc->apiFiltersParameter(),
				$doc->apiOrderByParameter(),
			];

			/** @var null|Schema $r_relations_schema */
			$r_relations_schema = null;
			$r_table            = $r->getController()->getRelativesStoreTable();

			if ($r_table?->hasSinglePKColumn()) {
				$r_pk_column         = $r_table->getSinglePKColumnOrFail()->getName();
				$r_relatives_options = self::relationsDocOptions($r_table, $doc);

				if (isset($rr_options['relations_parameter'])) {
					$r_params[] = $rr_options['relations_parameter'];
				}
				$r_relations_schema = $r->isPaginated() ? $doc->object(\array_map(
					static fn ($s) => $doc->object(['{' . $r_pk_column . '}' => $s]),
					$r_relatives_options['non_paginated_relations_schemas']
				)) : $doc->object($r_relatives_options['non_paginated_relations_schemas']);
			}

			$doc->addOperationFromRoute(
				self::routeName('get_relation'),
				'GET',
				\sprintf('Get %s %s', $singular_name, ApiDoc::toHumanReadable($r_name)),
				[
					$doc->success(
						$r->isPaginated() ? $doc->apiPaginated([
							'items'     => $doc->array($r_schema),
							'relations' => $r_relations_schema,
						]) : $doc->object([
							'item'      => $r_schema,
							'relations' => $r_relations_schema,
						]),
						\sprintf(
							'The `%s` of the `%s` was retrieved successfully.',
							ApiDoc::toHumanReadable($r_name),
							$singular_name
						)
					),
				],
				[
					'tags'            => [$tag->name],
					'description'     => \sprintf(
						'Gets the `%s` of the `%s` with the given `%s`.',
						ApiDoc::toHumanReadable($r_name),
						$singular_name,
						ApiDoc::toHumanReadable($pk_key_column)
					),
					'operationId'     => \sprintf('%s.get_relation.%s', $operation_id_prefix, $r_name),
					'parameters'      => $r_params,
				],
				[
					'relation' => $r_name,
				]
			);
		};

		foreach ($relations as $relation) {
			$add_relative_operation($relation, $doc->entitySchemaForRead($relation->getTargetTable()));
		}

		foreach ($v_relations as $vr) {
			$add_relative_operation($vr, $doc->typeSchema($vr->getRelativeType()));
		}
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
			$rrh        = new RESTFullRelationsHelper($this->table);

			$rrh->processRelations($entity, $req, false);

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

			$rrh = new RESTFullRelationsHelper($this->table);
			$rrh->processRelations($entity, $req, false);

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

		$rrh       = new RESTFullRelationsHelper($this->table);
		$relations = $rrh->entityNonPaginatedRelations($entity, $req);

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
		$page          = $req->getPage();
		$offset        = $req->getOffset();
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
			$rrh       = new RESTFullRelationsHelper($this->table);
			$relations = $rrh->entitiesNonPaginatedRelations($results, $req);
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

		$total_records      = 0;
		$paginated_relation = false;

		if ($this->table->hasRelation($relation_name)) {
			/** @var Relation $found */
			$found = $this->table->getRelation($relation_name);

			RESTFullRelationsHelper::assertNotPrivateRelation($found);

			$rrh = new RESTFullRelationsHelper($this->table);

			if ($found->isPaginated()) {
				$paginated_relation = true;
				$r                  = $rrh->getRelationItemsList($found, $entity, $req, false, $total_records);
			} else {
				$r = $rrh->getRelationItem($found, $entity);
			}
		} elseif ($this->table->hasVirtualRelation($relation_name)) {
			/** @var VirtualRelation $found */
			$found = $this->table->getVirtualRelation($relation_name);

			RESTFullRelationsHelper::assertNotPrivateRelation($found);

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

		if ($paginated_relation) {
			$data['items'] = $r;
			$data['page']  = $req->getPage();
			$data['max']   = $req->getMax();
			$data['total'] = $total_records;
		} else {
			$data['item'] = $r;
		}

		$relative_store_table = $found->getController()->getRelativesStoreTable();
		$relative_relations   = [];

		if ($relative_store_table?->hasSinglePKColumn()) {
			$rst_rrh = new RESTFullRelationsHelper($relative_store_table);

			if ($paginated_relation) {
				if (\count($r) > 0) {
					$relative_relations = $rst_rrh->entitiesNonPaginatedRelations($r, $req);
				}
			} else {
				$relative_relations = $rst_rrh->entityNonPaginatedRelations($r, $req);
			}
		}

		$data['relations'] = $relative_relations;

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

		self::registerAccessRightsActions($table);

		$relations_names = [];

		foreach ($table->getRelations(false) as $relation) {
			$relations_names[] = $relation->getName();
		}
		foreach ($table->getVirtualRelations(false) as $relation) {
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
	 * Registers access rights actions for the given table.
	 *
	 * @param Table $table
	 */
	protected static function registerAccessRightsActions(Table $table): void
	{
		$actions = [
			'read',
			'read_all',
			'update',
			'update_all',
			'delete',
			'delete_all',
		];

		$prefix = $table->getMorphType();

		foreach ($actions as $action) {
			$desc = new I18nMessage('OZ_ACCESS_RIGHT_DESCRIPTION', [
				'action' => $action,
			]);
			$error = new I18nMessage('OZ_MISSING_ACCESS_RIGHT', [
				'action' => $action,
			]);

			AtomicActionsRegistry::register(new AtomicAction(\sprintf('%s.%s', $prefix, $action), $desc, $error));
		}
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
	 * @param Table  $table
	 * @param ApiDoc $doc
	 *
	 * @return array{
	 *     relations_parameter: null|Parameter,
	 *     non_paginated_relations_schemas: array<string, Schema>,
	 *     relations: array<int, Relation>,
	 *     v_relations: array<int, VirtualRelation>
	 * }
	 */
	private static function relationsDocOptions(Table $table, ApiDoc $doc): array
	{
		$relations   = $table->getRelations(false);
		$v_relations = $table->getVirtualRelations(false);

		/** @var array<string, Schema> $schemas */
		$schemas = [];
		foreach ($relations as $rl) {
			if (!$rl->isPaginated()) {
				$schemas[$rl->getName()] = $doc->entitySchemaForRead($rl->getTargetTable());
			}
		}
		foreach ($v_relations as $vr) {
			if (!$vr->isPaginated()) {
				$schemas[$vr->getName()] = $doc->typeSchema($vr->getRelativeType());
			}
		}

		$p = !empty($schemas)
			? $doc->apiRelationsParameter('query', \array_keys($schemas))
			: null;

		return [
			'relations_parameter'             => $p,
			'non_paginated_relations_schemas' => $schemas,
			'relations'                       => $relations,
			'v_relations'                     => $v_relations,
		];
	}
}
