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

use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Annotations as OA;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use OpenApi\Processors\MergeJsonContent;

/**
 * Class SchemaBuilder.
 *
 * OpenAPI schema primitives, and the reusable components of the document.
 */
final class SchemaBuilder
{
	public function __construct(private readonly OpenApi $openapi) {}

	/**
	 * Create schema with type `object`.
	 *
	 * @param Schema[] $properties The object properties
	 * @param array    $options    The schema options
	 *
	 * @return Schema
	 */
	public function object(array $properties, array $options = []): Schema
	{
		$normalized_props = [];

		foreach ($properties as $key => $value) {
			if ($value instanceof OA\Property) {
				$normalized_props[] = $value;
			} else {
				$normalized_props[] = $prop = new OA\Property([
					'property' => $key,
				]);

				// merge the value properties to the property
				foreach ($value as $prop_key => $prop_value) {
					$prop->{$prop_key} = $prop_value;
				}
			}
		}

		return new Schema([
			'type'       => 'object',
			'properties' => $normalized_props,
		] + $options);
	}

	/**
	 * Create schema with type `array`.
	 *
	 * @param null|Schema $item    The array items schema
	 * @param array       $options The schema options
	 *
	 * @return Schema
	 */
	public function array(?Schema $item = null, array $options = []): Schema
	{
		return new Schema([
			'type'  => 'array',
			'items' => $item ?? new OA\Items([]),
		] + $options);
	}

	/**
	 * Create schema with type `integer`.
	 *
	 * @param null|string $description the schema description
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function integer(?string $description = null, array $options = []): Schema
	{
		return $this->type('integer', $description, $options);
	}

	/**
	 * Create schema with type `string`.
	 *
	 * @param null|string $description the schema description
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function string(?string $description = null, array $options = []): Schema
	{
		return $this->type('string', $description, $options);
	}

	/**
	 * Create schema with type `boolean`.
	 *
	 * @param null|string $description the schema description
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function boolean(?string $description = null, array $options = []): Schema
	{
		return $this->type('boolean', $description, $options);
	}

	/**
	 * Create schema with a given type.
	 *
	 * @param string|string[] $type        The schema type {@see Schema::$type}
	 * @param null|string     $description The schema description
	 * @param array           $options     The schema options
	 *
	 * @return Schema
	 */
	public function type(array|string $type, ?string $description = null, array $options = []): Schema
	{
		$s = new Schema([
			'type' => $type,
		] + $options);

		if (null !== $description) {
			$s->description = $description;
		}

		return $s;
	}

	/**
	 * Create a JSON media type.
	 */
	public function json(Schema $schema): OA\MediaType
	{
		/**
		 * Not using shortcut {@see OA\JsonContent} because
		 * we are not using processors {@see MergeJsonContent} as seen here {@see Generator::getProcessorPipeline()}.
		 */
		return new OA\MediaType([
			'mediaType' => 'application/json',
			'schema'    => $schema,
		]);
	}

	/**
	 * Create a reusable component, once, and returns a reference to it.
	 *
	 * @psalm-suppress PossiblyInvalidPropertyAssignmentValue
	 *
	 * @param string                        $kind    the component kind: `schemas`, `responses`, `parameters`,
	 *                                               `requestBodies`, `headers`, `examples`, `links` or
	 *                                               `securitySchemes`
	 * @param string                        $name    the component name
	 * @param callable():AbstractAnnotation $factory the component factory
	 * @param array                         $options the component options
	 *
	 * @return Schema
	 */
	public function component(string $kind, string $name, callable $factory, array $options = []): Schema
	{
		/** @psalm-suppress InvalidPropertyAssignmentValue */
		if (self::isUndefined($this->openapi->components)) {
			$this->openapi->components = [];
		}

		/** @psalm-suppress UndefinedMethod */
		if (!isset($this->openapi->components[$kind][$name])) {
			/** @psalm-suppress UndefinedMethod */
			$this->openapi->components[$kind][$name] = $factory();
		}

		return new Schema([
			'ref'     => "#/components/{$kind}/{$name}",
		] + $options);
	}

	/**
	 * Push a value to an object property.
	 *
	 * @param object                    $to        The object
	 * @param string                    $prop      The property name
	 * @param mixed                     $value     The value to push
	 * @param null|callable(mixed):bool $predicate The predicate to check if the value already exists
	 */
	public static function push(object $to, string $prop, mixed $value, ?callable $predicate = null): void
	{
		if (self::isUndefined($to->{$prop})) {
			$to->{$prop} = [];
		} elseif (!\is_array($to->{$prop})) {
			$to->{$prop} = [$to->{$prop}];
		}

		if ($predicate) {
			foreach ($to->{$prop} as $v) {
				if (!$predicate($v)) {
					continue;
				}

				return;
			}
		}

		$to->{$prop}[] = $value;
	}

	/**
	 * Check if an annotation property value is {@see Generator::UNDEFINED}.
	 */
	public static function isUndefined(mixed $value): bool
	{
		return Generator::UNDEFINED === $value;
	}

	/**
	 * Try create an user friendly name.
	 *
	 * Examples:
	 *  - 'oz_user' => 'User'
	 *  - `user_profile` => `User Profile`
	 *  - `user_id` => `User Id`
	 *
	 * @param string $name the table name
	 */
	public static function toHumanReadable(string $name): string
	{
		$p = \explode('_', $name);

		$first = $p[0];

		if (\strlen($first) <= 2) {
			\array_shift($p);
		}

		return \implode(' ', \array_map(\ucfirst(...), $p));
	}
}
