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

use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Schema;
use OZONE\Core\REST\RESTFulAPIRequest;

/**
 * Class ParameterBuilder.
 *
 * OpenAPI parameters, and the query parameters of the O'Zone API (pagination, filters,
 * ordering, relations, collections).
 */
final class ParameterBuilder
{
	public function __construct(private readonly SchemaBuilder $schemas) {}

	/**
	 * Create a new parameter.
	 *
	 * @param string                           $name        the parameter name
	 * @param Schema                           $schema      the parameter schema
	 * @param string                           $description the parameter description
	 * @param 'cookie'|'header'|'path'|'query' $in          the parameter location
	 * @param array                            $properties  the parameter properties
	 */
	public function parameter(
		string $name,
		Schema $schema,
		string $description,
		string $in = 'path',
		array $properties = []
	): Parameter {
		return new Parameter([
			'name'        => $name,
			'schema'      => $schema,
			'in'          => $in,
			'description' => $description,
		] + $properties);
	}

	/**
	 * Create the O'Zone API page parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 */
	public function page(string $in = 'query'): Parameter
	{
		return $this->parameter(
			RESTFulAPIRequest::PAGE_PARAM,
			$this->schemas->integer('The desired page number.', [
				'default' => 1,
			]),
			'The desired page number.',
			$in
		);
	}

	/**
	 * Create the O'Zone API max parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in          The parameter location
	 * @param int                              $default_max The default maximum number of items per page
	 */
	public function max(string $in = 'query', int $default_max = 10): Parameter
	{
		return $this->parameter(
			RESTFulAPIRequest::MAX_PARAM,
			$this->schemas->integer('The maximum number of items per page.', [
				'default' => $default_max,
			]),
			'The maximum number of items per page.',
			$in
		);
	}

	/**
	 * Create the O'Zone API cursor parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 */
	public function cursor(string $in = 'query'): Parameter
	{
		return $this->parameter(
			RESTFulAPIRequest::CURSOR_PARAM,
			$this->schemas->string('The cursor value from the previous page.'),
			'The cursor value from the previous page for cursor-based pagination.',
			$in
		);
	}

	/**
	 * Create the O'Zone API cursor column parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 */
	public function cursorColumn(string $in = 'query'): Parameter
	{
		return $this->parameter(
			RESTFulAPIRequest::CURSOR_COLUMN_PARAM,
			$this->schemas->string('The column name to use as cursor.'),
			'The column name to use as cursor. When provided, activates cursor-based pagination.',
			$in
		);
	}

	/**
	 * Create the O'Zone API cursor direction parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 */
	public function cursorDir(string $in = 'query'): Parameter
	{
		$sc       = $this->schemas->string('The direction of cursor-based pagination.');
		$sc->enum = ['asc', 'desc'];

		return $this->parameter(
			RESTFulAPIRequest::CURSOR_DIR_PARAM,
			$sc,
			'The direction of cursor-based pagination (`asc` or `desc`).',
			$in
		);
	}

	/**
	 * Create the O'Zone API collection parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in          The parameter location
	 * @param string[]                         $collections The available collections
	 */
	public function collection(string $in = 'query', array $collections = []): Parameter
	{
		$sc = $this->schemas->string('The collection name.');

		if (!empty($collections)) {
			$sc->enum = $collections;
		}

		return $this->parameter(
			RESTFulAPIRequest::COLLECTION_PARAM,
			$sc,
			'The collection name.',
			$in
		);
	}

	/**
	 * Create the O'Zone API order by parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 */
	public function orderBy(string $in = 'query'): Parameter
	{
		$list_sep = RESTFulAPIRequest::ORDER_BY_DELIMITER;
		$rule_sep = RESTFulAPIRequest::ORDER_BY_DELIMITER_ASC_DESC;

		$sc       = $this->schemas->string('The ordering rules parameter');
		$desc     = <<<DESC
The ordering rules parameter.

A list of ordering rules separated by the delimiter `{$list_sep}`.
Each ordering rule is a column name followed by the delimiter `{$rule_sep}` and the order direction (`asc` or `desc`).

> Example: `field_one{$rule_sep}asc{$list_sep}field_two{$rule_sep}desc`.
DESC;

		return $this->parameter(
			RESTFulAPIRequest::ORDER_BY_PARAM,
			$sc,
			$desc,
			$in
		);
	}

	/**
	 * Create the O'Zone API relations parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in        The parameter location
	 * @param string[]                         $relations The available relations
	 */
	public function relations(string $in = 'query', array $relations = []): Parameter
	{
		$rel_sep               = RESTFulAPIRequest::RELATIONS_DELIMITER;
		$sc                    = $this->schemas->string('The relations parameter.');
		$allowed_relation_text = '';

		if (!empty($relations)) {
			$sc->enum              = $relations;
			$rel_strings           = \implode($rel_sep, $relations);
			$allowed_relation_text = "Allowed relations are: `{$rel_strings}`.\n";
		}

		$sample_relations = empty($relations) ? ['relation_one', 'relation_two'] : \array_slice($relations, 0, 2);
		$sample           = \implode($rel_sep, $sample_relations);
		$desc             = <<<DESC
The relations parameter.

A list of relations separated by the delimiter `{$rel_sep}`.
{$allowed_relation_text}
> Note: Only non paginated relations are allowed. For paginated relations use dedicated endpoints.

> Example: `{$sample}`.
DESC;

		return $this->parameter(
			RESTFulAPIRequest::RELATIONS_PARAM,
			$sc,
			$desc,
			$in
		);
	}

