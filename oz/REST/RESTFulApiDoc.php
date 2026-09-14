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
use LogicException;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OZONE\Core\REST\Enums\RESTFulAction;
use PHPUtils\Str;

/**
 * Class RESTFulApiDoc.
 *
 * The API docs of a {@see RESTFulService}: one operation per {@see RESTFulAction} that the table
 * metadata leaves enabled (`api.doc.<action>.enabled`), and one `get_relation` operation per
 * relation (`api.doc.get_relation.<relation>.enabled`). `api.doc.enabled` turns them all off, and
 * the actions the service disables ({@see RESTFulService::isActionEnabled()}) are left out.
 *
 * @psalm-type Shared = array{
 *     table: Table,
 *     singular: string,
 *     plural: string,
 *     a_an: string,
 *     op_prefix: string,
 *     tag: string,
 *     entity_read: Schema,
 *     entity_create: Schema,
 *     entity_update: Schema,
 *     relations_parameter: null|Parameter,
 *     relations_schemas: array<string, Schema>,
 * }
 */
final class RESTFulApiDoc
{
	/**
	 * @param ApiDoc                       $doc
	 * @param class-string<RESTFulService> $service
	 */
	public function __construct(private readonly ApiDoc $doc, private readonly string $service) {}

	/**
	 * Adds the service's operations to the docs.
	 */
	public function document(): void
	{
		$service = $this->service;
		$doc     = $this->doc;
		$table   = db()->getTableOrFail($service::TABLE_NAME);
		$meta    = $table->getMeta();

		if (!$meta->get('api.doc.enabled', true)) {
			return;
		}

		$api_doc_meta = $doc->tableMeta($table);
		$tag          = $doc->addTag($api_doc_meta['plural_name'], $api_doc_meta['description']);
		$s            = [
			'table'         => $table,
			'singular'      => $api_doc_meta['singular_name'],
			'plural'        => $api_doc_meta['plural_name'],
			'a_an'          => $api_doc_meta['use_an'] ? 'an' : 'a',
			'op_prefix'     => Str::stringToURLSlug($api_doc_meta['singular_name'], '_'),
			'tag'           => $tag->name,
			'entity_read'   => $doc->entitySchemaForRead($table),
			'entity_create' => $doc->entitySchemaForCreate($table),
			'entity_update' => $doc->entitySchemaForUpdate($table),
		];

		$relatives = $this->relationsDocOptions($table);

		$s['relations_parameter'] = $relatives['relations_parameter'];
		$s['relations_schemas']   = $relatives['non_paginated_relations_schemas'];

		foreach (RESTFulAction::cases() as $action) {
			if (RESTFulAction::GET_RELATION === $action || !$service::isActionEnabled($action)) {
				continue;
			}

			if (!$meta->get(\sprintf('api.doc.%s.enabled', $action->value), true)) {
				continue;
			}

			[$summary, $responses, $properties] = $this->operation($action, $s);

			$doc->addOperationFromRoute(
				$service::routeName($action),
				$action->httpMethod(),
				$summary,
				$responses,
				$properties
			);
		}

		if (!$service::isActionEnabled(RESTFulAction::GET_RELATION)) {
			return;
		}

		foreach ($relatives['relations'] as $relation) {
			/** @psalm-suppress InvalidArgument */
			$this->relationOperation($relation, $doc->entitySchemaForRead($relation->getTargetTable()), $s);
		}

		foreach ($relatives['v_relations'] as $vr) {
			$this->relationOperation($vr, $doc->virtualRelationTypeSchema($vr), $s);
		}
	}

