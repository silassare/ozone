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

use OpenApi\Annotations as OA;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\TypesSwitcher;

/**
 * Class ResponseBuilder.
 *
 * OpenAPI responses and request bodies: the O'Zone API response envelope, pagination
 * payloads, and request bodies documented from forms.
 */
final class ResponseBuilder
{
	public function __construct(
		private readonly SchemaBuilder $schemas,
		private readonly GoblSchemaMapper $gobl
	) {}

	/**
	 * Creates a new response.
	 *
	 * @param int                                       $http_status_code the response HTTP status code
	 * @param string                                    $description      the response description
	 * @param array<string, OA\Attachable|OA\MediaType> $content          the response content
	 */
	public function response(int $http_status_code, string $description, array $content): Response
	{
		return new Response([
			'response'    => $http_status_code,
			'description' => $description,
			'content'     => $content,
		]);
	}

	/**
	 * Create a new request body.
	 *
	 * @param array<OA\Attachable|OA\MediaType> $content
	 */
	public function requestBody(array $content): OA\RequestBody
	{
		return new OA\RequestBody([
			'content' => $content,
		]);
	}

	/**
	 * Create a request body from a form.
	 *
	 * Iterates the form fields (top-level, non-conditional) and builds an
	 * `application/json` request body schema.
	 *
	 * Hidden fields are included and annotated with `x-oz-hidden: true`.
	 * Required fields are listed in the JSON Schema `required` array.
	 *
	 * @param Form $form the form to document
	 */
	public function requestBodyFromForm(Form $form): OA\RequestBody
	{
		$opt = $form->toArray();

		/** @var array<string, Schema> $properties */
		$properties     = [];
		$required_names = [];

		foreach ($opt['fields'] as $field) {
			$field_name = $field->getName();
			$field_type = $field->getType();

			if ($field_type instanceof TypesSwitcher) {
				// dynamic type: document as a generic value
				$schema = $this->schemas->type(['string', 'number', 'boolean', 'object', 'array', 'null']);
			} else {
				$schema = $this->gobl->typeSchema($field_type);
			}

			$label         = $field->getLabel();
			$label_text    = null !== $label ? (\is_string($label) ? $label : $label->getText()) : null;
			$schema->title = (null !== $label_text && '' !== $label_text)
				? $label_text
				: SchemaBuilder::toHumanReadable($field_name);

			$desc      = $field->getDescription();
			$desc_text = null !== $desc ? (\is_string($desc) ? $desc : $desc->getText()) : null;
			if (null !== $desc_text && '' !== $desc_text) {
				$schema->description = $desc_text;
			} else {
				$schema->description = SchemaBuilder::toHumanReadable($field_name);
			}

			if ($field->isHidden()) {
				$schema->x = ['oz-hidden' => ['name' => 'oz-hidden', 'value' => true]];
			}

			$properties[$field_name] = $schema;

			if ($field->isRequired()) {
				$required_names[] = $field_name;
			}
		}

		$body_schema = $this->schemas->object($properties, [
			'required' => $required_names,
		]);

		return $this->requestBody([
			'application/json' => $this->schemas->json($body_schema),
		]);
	}

	/**
	 * Create an ozone success response.
	 *
	 * @param array|Schema $data             the data
	 * @param null|string  $description      the description
	 * @param null|string  $message          the message
	 * @param int          $http_status_code the HTTP status code
	 */
	public function success(
		array|Schema $data,
		?string $description = '',
		?string $message = 'OK',
		int $http_status_code = 200,
	): Response {
		return $this->response($http_status_code, $description ?? '', [
			'application/json' => $this->payload(JSONResponse::SUCCESS, $message ?? '', $data),
		]);
	}

	/**
	 * Create an ozone error response.
	 *
	 * @param array|Schema $data             the data
	 * @param null|string  $description      the description
	 * @param null|string  $message          the message
	 * @param int          $http_status_code the HTTP status code
	 */
	public function error(
		array|Schema $data,
		?string $description = '',
		?string $message = 'OZ_ERROR_INTERNAL',
		int $http_status_code = 200,
	): Response {
		return $this->response($http_status_code, $description ?? '', [
			'application/json' => $this->payload(JSONResponse::ERROR, $message ?? '', $data),
		]);
	}

	/**
	 * Creates the O'Zone API JSON response schema with a message and data.
	 *
	 * @param int          $ozone_error_code the error code: 0 for success and 1 for error
	 * @param string       $message          the message
	 * @param array|Schema $data             the data
	 */
	public function payload(int $ozone_error_code, string $message, array|Schema $data): OA\MediaType
	{
		$data = $data instanceof Schema ? $data : $this->schemas->object($data);

		$utime = $this->schemas->integer('The response UNIX timestamp.');
		$stime = $this->schemas->integer('The response auth expiration UNIX timestamp.');

		return $this->schemas->json($this->schemas->object([
			'error' => $this->schemas->integer(
				'Indicate if there is an error: `0` for success, `1` for error.',
				['default' => $ozone_error_code]
			),
			'msg'   => $this->schemas->string('The error/success message.', [
				'default' => $message,
			]),
			'data'  => $data,
			'utime' => $utime,
			'stime' => $stime,
		]));
	}

	/**
	 * Create a O'Zone API paginated schema.
	 *
	 * @param array<string,Schema> $properties  The properties
	 * @param int                  $default_max The default maximum number of items per page
	 */
	public function paginated(array $properties, int $default_max = 10): Schema
	{
		return $this->schemas->object($properties + [
			'page'      => $this->schemas->integer('The current page number.', [
				'default' => 1,
			]),
			'max' => $this->schemas->integer('The maximum number of items per page.', [
				'default' => $default_max,
			]),
			'total' => $this->schemas->integer('The total number of items.'),
		]);
	}

	/**
	 * Create a O'Zone API cursor-paginated schema.
	 *
	 * @param array<string,Schema> $properties  The properties
	 * @param int                  $default_max The default maximum number of items per page
	 */
	public function cursorPaginated(array $properties, int $default_max = 10): Schema
	{
		return $this->schemas->object($properties + [
			'next_cursor'   => $this->schemas->type(['string', 'null'], 'The cursor value for the next page.'),
			'cursor_column' => $this->schemas->type(['string', 'null'], 'The column used as cursor.'),
			'has_more'      => $this->schemas->boolean('Whether there are more items to retrieve.'),
			'max'           => $this->schemas->integer('The maximum number of items per page.', [
				'default' => $default_max,
			]),
		]);
	}
}
