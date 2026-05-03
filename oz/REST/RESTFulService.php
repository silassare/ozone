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

namespace OZONE\Core\REST;

use Gobl\DBAL\Relations\Interfaces\RelationInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Interfaces\PaginationAwareListInterface;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMOptions;
use InvalidArgumentException;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Schema;
use Override;
use OZONE\Core\Access\AtomicAction;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Lang\I18n;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use PHPUtils\Str;
use Throwable;

/**
 * Class RESTFulService.
 */
abstract class RESTFulService extends Service
{
	public const SERVICE_PATH = '/svc-path-sample';
	public const TABLE_NAME   = 'table_name_sample';
	public const KEY_COLUMN   = 'id';

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

	protected Table $table;

	/** Memoized controller instance - safe because a service instance is single-use per request. */
	private ?ORMController $controller_instance = null;

	/**
	 * RESTFulService constructor.
	 *
	 * @param Context|RouteInfo $context
	 */
	protected function __construct(Context|RouteInfo $context)
	{
		parent::__construct($context);

		$this->table = db()->getTableOrFail(static::TABLE_NAME);
	}

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
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$table = db()->getTableOrFail(static::TABLE_NAME);

		if (!$table->getMeta()->get('api.doc.enabled', true)) {
			return;
		}

		$api_doc_meta        = $doc->tableMeta($table);
		$singular_name       = $api_doc_meta['singular_name'];
		$plural_name         = $api_doc_meta['plural_name'];
		$a_an                = $api_doc_meta['use_an'] ? 'an' : 'a';
		$operation_id_prefix = Str::stringToURLSlug($singular_name, '_');
		$tag                 = $doc->addTag($plural_name, $api_doc_meta['description']);
		$entity_read         = $doc->entitySchemaForRead(static::TABLE_NAME);
		$entity_create       = $doc->entitySchemaForCreate(static::TABLE_NAME);
		$entity_update       = $doc->entitySchemaForUpdate(static::TABLE_NAME);
		$pk_key_column       = static::KEY_COLUMN;
		$collections         = $table->getCollections();

		$get_one_params = [];
		$get_all_params = [
			$doc->apiMaxParameter(),
			$doc->apiPageParameter(),
			$doc->apiFiltersParameter('query', $table),
			$doc->apiOrderByParameter(),
			$doc->apiCursorParameter(),
			$doc->apiCursorColumnParameter(),
			$doc->apiCursorDirParameter(),
		];
		$update_one_params = [];
		$update_all_params = [
			$doc->apiMaxParameter(),
			$doc->apiFiltersParameter('query', $table),
			$doc->apiOrderByParameter(),
		];
		$delete_one_params = [];
		$delete_all_params = [
			$doc->apiMaxParameter(),
			$doc->apiFiltersParameter('query', $table),
			$doc->apiOrderByParameter(),
		];
		if (!empty($collections)) {
			$get_all_params[] = $doc->apiCollectionParameter(
				'query',
				\array_map(static fn ($c) => $c->getName(), $collections)
			);
		}

		$relatives_options   = static::relationsDocOptions($table, $doc);
		$relations           = $relatives_options['relations'];
		$v_relations         = $relatives_options['v_relations'];

		$non_paginated_relations_schemas = $relatives_options['non_paginated_relations_schemas'];

		if (isset($relatives_options['relations_parameter'])) {
			$get_one_params[] = $get_all_params[] = $relatives_options['relations_parameter'];
		}

