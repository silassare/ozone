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

namespace OZONE\Core\REST\ApiDoc;

use BackedEnum;
use Gobl\DBAL\Relations\Interfaces\VirtualRelationInterface;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use OpenApi\Annotations\Schema;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class GoblSchemaMapper.
 *
 * Maps Gobl column types and tables to OpenAPI schemas.
 */
final class GoblSchemaMapper
{
	/**
	 * @var array<string, callable(TypeInterface):Schema> gobl type name -> schema provider
	 */
	private static array $type_schema_providers = [];

	public function __construct(private readonly SchemaBuilder $schemas) {}

	/**
	 * Declare a gobl type to schema provider, used instead of the built-in mapping.
	 *
	 * @param string                         $name     the gobl type name
	 * @param callable(TypeInterface):Schema $provider the provider callable
	 */
	public static function declareTypeSchemaProvider(string $name, callable $provider): void
	{
		self::$type_schema_providers[$name] = $provider;
	}

	/**
	 * Create a schema for a gobl type.
	 */
	public function typeSchema(TypeInterface $type): Schema
	{
		$factory = self::$type_schema_providers[$type->getName()] ?? null;

		if ($factory) {
			$schema = $factory($type);
		} else {
			$schema = $this->map($type) ?? $this->map($type->getBaseType());
		}

		if (null === $schema) {
			throw (new RuntimeException("Unsupported type: {$type->getName()}"))->suspectObject($type);
		}

		return $schema;
	}

	/**
	 * Create a schema for a gobl virtual relation.
	 */
	public function virtualRelationTypeSchema(VirtualRelationInterface $vr): Schema
	{
		$type = $vr->getRelativeType();

		if ($type instanceof Table) {
			return $this->entitySchemaForRead($type);
		}

		return $this->typeSchema($type);
	}

	/**
	 * Returns schema type for a table entity read.
	 *
	 * @param string|Table $table The table or the table name
	 */
	public function entitySchemaForRead(string|Table $table): Schema
	{
		return $this->entitySchema($table, 'read');
	}

	/**
	 * Returns schema type for a table entity creation.
	 *
	 * @param string|Table $table The table or the table name
	 */
	public function entitySchemaForCreate(string|Table $table): Schema
	{
		return $this->entitySchema($table, 'create');
	}

	/**
	 * Returns schema type for a table entity update.
	 *
	 * @param string|Table $table The table or the table name
	 */
	public function entitySchemaForUpdate(string|Table $table): Schema
	{
		return $this->entitySchema($table, 'update');
	}

	/**
	 * Extract api doc metadata from a table.
	 *
	 * @return array{singular_name: string, plural_name: string, description: string, use_an: bool}
	 */
	public function tableMeta(Table $table): array
	{
		$meta = $table->getMeta();

		$get = static function (string $key, $default) use ($meta) {
			$value = $meta->get($key);

			if (null === $value || '' === $value) {
				$value = $default;
			}

			return $value;
		};

		return [
			'singular_name' => $get('api.doc.singular_name', SchemaBuilder::toHumanReadable($table->getSingularName())),
			'plural_name'   => $get('api.doc.plural_name', SchemaBuilder::toHumanReadable($table->getPluralName())),
			'description'   => $get('api.doc.description', ''),
			'use_an'        => (bool) $get('api.doc.use_an', false),
		];
	}