	/**
	 * Create the O'Zone API filters parameter.
	 *
	 * When `$table` is provided, the description includes a per-column section
	 * listing each filterable column and its allowed operators.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in    The parameter location
	 * @param null|Table                       $table The table to document per-column operators for
	 */
	public function filters(string $in = 'query', ?Table $table = null): Parameter
	{
		$filters_param  = RESTFulAPIRequest::FILTERS_PARAM;
		$op_in          = Operator::IN->value;
		$op_not_in      = Operator::NOT_IN->value;
		$op_eq          = Operator::EQ->value;
		$op_neq         = Operator::NEQ->value;
		$op_lt          = Operator::LT->value;
		$op_lte         = Operator::LTE->value;
		$op_gt          = Operator::GT->value;
		$op_gte         = Operator::GTE->value;
		$op_like        = Operator::LIKE->value;
		$op_not_like    = Operator::NOT_LIKE->value;
		$op_is_null     = Operator::IS_NULL->value;
		$op_is_not_null = Operator::IS_NOT_NULL->value;
		$op_contains    = Operator::CONTAINS->value;
		$op_has_key     = Operator::HAS_KEY->value;

		$desc = <<<DESC
The filters parameter.

Use `{$filters_param}` to narrow query results with complex boolean conditions.
Each condition is a JSON array triplet `[field, operator, value]`. Conditions can be
composed with `AND` / `OR` connectors and nested arbitrarily.

**Available operators:**

| Operator | Description | Value type |
|----------|-------------|------------|
| `{$op_eq}` | Equal | scalar |
| `{$op_neq}` | Not equal | scalar |
| `{$op_lt}` | Less than | number or string |
| `{$op_lte}` | Less than or equal | number or string |
| `{$op_gt}` | Greater than | number or string |
| `{$op_gte}` | Greater than or equal | number or string |
| `{$op_like}` | SQL LIKE pattern match (`%` = any chars, `_` = one char) | string |
| `{$op_not_like}` | SQL NOT LIKE (inverse of `{$op_like}`) | string |
| `{$op_is_null}` | Column value is NULL | omit value |
| `{$op_is_not_null}` | Column value is NOT NULL | omit value |
| `{$op_in}` | Value is one of a list | array of scalars |
| `{$op_not_in}` | Value is NOT in a list | array of scalars |
| `{$op_contains}` | JSON column contains the given JSON value (native JSON columns only) | any JSON value |
| `{$op_has_key}` | JSON column has the given key (native JSON columns only) | string |

> The `{$op_is_null}` and `{$op_is_not_null}` operators take no value; supply only `[field, operator]`.

> The `{$op_in}` and `{$op_not_in}` operators require an **array** as value: `["field", "in", [1, 2, 3]]`.

> The `{$op_contains}` and `{$op_has_key}` operators are only available on columns declared as native JSON.

**Condition format:**
```
["field", "operator", value]         // regular comparison
["field", "is_null"]                 // no value needed for is_null / is_not_null
["field", "in", [1, 2, 3]]          // array value for in / not_in
```

**Composing conditions (array expression):**
```json
[
    [
        ["status", "eq", "active"],
        "OR", ["role", "in", ["admin", "mod"]]
    ],
    "AND", ["created_at", "gte", 1700000000]
]
```

**Composing conditions (flat shorthand):** all conditions share the same logical level:
```json
[
    "status", "eq", "active",
    "AND",
    "score", "gte", 10,
    "AND",
    "deleted_at", "is_null"
]
```
DESC;

		if (null !== $table) {
			$per_column_lines = [];
			foreach ($table->getColumns() as $column) {
				$col_ops = $column->getType()->getAllowedFilterOperators();
				if (!empty($col_ops)) {
					$col_name           = $column->getFullName();
					$type_name          = $column->getType()->getName();
					$ops_str            = \implode(', ', \array_map(static fn ($op) => "`{$op->value}`", $col_ops));
					$per_column_lines[] = "| `{$col_name}` | `{$type_name}` | {$ops_str} |";
				}
			}
			if (!empty($per_column_lines)) {
				$per_col_table = \implode("\n", $per_column_lines);
				$desc .= <<<COLS


**Available operators per column:**

| Column | Type | Operators |
|--------|------|-----------|
{$per_col_table}
COLS;
			}
		}

		return $this->parameter(
			$filters_param,
			$this->schemas->array(null, ['description' => 'The desired filters.']),
			$desc,
			$in
		);
	}
}