	/**
	 * The summary, responses and properties of an action's operation.
	 *
	 * @param Shared $s
	 *
	 * @return array{0: string, 1: list<Response>, 2: array<string, mixed>}
	 */
	private function operation(RESTFulAction $action, array $s): array
	{
		$doc      = $this->doc;
		$service  = $this->service;
		$table    = $s['table'];
		$singular = $s['singular'];
		$plural   = $s['plural'];
		$key      = ApiDoc::toHumanReadable($service::KEY_COLUMN);
		$op_id    = \sprintf('%s.%s', $s['op_prefix'], $action->value);
		$item     = $doc->object(['item' => $s['entity_read']]);
		$affected = $doc->object(['affected' => $doc->integer('The number of affected rows.')]);

		$identified = static fn (string $verb) => \sprintf(
			'%s %s `%s` identified by a given `%s`',
			$verb,
			$s['a_an'],
			$singular,
			$key
		);

		return match ($action) {
			RESTFulAction::CREATE_ONE => [
				\sprintf('Create %s', $singular),
				[$doc->success($item, \sprintf('The `%s` was created successfully.', $singular), 'OK', 201)],
				[
					'tags'        => [$s['tag']],
					'description' => \sprintf('Create new `%s`.', $singular),
					'operationId' => $op_id,
					'requestBody' => $doc->requestBody([$doc->json($s['entity_create'])]),
				],
			],
			RESTFulAction::GET_ONE => [
				\sprintf('Get %s', $singular),
				[
					$doc->success(
						$doc->object([
							'item'      => $s['entity_read'],
							'relations' => $doc->object($s['relations_schemas']),
						]),
						\sprintf('The `%s` was retrieved successfully.', $singular)
					),
				],
				[
					'tags'        => [$s['tag']],
					'description' => $identified('Get') . '.',
					'operationId' => $op_id,
					'parameters'  => null !== $s['relations_parameter'] ? [$s['relations_parameter']] : [],
				],
			],
			RESTFulAction::UPDATE_ONE => [
				\sprintf('Update %s', $singular),
				[$doc->success($item, \sprintf('The `%s` was updated successfully.', $singular))],
				[
					'tags'        => [$s['tag']],
					'description' => $identified('Update'),
					'operationId' => $op_id,
					'requestBody' => $doc->requestBody([$doc->json($s['entity_update'])]),
					'parameters'  => [],
				],
			],
			RESTFulAction::DELETE_ONE => [
				\sprintf('Delete %s', $singular),
				[$doc->success($item, \sprintf('The `%s` was deleted successfully.', $singular))],
				[
					'tags'        => [$s['tag']],
					'description' => $identified('Delete') . '.',
					'operationId' => $op_id,
					'parameters'  => [],
				],
			],
			RESTFulAction::GET_ALL => [
				\sprintf('List %s', $plural),
				[
					$doc->success(
						$this->listSchema($s['entity_read'], $doc->object(\array_map(
							static fn ($schema) => $doc->object(['{' . $service::KEY_COLUMN . '}' => $schema]),
							$s['relations_schemas']
						))),
						\sprintf('All `%s` were retrieved successfully.', $plural)
					),
				],
				[
					'tags'        => [$s['tag']],
					'description' => \sprintf('Gets all `%s` that matches a given filters.', $plural),
					'operationId' => $op_id,
					'parameters'  => $this->getAllParameters($table, $s['relations_parameter']),
				],
			],
			RESTFulAction::UPDATE_ALL => [
				\sprintf('Update %s', $plural),
				[$doc->success($affected, \sprintf('All `%s` were updated successfully.', $plural))],
				[
					'tags'        => [$s['tag']],
					'description' => \sprintf('Update all `%s` that matches a given filters.`', $plural),
					'operationId' => $op_id,
					'requestBody' => $doc->requestBody([$doc->json($s['entity_update'])]),
					'parameters'  => $this->bulkParameters($table),
				],
			],
			RESTFulAction::DELETE_ALL => [
				\sprintf('Delete %s', $plural),
				[$doc->success($affected, \sprintf('All `%s` were deleted successfully.', $plural))],
				[
					'tags'        => [$s['tag']],
					'description' => \sprintf('Delete all `%s` that matches a given filters.', $plural),
					'operationId' => $op_id,
					'parameters'  => $this->bulkParameters($table),
				],
			],
			RESTFulAction::GET_RELATION => throw new LogicException('get_relation is documented per relation.'),
		};
	}