		// create_one
		if ($table->getMeta()->get('api.doc.create_one.enabled', true)) {
			$doc->addOperationFromRoute(
				static::routeName('create_one'),
				'POST',
				\sprintf('Create %s', $singular_name),
				[
					$doc->success(
						$doc->object(['item' => $entity_read]),
						\sprintf('The `%s` was created successfully.', $singular_name),
						'OK',
						201
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
				static::routeName('get_one'),
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
				static::routeName('update_one'),
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
				static::routeName('delete_one'),
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
					'operationId' => \sprintf('%s.delete_one', $operation_id_prefix),
					'parameters'  => $delete_one_params,
				]
			);
		}

		// get_all
		if ($table->getMeta()->get('api.doc.get_all.enabled', true)) {
			$items_with_relations = [
				'items'     => $doc->array($entity_read),
				'relations' => $doc->object(\array_map(
					static fn ($s) => $doc->object(['{' . $pk_key_column . '}' => $s]),
					$non_paginated_relations_schemas
				)),
			];

			$doc->addOperationFromRoute(
				static::routeName('get_all'),
				'GET',
				\sprintf('List %s', $plural_name),
				[
					$doc->success(
						new Schema([
							'oneOf' => [
								$doc->apiPaginated($items_with_relations),
								$doc->apiCursorPaginated($items_with_relations),
							],
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
				static::routeName('update_all'),
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
				static::routeName('delete_all'),
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
		): void {
			$r_name = $r->getName();
			if (!$table->getMeta()->get(\sprintf('api.doc.get_relation.%s.enabled', $r_name), true)) {
				return;
			}

			// ensure not a virtual relation, get the target table to be able to get the customized filters parameters for the relation
			$r_target = $r instanceof Relation ? $r->getTargetTable() : null;

			$r_params = $r->isPaginated() ? [
				$doc->apiMaxParameter(),
				$doc->apiPageParameter(),
				$doc->apiFiltersParameter('query', $r_target),
				$doc->apiOrderByParameter(),
				$doc->apiCursorParameter(),
				$doc->apiCursorColumnParameter(),
				$doc->apiCursorDirParameter(),
			] : [
				$doc->apiFiltersParameter('query', $r_target),
				$doc->apiOrderByParameter(),
			];

			/** @var null|Schema $r_relations_schema */
			$r_relations_schema = null;
			$r_table            = $r->getController()->getRelativesStoreTable();

			if ($r_table?->hasSinglePKColumn()) {
				$r_pk_column         = $r_table->getSinglePKColumnOrFail()->getName();
				$r_relatives_options = static::relationsDocOptions($r_table, $doc);

				if (isset($r_relatives_options['relations_parameter'])) {
					$r_params[] = $r_relatives_options['relations_parameter'];
				}
				$r_relations_schema = $r->isPaginated() ? $doc->object(\array_map(
					static fn ($s) => $doc->object(['{' . $r_pk_column . '}' => $s]),
					$r_relatives_options['non_paginated_relations_schemas']
				)) : $doc->object($r_relatives_options['non_paginated_relations_schemas']);
			}

			$doc->addOperationFromRoute(
				static::routeName('get_relation'),
				'GET',
				\sprintf('Get %s %s', $singular_name, ApiDoc::toHumanReadable($r_name)),
				[
					$doc->success(
						$r->isPaginated() ? new Schema([
							'oneOf' => [
								$doc->apiPaginated(
									['items' => $doc->array($r_schema)]
										+ (null !== $r_relations_schema ? ['relations' => $r_relations_schema] : [])
								),
								$doc->apiCursorPaginated(
									['items' => $doc->array($r_schema)]
										+ (null !== $r_relations_schema ? ['relations' => $r_relations_schema] : [])
								),
							],
						]) : $doc->object(
							['item' => $r_schema]
								+ (null !== $r_relations_schema ? ['relations' => $r_relations_schema] : [])
						),
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
			/** @psalm-suppress InvalidArgument */
			$add_relative_operation($relation, $doc->entitySchemaForRead($relation->getTargetTable()));
		}

		foreach ($v_relations as $vr) {
			$add_relative_operation($vr, $doc->virtualRelationTypeSchema($vr));
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

		$db->runInTransaction(function () use ($req): void {
			$controller = $this->ctrl();
			$values     = $req->getFormData($this->table);
			$entity     = $controller->addItem($values);
			$rrh        = new RESTFulRelationsHelper($this->table);

			$rrh->processRelations($entity, $req, false);

			$this->setResponseStatus(201);

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

		$db->runInTransaction(function () use ($req): void {
			$controller = $this->ctrl();
			$entity     = $controller->updateOneItem($req);

			if (!$entity) {
				throw new NotFoundException();
			}

			$rrh = new RESTFulRelationsHelper($this->table);
			$rrh->processRelations($entity, $req, true);

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
		$db = ORM::getDatabase($this->table->getNamespace());

		$db->runInTransaction(function () use ($req): void {
			$controller = $this->ctrl();
			$count      = $controller->updateAllItems($req);

			$this->json()
				->setDone(
					$controller
						->getCRUD()
						->getMessage()
				)
				->setData(['affected' => $count]);
		});
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
		$controller = $this->ctrl();
		$entity     = $controller->deleteOneItem($req);

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
		$db = ORM::getDatabase($this->table->getNamespace());

		$db->runInTransaction(function () use ($req): void {
			$controller = $this->ctrl();
			$count      = $controller->deleteAllItems($req);

			$this->json()
				->setDone(
					$controller
						->getCRUD()
						->getMessage()
				)
				->setData(['affected' => $count]);
		});
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
		$controller = $this->ctrl();
		$entity     = $controller->getItem($req);

		if (!$entity) {
			throw new NotFoundException();
		}

		$rrh       = new RESTFulRelationsHelper($this->table);
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
		$collection_name = $req->getRequestedCollection();
		$controller      = $this->ctrl();

		if ($collection_name) {
			$collection = $this->table->getCollection($collection_name);

			if (!$collection) {
				throw new NotFoundException();
			}

			$orm_results = $collection->getItems($req);
		} else {
			$orm_results = $controller->getAllItems($req);
		}

		$data  = $this->preparePaginatedListResponseData($orm_results, $req);

		$relations = [];

		if (\count($data['items'])) {
			$rrh       = new RESTFulRelationsHelper($this->table);
			$relations = $rrh->entitiesNonPaginatedRelations($data['items'], $req);
		}

		$data['relations'] = $relations;

		$this->json()
			->setDone(
				$controller
					->getCRUD()
					->getMessage()
			)
			->setData($data);
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

		$controller = $this->ctrl();
		$entity     = $controller->getItem(ORMOptions::makeFromFilters($entity_filters));

		if (!$entity) {
			throw new NotFoundException();
		}

		if ($this->table->hasRelation($relation_name)) {
			/** @var Relation $found */
			$found = $this->table->getRelation($relation_name);

			/** @psalm-suppress InvalidArgument */
			RESTFulRelationsHelper::assertNotPrivateRelation($found);

			$rrh = new RESTFulRelationsHelper($this->table);

			$paginated_relation = $found->isPaginated();
			if ($paginated_relation) {
				$orm_results = $rrh->getRelationItemsList($found, $entity, $req, false);

				if (!$orm_results) {
					throw new NotFoundException();
				}

				/** @psalm-suppress InvalidArgument */
				$data = $this->preparePaginatedListResponseData($orm_results, $req);
			} else {
				$item = $rrh->getRelationItem($found, $entity);

				if (null === $item) {
					throw new NotFoundException();
				}

				$data = [
					'item' => $item,
				];
			}
		} elseif ($this->table->hasVirtualRelation($relation_name)) {
			/** @var VirtualRelation $found */
			$found = $this->table->getVirtualRelation($relation_name);

			RESTFulRelationsHelper::assertNotPrivateRelation($found);

			$paginated_relation = $found->isPaginated();

			if ($paginated_relation) {
				$list = $found->getController()->list($entity, $req);

				if (!$list) {
					throw new NotFoundException();
				}

				$data = $this->preparePaginatedListResponseData($list, $req);
			} else {
				$item = $found->getController()->get($entity, $req);

				if (null === $item) {
					throw new NotFoundException();
				}

				$data = [
					'item' => $item,
				];
			}
		} else {
			throw new NotFoundException();
		}

		$relative_store_table = $found->getController()->getRelativesStoreTable();
		$relative_relations   = [];

		if ($relative_store_table?->hasSinglePKColumn()) {
			$rst_rrh = new RESTFulRelationsHelper($relative_store_table);

			if ($paginated_relation) {
				if (\count($data['items']) > 0) {
					$relative_relations = $rst_rrh->entitiesNonPaginatedRelations($data['items'], $req);
				}
			} else {
				$relative_relations = $rst_rrh->entityNonPaginatedRelations($data['item'], $req);
			}
		}

		$data['relations'] = $relative_relations;

		$this->json()
			->setDone()
			->setData($data);
	}

	/**
	 * Processes a paginated list to prepare response data.
	 *
	 * @param PaginationAwareListInterface $list the paginated list to process
	 * @param RESTFulAPIRequest            $req  the API request containing pagination parameters
	 */
	protected function preparePaginatedListResponseData(PaginationAwareListInterface $list, RESTFulAPIRequest $req): array
	{
		if ($req->isCursorBased()) {
			$data        = $list->getItemsWithCursorMeta($req);
			$data['max'] = $req->getMax();
		} else {
			$data = [
				'items' => \iterator_to_array($list->getItems(), false),
				'page'  => $req->getPage() ?? 1,
				'max'   => $req->getMax(),
				'total' => $list->getTotal($req),
			];
		}

		return $data;
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
			->getTableOrFail(static::TABLE_NAME);
		$key_column  = $table->getColumnOrFail(static::KEY_COLUMN);
		$type_obj    = $key_column->getType();
		$bigint_type = TypeBigint::class;
		$int_type    = TypeInt::class;
		$is_number   = ($type_obj instanceof $bigint_type || $type_obj instanceof $int_type);

		static::registerAccessRightsActions($table);

		$relations_names = [];

		foreach ($table->getRelations(false) as $relation) {
			$relations_names[] = $relation->getName();
		}
		foreach ($table->getVirtualRelations(false) as $relation) {
			$relations_names[] = $relation->getName();
		}

		$id_param       = $is_number ? '[0-9]+' : '[^/]+';
		$relation_param = \implode('|', $relations_names);

		$router->group(static::SERVICE_PATH, static function (Router $router): void {
			$router->post(static function (RouteInfo $r) {
				$service = new static($r);
				$service->actionCreateOne(static::buildRequest($r));

				return $service->respond();
			})->name(static::routeName('create_one'));

			$router->get(static function (RouteInfo $r) {
				$service = new static($r);
				$service->actionGetAll(static::buildRequest($r));

				return $service->respond();
			})->name(static::routeName('get_all'));

			$router->patch(static function (RouteInfo $r) {
				$service = new static($r);
				$service->actionUpdateAll(static::buildRequest($r));

				return $service->respond();
			})->name(static::routeName('update_all'));

			$router->delete(static function (RouteInfo $r) {
				$service = new static($r);
				$service->actionDeleteAll(static::buildRequest($r));

				return $service->respond();
			})->name(static::routeName('delete_all'));

			$router->group('/:' . static::KEY_COLUMN, static function (Router $router): void {
				$router->get(static function (RouteInfo $r) {
					$req = static::buildRequest($r, [
						static::KEY_COLUMN,
						'eq',
						$r->param(static::KEY_COLUMN),
					]);

					$service = new static($r);
					$service->actionGetOne($req);

					return $service->respond();
				})->name(static::routeName('get_one'));

				$router->patch(static function (RouteInfo $r) {
					$req = static::buildRequest($r, [
						static::KEY_COLUMN,
						'eq',
						$r->param(static::KEY_COLUMN),
					]);

					$service = new static($r);
					$service->actionUpdateOne($req);

					return $service->respond();
				})->name(static::routeName('update_one'));

				$router->delete(static function (RouteInfo $r) {
					$req = static::buildRequest($r, [
						static::KEY_COLUMN,
						'eq',
						$r->param(static::KEY_COLUMN),
					]);

					$service = new static($r);
					$service->actionDeleteOne($req);

					return $service->respond();
				})->name(static::routeName('delete_one'));

				$router->get('/:relation', static function (RouteInfo $r) {
					$service = new static($r);
					$service->actionGetRelation(static::buildRequest($r), [
						static::KEY_COLUMN,
						'eq',
						$r->param(static::KEY_COLUMN),
					], $r->param('relation'));

					return $service->respond();
				})->name(static::routeName('get_relation'));
			});
		})
			->param('relation', $relation_param)
			->param(static::KEY_COLUMN, $id_param);
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
			$desc = I18n::m('OZ_ACCESS_RIGHT_DESCRIPTION', [
				'action' => $action,
			]);
			$error = I18n::m('OZ_MISSING_ACCESS_RIGHT', [
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
	protected function ctrl(): ORMController
	{
		return $this->controller_instance ??= ORM::ctrl($this->table);
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
			if ($rl->isPaginated()) {
				continue;
			}

			$schemas[$rl->getName()] = $doc->entitySchemaForRead($rl->getTargetTable());
		}
		foreach ($v_relations as $vr) {
			if ($vr->isPaginated()) {
				continue;
			}

			$schemas[$vr->getName()] = $doc->virtualRelationTypeSchema($vr);
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
