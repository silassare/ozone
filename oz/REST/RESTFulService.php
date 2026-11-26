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
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMOptions;
use InvalidArgumentException;
use Override;
use PHPUtils\Interfaces\RichExceptionInterface;
use OZONE\Core\Access\AtomicAction;
use OZONE\Core\CRUD\TableActions;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
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
	/**
	 * The service's stable name, which its routes are named after (`events.get_all`) and a client
	 * names the service by: a path may change, the name stays. Lowercase words joined by `_` or `.`
	 * (`events`, `shop.orders`). Required, as are the path and the table.
	 */
	public const string SERVICE_NAME = '';

	/** Where its routes are served (`/events`). */
	public const string SERVICE_PATH = '';
	public const string TABLE_NAME   = '';
	public const string KEY_COLUMN   = 'id';

	/** The field of a bulk create that carries its rows, one form each. */
	public const string BULK_ITEMS_FIELD = 'items';

	/** What a {@see self::SERVICE_NAME} looks like. */
	public const string SERVICE_NAME_REG = '~^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$~';

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
		'get_candidates' => true,
		'update_one'   => true,
		'update_all'   => true,
		'delete_one'   => true,
		'delete_all'   => true,
		'create_one'   => true,
		'create_bulk'  => true,
	];

	protected Table $table;

	/** Memoized controller instance - safe because a service instance is single-use per request. */
	private ?ORMController $controller_instance = null;

	/**
	 * RESTFulService constructor.
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

		// An action is one of the enum's: a service's list says which are on, and a list written
		// before an action existed does not make it invalid.
		if (null === RESTFulAction::tryFrom($action)) {
			throw new InvalidArgumentException('Invalid action: ' . $action);
		}

		static::assertDeclared();

		return static::SERVICE_NAME . '.' . $action;
	}

	/**
	 * Refuses a service missing its name, its path or its table: without them it would register
	 * under a placeholder, or under a name a client cannot rely on.
	 */
	public static function assertDeclared(): void
	{
		$class = static::class;

		if (!\preg_match(self::SERVICE_NAME_REG, static::SERVICE_NAME)) {
			throw new RuntimeException(\sprintf(
				'%s::SERVICE_NAME must be lowercase words joined by "_" or "." ("events", "shop.orders"), got "%s".',
				$class,
				static::SERVICE_NAME
			));
		}

		if (!\str_starts_with(static::SERVICE_PATH, '/') || '/' === static::SERVICE_PATH) {
			throw new RuntimeException(\sprintf(
				'%s::SERVICE_PATH must be a path under the root ("/"), got "%s".',
				$class,
				static::SERVICE_PATH
			));
		}

		if ('' === static::TABLE_NAME) {
			throw new RuntimeException(\sprintf('%s::TABLE_NAME is required.', $class));
		}
	}

	/**
	 * Whether the action is enabled: a disabled action has no route and no docs.
	 */
	public static function isActionEnabled(RESTFulAction|string $action): bool
	{
		$action = $action instanceof RESTFulAction ? $action->value : $action;

		return !empty(static::$available_actions[$action]);
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		new RESTFulApiDoc($doc, static::class)->document();
	}

	// ========================================================
	// =	POST REQUEST METHODS
	// ========================================================

	/**
	 * Creates a new entry.
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

	/**
	 * Creates several entries at once, from `items` (one form each).
	 *
	 * Asked for as one bulk create of the table (`{morph type}.create_bulk`, administrators only by
	 * default), then each row as a create of its own, all in one transaction: a row refused leaves
	 * none written, and its error is located at `items.{position}.{field}`. At most
	 * `OZ_DB_CREATE_BULK_MAX_ITEMS` rows.
	 *
	 * @throws Throwable
	 */
	public function actionCreateBulk(RESTFulAPIRequest $req): void
	{
		$items = $req->getFormField(self::BULK_ITEMS_FIELD);

		if (!\is_array($items) || [] === $items || !\array_is_list($items)) {
			throw new InvalidFormException('OZ_FORM_BULK_ITEMS_INVALID', ['field' => self::BULK_ITEMS_FIELD]);
		}

		self::assertBulkSize(\count($items), self::BULK_ITEMS_FIELD);

		$forms = [];

		foreach ($items as $position => $item) {
			if (!\is_array($item)) {
				throw new InvalidFormException('OZ_FORM_BULK_ITEMS_INVALID', [
					'field' => self::BULK_ITEMS_FIELD . '.' . $position,
				]);
			}

			$forms[] = new RESTFulAPIRequest($req->getContext(), $item)->getFormData($this->table);
		}

		$controller = $this->ctrl();

		try {
			$entities = $controller->addItems($forms);
		} catch (RichExceptionInterface $e) {
			throw self::locatedInBulk($e, $this->table, self::BULK_ITEMS_FIELD);
		}

		$this->setResponseStatus(201);

		$this->json()
			->setDone($controller->getCRUD()->getMessage())
			->setData(['items' => $entities]);
	}

	/**
	 * Refuses a batch of more rows than `OZ_DB_CREATE_BULK_MAX_ITEMS` allows (`0` or `null`: no
	 * limit), naming the field that carried them.
	 *
	 * @throws InvalidFormException
	 */
	public static function assertBulkSize(int $count, string $field): void
	{
		$max = (int) Settings::get('oz.db', 'OZ_DB_CREATE_BULK_MAX_ITEMS', 100);

		if ($max > 0 && $count > $max) {
			throw new InvalidFormException('OZ_FORM_BULK_TOO_MANY_ITEMS', [
				'field' => $field,
				'max'   => $max,
				'count' => $count,
			]);
		}
	}

	/**
	 * The error of a refused row of a bulk create, located: its field becomes
	 * `{prefix}.{position}.{field}` (or `{prefix}.{position}` when it names none), its position the
	 * one gobl reported as `bulk_index`.
	 */
	public static function locatedInBulk(RichExceptionInterface $e, Table $table, string $prefix): Throwable
	{
		$position = $e->getData(true)['bulk_index'] ?? null;

		if (!\is_int($position)) {
			return $e;
		}

		$converted = BaseException::tryConvert($e);
		$field     = $converted->getData(true)['field'] ?? null;
		$located   = $prefix . '.' . $position;

		if (\is_string($field) && '' !== $field) {
			// By the name the form uses, not the column's full name.
			$located .= '.' . ($table->getColumn($field)?->getName() ?? $field);
		}

		return $converted->mergeData(['field' => $located]);
	}

	// ========================================================
	// =	PATCH REQUEST METHODS
	// ========================================================

	/**
	 * Updates only one item in the table that matches some filters.
	 *
	 * @throws Throwable
	 */
	public function actionUpdateOne(RESTFulAPIRequest $req): void
	{
		$db = ORM::getDatabase($this->table->getNamespace());

		$db->runInTransaction(function () use ($req): void {
			$controller = $this->ctrl();

			// A request writing only the row's relatives names none of its columns: the row itself
			// is not updated, and the update the ORM would refuse for want of a field is not asked.
			// The row's update check still answers whether the row may be changed through it.
			if (empty($req->getFormData($this->table))) {
				$controller->getCRUD()->assertUpdate(ORM::query($this->table), []);

				$entity = $controller->getItem($req);
			} else {
				$entity = $controller->updateOneItem($req);
			}

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
	 * Gets a relation's candidates: the records the host may be linked to through it.
	 *
	 * The host is the `host` object of the query: the stored row when it names its key -- read as a
	 * row of this service is, so the reader may read it -- with the other values laid over it, as a
	 * form being filled in has them; or, without its key, a row not saved yet. Which records are
	 * candidates is the relation's to decide, on the server, with this request's context; who may
	 * read them is the target table's. The usual list parameters search and page them.
	 *
	 * @throws Throwable
	 */
	public function actionGetCandidates(RESTFulAPIRequest $req, string $relation_name): void
	{
		$relation = $this->table->getRelation($relation_name);

		if (null === $relation || !$relation->hasCandidates()) {
			throw new NotFoundException();
		}

		/** @psalm-suppress InvalidArgument */
		RESTFulRelationsHelper::assertNotPrivateRelation($relation);

		$results = ORM::ctrl($relation->getTargetTable())
			->getCandidates($relation, $this->candidatesHost($req), $req, $this->getContext());

		$this->json()
			->setDone()
			->setData($this->preparePaginatedListResponseData($results, $req));
	}

	/**
	 * The host a relation's candidates are asked for, from the `host` object of the request.
	 *
	 * @throws Throwable
	 */
	private function candidatesHost(RESTFulAPIRequest $req): ORMEntity
	{
		$given = $req->getFormField('host') ?? [];

		if (!\is_array($given)) {
			throw new InvalidFormException('OZ_CANDIDATES_HOST_INVALID');
		}

		foreach ($given as $field => $_) {
			if (!\is_string($field) || !$this->table->hasColumn($field)) {
				throw new InvalidFormException('OZ_CANDIDATES_HOST_INVALID', ['field' => $field]);
			}
		}

		$key = $this->table->getColumnOrFail(static::KEY_COLUMN);
		$id  = $given[$key->getFullName()] ?? $given[$key->getName()] ?? null;

		if (null !== $id) {
			$host = $this->ctrl()->getItem(ORMOptions::makeFromFilters([$key->getFullName() => $id]));

			if (null === $host) {
				throw new NotFoundException();
			}
		} else {
			$host = ORM::entity($this->table);
		}

		unset($given[$key->getFullName()], $given[$key->getName()]);

		// Laid over the stored row and never saved: the host as the form has it.
		$host->hydrate($given);

		return $host;
	}

	/**
	 * Gets relation item(s) that matches some filters.
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
	 */
	protected function beforeAction(RESTFulAction $action, RESTFulAPIRequest $req): void {}

	/**
	 * Called once an action succeeded, before the response is built: `$this->json()` holds the
	 * action's response data.
	 */
	protected function afterAction(RESTFulAction $action, RESTFulAPIRequest $req): void {}

	/**
	 * Called for each action when the routes are registered, to add guards, a form, middlewares,
	 * ... to that action's route only.
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
	 * @throws Throwable
	 */
	protected static function registerRESTRoutes(Router $router): void
	{
		static::assertDeclared();

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

		$candidates_names = self::candidatesRelationNames($table);

		$id_param       = $is_number ? '[0-9]+' : '[^/]+';
		$relation_param = \implode('|', $relations_names);

		$group = $router->group(static::SERVICE_PATH, static function (Router $router) use ($candidates_names): void {
			foreach (RESTFulAction::cases() as $action) {
				if (RESTFulAction::GET_CANDIDATES === $action && empty($candidates_names)) {
					continue;
				}

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
			->param(RESTFulAction::RELATION_PARAM, $relation_param)
			->param(static::KEY_COLUMN, $id_param);

		if (!empty($candidates_names)) {
			$group->param(RESTFulAction::CANDIDATES_RELATION_PARAM, \implode('|', $candidates_names));
		}
	}

	/**
	 * The relations of a table a client may ask the candidates of: those that declare them, and are
	 * not private. Without one, the table serves no `get_candidates`.
	 *
	 * @return list<string>
	 */
	public static function candidatesRelationNames(Table $table): array
	{
		$names = [];

		foreach ($table->getRelations(false) as $relation) {
			if ($relation->hasCandidates()) {
				$names[] = $relation->getName();
			}
		}

		return $names;
	}

	/**
	 * Registers access rights actions for the given table.
	 */
	protected static function registerAccessRightsActions(Table $table): void
	{
		$prefix = $table->getMorphType();

		// Every action a table is asked for, `create` and `create_bulk` included: a right not
		// registered can never be granted, and a check for it never passes.
		foreach (TableActions::names() as $action) {
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
			static fn(RouteInfo $ri) => (new static($ri))->runAction($action, $ri)
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
		// The filter names the column as the database does: KEY_COLUMN may be the short name (`id`),
		// which every other use resolves through the table, and which no query would find on a table
		// whose columns carry a prefix (`article_id`).
		$entry_filters = $action->onEntry()
			? [
				$this->table->getColumnOrFail(static::KEY_COLUMN)
					->getFullName(),
				'eq',
				$ri->param(static::KEY_COLUMN),
			]
			: [];

		// get_relation looks the entry up itself: its request filters apply to the relatives.
		$req = static::buildRequest($ri, RESTFulAction::GET_RELATION === $action ? [] : $entry_filters);

		$this->beforeAction($action, $req);

		match ($action) {
			RESTFulAction::CREATE_ONE   => $this->actionCreateOne($req),
			RESTFulAction::CREATE_BULK  => $this->actionCreateBulk($req),
			RESTFulAction::GET_ALL      => $this->actionGetAll($req),
			RESTFulAction::UPDATE_ALL   => $this->actionUpdateAll($req),
			RESTFulAction::DELETE_ALL   => $this->actionDeleteAll($req),
			RESTFulAction::GET_ONE      => $this->actionGetOne($req),
			RESTFulAction::UPDATE_ONE   => $this->actionUpdateOne($req),
			RESTFulAction::DELETE_ONE   => $this->actionDeleteOne($req),
			RESTFulAction::GET_RELATION   => $this->actionGetRelation($req, $entry_filters, $ri->param(RESTFulAction::RELATION_PARAM)),
			RESTFulAction::GET_CANDIDATES => $this->actionGetCandidates($req, $ri->param(RESTFulAction::CANDIDATES_RELATION_PARAM)),
		};

		$this->afterAction($action, $req);

		return $this->respond();
	}
}