	/**
	 * Adds the `get_relation` operation of one relation.
	 *
	 * @param Shared $s
	 */
	private function relationOperation(RelationInterface $r, Schema $r_schema, array $s): void
	{
		$doc    = $this->doc;
		$r_name = $r->getName();

		if (!$s['table']->getMeta()->get(\sprintf('api.doc.get_relation.%s.enabled', $r_name), true)) {
			return;
		}

		// Not a virtual relation: its target table gives the relation's customized filter parameters.
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
			$r_relatives_options = $this->relationsDocOptions($r_table);

			if (isset($r_relatives_options['relations_parameter'])) {
				$r_params[] = $r_relatives_options['relations_parameter'];
			}
			$r_relations_schema = $r->isPaginated() ? $doc->object(\array_map(
				static fn ($schema) => $doc->object(['{' . $r_pk_column . '}' => $schema]),
				$r_relatives_options['non_paginated_relations_schemas']
			)) : $doc->object($r_relatives_options['non_paginated_relations_schemas']);
		}

		$service       = $this->service;
		$r_human       = ApiDoc::toHumanReadable($r_name);
		$data_schema   = $r->isPaginated()
			? $this->listSchema($r_schema, $r_relations_schema)
			: $doc->object(
				['item' => $r_schema]
					+ (null !== $r_relations_schema ? ['relations' => $r_relations_schema] : [])
			);

		$doc->addOperationFromRoute(
			$service::routeName(RESTFulAction::GET_RELATION),
			'GET',
			\sprintf('Get %s %s', $s['singular'], $r_human),
			[
				$doc->success(
					$data_schema,
					\sprintf('The `%s` of the `%s` was retrieved successfully.', $r_human, $s['singular'])
				),
			],
			[
				'tags'        => [$s['tag']],
				'description' => \sprintf(
					'Gets the `%s` of the `%s` with the given `%s`.',
					$r_human,
					$s['singular'],
					ApiDoc::toHumanReadable($service::KEY_COLUMN)
				),
				'operationId' => \sprintf('%s.get_relation.%s', $s['op_prefix'], $r_name),
				'parameters'  => $r_params,
			],
			[
				'relation' => $r_name,
			]
		);
	}

	/**
	 * A page of items, offset or cursor based, with their relations when given.
	 */
	private function listSchema(Schema $item, ?Schema $relations): Schema
	{
		$doc        = $this->doc;
		$properties = ['items' => $doc->array($item)];

		if (null !== $relations) {
			$properties['relations'] = $relations;
		}

		return new Schema([
			'oneOf' => [
				$doc->apiPaginated($properties),
				$doc->apiCursorPaginated($properties),
			],
		]);
	}

	/**
	 * The query parameters of `get_all`.
	 *
	 * @return list<Parameter>
	 */
	private function getAllParameters(Table $table, ?Parameter $relations_parameter): array
	{
		$doc    = $this->doc;
		$params = [
			$doc->apiMaxParameter(),
			$doc->apiPageParameter(),
			$doc->apiFiltersParameter('query', $table),
			$doc->apiOrderByParameter(),
			$doc->apiCursorParameter(),
			$doc->apiCursorColumnParameter(),
			$doc->apiCursorDirParameter(),
		];

		$collections = $table->getCollections();

		if (!empty($collections)) {
			$params[] = $doc->apiCollectionParameter(
				'query',
				\array_map(static fn ($c) => $c->getName(), $collections)
			);
		}

		if (null !== $relations_parameter) {
			$params[] = $relations_parameter;
		}

		return $params;
	}

	/**
	 * The query parameters of `update_all` and `delete_all`.
	 *
	 * @return list<Parameter>
	 */
	private function bulkParameters(Table $table): array
	{
		return [
			$this->doc->apiMaxParameter(),
			$this->doc->apiFiltersParameter('query', $table),
			$this->doc->apiOrderByParameter(),
		];
	}

	/**
	 * The relations of a table, the schemas of its non-paginated ones, and the `relations`
	 * query parameter listing them.
	 *
	 * @return array{
	 *     relations_parameter: null|Parameter,
	 *     non_paginated_relations_schemas: array<string, Schema>,
	 *     relations: array<int, Relation>,
	 *     v_relations: array<int, VirtualRelation>
	 * }
	 */
	private function relationsDocOptions(Table $table): array
	{
		$doc         = $this->doc;
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
