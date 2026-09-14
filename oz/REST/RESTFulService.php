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
use Override;
use OZONE\Core\Access\AtomicAction;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Http\Response;
use OZONE\Core\Lang\I18n;
use OZONE\Core\REST\Enums\RESTFulAction;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\RouteOptions;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class RESTFulService.
 *
 * CRUD routes and API docs for a table, one per {@see RESTFulAction}. A subclass can hook
 * around each action ({@see self::beforeAction()}, {@see self::afterAction()}) and configure
 * each action's route ({@see self::configureRoute()}).
 */
abstract class RESTFulService extends Service
{
	public const SERVICE_PATH = '/svc-path-sample';
	public const TABLE_NAME   = 'table_name_sample';
	public const KEY_COLUMN   = 'id';

	/**
	 * The actions by name, and whether they are enabled. To disable one, a subclass redeclares
	 * this property with that action set to false: changing the inherited array would change
	 * every service.
	 *
	 * @var array<string, bool>
	 */
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
	public static function routeName(RESTFulAction|string $action): string
	{
		$action = $action instanceof RESTFulAction ? $action->value : $action;

		if (!isset(static::$available_actions[$action])) {
			throw new InvalidArgumentException('Invalid action: ' . $action);
		}

		return static::SERVICE_PATH . '.' . $action;
	}

	/**
	 * Whether the action is enabled: a disabled action has no route and no docs.
	 */
	public static function isActionEnabled(RESTFulAction|string $action): bool
	{
		$action = $action instanceof RESTFulAction ? $action->value : $action;

		return !empty(static::$available_actions[$action]);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		(new RESTFulApiDoc($doc, static::class))->document();
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
	 * Called before an action runs, e.g. to check the request further.
	 *
	 * @psalm-suppress PossiblyUnusedParam
	 */
	protected function beforeAction(RESTFulAction $action, RESTFulAPIRequest $req): void {}

	/**
	 * Called once an action succeeded, before the response is built: `$this->json()` holds the
	 * action's response data.
	 *
	 * @psalm-suppress PossiblyUnusedParam
	 */
	protected function afterAction(RESTFulAction $action, RESTFulAPIRequest $req): void {}

	/**
	 * Called for each action when the routes are registered, to add guards, a form, middlewares,
	 * ... to that action's route only.
	 *
	 * @psalm-suppress PossiblyUnusedParam
	 */
	protected static function configureRoute(RESTFulAction $action, RouteOptions $options): void {}

	/**
	 * Processes a paginated list to prepare response data.
	 *
	 * @param PaginationAwareListInterface $list the paginated list to process
	 * @param RESTFulAPIRequest            $req  the API request containing pagination parameters
	 */
	protected function preparePaginatedListResponseData(
		PaginationAwareListInterface $list,
		RESTFulAPIRequest $req
	): array {
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
	 * Registers the routes of the enabled actions: the collection actions under
	 * {@see self::SERVICE_PATH}, the entry actions under `/:<key column>`.
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
			foreach (RESTFulAction::cases() as $action) {
				if (!$action->onEntry() && static::isActionEnabled($action)) {
					self::registerAction($router, $action);
				}
			}

			$router->group('/:' . static::KEY_COLUMN, static function (Router $router): void {
				foreach (RESTFulAction::cases() as $action) {
					if ($action->onEntry() && static::isActionEnabled($action)) {
						self::registerAction($router, $action);
					}
				}
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
	 * Registers the route of one action.
	 */
	private static function registerAction(Router $router, RESTFulAction $action): void
	{
		$options = $router->map(
			$action->httpMethod(),
			$action->path(),
			static fn (RouteInfo $ri) => (new static($ri))->runAction($action, $ri)
		)->name(static::routeName($action));

		static::configureRoute($action, $options);
	}

	/**
	 * Runs an action and its hooks, and builds the response.
	 *
	 * @throws Throwable
	 */
	private function runAction(RESTFulAction $action, RouteInfo $ri): Response
	{
		$entry_filters = $action->onEntry()
			? [static::KEY_COLUMN, 'eq', $ri->param(static::KEY_COLUMN)]
			: [];

		// get_relation looks the entry up itself: its request filters apply to the relatives.
		$req = static::buildRequest($ri, RESTFulAction::GET_RELATION === $action ? [] : $entry_filters);

		$this->beforeAction($action, $req);

		match ($action) {
			RESTFulAction::CREATE_ONE   => $this->actionCreateOne($req),
			RESTFulAction::GET_ALL      => $this->actionGetAll($req),
			RESTFulAction::UPDATE_ALL   => $this->actionUpdateAll($req),
			RESTFulAction::DELETE_ALL   => $this->actionDeleteAll($req),
			RESTFulAction::GET_ONE      => $this->actionGetOne($req),
			RESTFulAction::UPDATE_ONE   => $this->actionUpdateOne($req),
			RESTFulAction::DELETE_ONE   => $this->actionDeleteOne($req),
			RESTFulAction::GET_RELATION => $this->actionGetRelation($req, $entry_filters, $ri->param('relation')),
		};

		$this->afterAction($action, $req);

		return $this->respond();
	}
}
