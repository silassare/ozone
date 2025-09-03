<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\REST;

use Gobl\DBAL\Column;
use Gobl\DBAL\Relations\Interfaces\RelationInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMRequest;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InvalidFormException;
use Throwable;

/**
 * Class RESTFullRelationsHelper.
 *
 * @internal
 */
final class RESTFullRelationsHelper
{
	private readonly Column $pk_column;

	/**
	 * RESTFullRelationsHelper constructor.
	 *
	 * @param Table $table
	 */
	public function __construct(private readonly Table $table)
	{
		$this->pk_column = $table->getSinglePKColumnOrFail();
	}

	/**
	 * Asserts that the given relation is not private.
	 *
	 * @throws ForbiddenException
	 */
	public static function assertNotPrivateRelation(RelationInterface $r): void
	{
		if ($r->isPrivate()) {
			throw new ForbiddenException(null, [
				'_message'  => 'Attempt to access private relations.',
				'_relation' => $r->getName(),
			]);
		}
	}

	/**
	 * Make sure to load non-paginated relations for a single entity.
	 *
	 * @param ORMEntity         $entity
	 * @param RESTFulAPIRequest $req
	 *
	 * @return array
	 *
	 * @throws BadRequestException|ForbiddenException
	 */
	public function entityNonPaginatedRelations(ORMEntity $entity, RESTFulAPIRequest $req): array
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
	 * @throws BadRequestException|ForbiddenException
	 */
	public function entitiesNonPaginatedRelations(array $entities, RESTFulAPIRequest $req): array
	{
		$query_relations = $req->getRequestedRelations();
		$results         = [];
		$key_column      = $this->pk_column->getName();

		if (!empty($query_relations)) {
			$list = $this->resolveRelations($query_relations, false);

			/** @var Relation[] $relations */
			$relations = $list[Relation::class] ?? [];

			/** @var VirtualRelation[] $v_relations */
			$v_relations = $list[VirtualRelation::class] ?? [];

			foreach ($relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{$key_column};
					$results[$name][$id] = $this->getRelationItem($rel, $entity);
				}
			}

			foreach ($v_relations as $name => $rel) {
				foreach ($entities as $entity) {
					$id                  = $entity->{$key_column};
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
	public function getRelationItemsList(
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
	public function getRelationItem(Relation $relation, ORMEntity $entity): mixed
	{
		$relation_getter = $relation->getGetterName();

		return $entity->{$relation_getter}();
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
	public function processRelations(ORMEntity $entity, ORMRequest $req, bool $patch): void
	{
		$table = $this->table;

		/** @var array<RelationInterface> $relations */
		$relations = [
			...$table->getRelations(false),
			...$table->getVirtualRelations(false),
		];

		foreach ($relations as $relation) {
			$relation_name    = $relation->getName();
			$relation_payload = $req->getFormField($relation_name);

			if (!empty($relation_payload)) {
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
	 * Resolve relations.
	 *
	 * @param array $relations_names_list
	 * @param bool  $allow_paginated
	 *
	 * @return array
	 *
	 * @throws BadRequestException|ForbiddenException
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

			if ($rel) {
				self::assertNotPrivateRelation($rel);

				if (!$allow_paginated && $rel->isPaginated()) {
					throw new BadRequestException(
						'OZ_RELATION_IS_PAGINATED_AND_SHOULD_BE_RETRIEVED_WITH_DEDICATED_ENDPOINT',
						['relation' => $name]
					);
				}
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