	/**
	 * Create a schema component for a table entity; private columns are left out.
	 *
	 * @param string|Table             $table The table or the table name
	 * @param 'create'|'read'|'update' $for   Specify the entity schema usage
	 */
	private function entitySchema(string|Table $table, string $for): Schema
	{
		$table        = \is_string($table) ? db()->getTableOrFail($table) : $table;
		$api_doc_meta = $this->tableMeta($table);
		$c_key        = \str_replace(' ', '', $api_doc_meta['singular_name'] . ' ' . \ucfirst($for));

		return $this->schemas->component('schemas', $c_key, function () use ($table, $for) {
			/** @var array<string, Schema> $properties */
			$properties = [];

			/** @var string[] $required_names */
			$required_names = [];

			$is_create = 'create' === $for;
			$is_update = 'update' === $for;

			foreach ($table->getColumns() as $column) {
				$type = $column->getType();

				if ($column->isPrivate() || (($is_create || $is_update) && $type->isAutoIncremented())) {
					continue;
				}

				$name = $column->getFullName();

				$schema = $this->typeSchema($column->getType());

				if ($is_create && !$type->isNullAble() && null === $type->getDefault()) {
					$required_names[] = $name;
				}

				$col_desc = $column->getMeta()->get('api.doc.description', '');
				if (!empty($col_desc)) {
					$schema->description = $col_desc;
				}

				$properties[$name] = $schema;
			}

			return $this->schemas->object($properties, [
				'required' => $required_names,
			]);
		});
	}

	/**
	 * Converts a gobl type to a schema type, or null when the type is not a built-in one.
	 */
	private function map(TypeInterface $type): ?Schema
	{
		$sc_type   = null;
		$t_name    = $type->getName();
		$t_default = $type->getDefault();

		switch ($t_name) {
			case TypeInt::NAME:
				$sc_type = $this->schemas->integer(null, [
					'format' => 'int32',
				]);

				break;

			case TypeString::NAME:
			case TypeEmail::NAME:
			case TypePassword::NAME:
				$sc_type = $this->schemas->string();

				/** @var TypeString $type */
				$one_of  = $type->getOption('one_of');
				$pattern = $type->getOption('pattern');

				if ($one_of) {
					$sc_type->enum = $one_of;
				}

				if ($pattern) {
					$sc_type->pattern = $pattern;
				}

				if (TypeEmail::NAME === $t_name || TypePassword::NAME === $t_name) {
					$sc_type->format = $t_name;
				}

				break;

			case TypeEnum::NAME:
				$sc_type = $this->schemas->string();

				/** @var TypeEnum $type */
				/** @var class-string<BackedEnum> $enum_class */
				$enum_class = $type->getOption('enum_class');

				if ($enum_class) {
					$sc_type->enum = $enum_class::cases();
				}

				break;

			case TypeBool::NAME:
				$sc_type   = $this->schemas->boolean();
				$t_default = null === $t_default ? null : (bool) $t_default;

				break;

			case TypeBigint::NAME:
				$sc_type = $this->schemas->string(null, [
					'format' => 'int64',
				]);

				break;

			case TypeFloat::NAME:
			case TypeDecimal::NAME:
				$sc_type = $this->schemas->type('number', null, [
					'format' => $type->getName(),
				]);

				break;

			case TypeList::NAME:
				$sc_type = $this->schemas->array();

				break;

			case TypeMap::NAME:
				$sc_type = $this->schemas->object([]);

				break;

			case TypeDate::NAME:
				/** @var TypeDate $type */
				$g_format = $type->getOption('format', TypeDate::FORMAT_DEFAULT);

				if (TypeDate::FORMAT_TIMESTAMP === $g_format) {
					$sc_type = $this->schemas->string('The UTC date in UNIX timestamp format.', [
						'format' => $type->isMicroseconds() ? 'float' : 'int64',
					]);
				} else {
					$sc_type = $this->schemas->string(\sprintf('The UTC date time in `%s` format.', $g_format), [
						'format' => 'date-time',
					]);
				}

				$t_default = null === $t_default ? null : $type->dbToPhp($t_default, db());

				break;
		}

		if (null === $sc_type) {
			return null;
		}

		if ($type->isNullable() && !\is_array($sc_type->type)) {
			$sc_type->type = ['null', $sc_type->type];
		}

		if ($type->hasDefault() && SchemaBuilder::isUndefined($sc_type->default)) {
			$sc_type->default = $t_default;
		}

		return $sc_type;
	}
}
