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

namespace OZONE\Core\Router\Enums;

use OZONE\Core\Router\RouteSharedOptions;

/**
 * Enum RouteFormDocPolicy.
 *
 * Controls how a route's form declaration is represented in generated API documentation.
 *
 * | Value            | `requestBody` in docs | `x-oz-form` extension |
 * |------------------|-----------------------|-----------------------|
 * | AUTO             | embedded if static    | none                  |
 * | OPAQUE           | none                  | `{policy: opaque}`    |
 * | EXTERNAL         | none                  | `{policy: external}`  |
 *
 * Usage via {@see RouteSharedOptions::form()}:
 *
 * ```php
 * // Static form (0-arg factory) - AUTO detects it as documentable:
 * ->form(new MyForm())
 * ->form(fn () => new MyForm())
 *
 * // Dynamic form (1-arg factory) - AUTO detects it as opaque:
 * ->form(fn (RouteInfo $ri) => buildForm($ri))
 *
 * // Explicitly opaque (internal form, not shown in docs):
 * ->form(new MyForm(), RouteFormDocPolicy::OPAQUE)
 * ->form(RouteFormDeclaration::opaque(fn (RouteInfo $ri) => ...))
 *
 * // External (client fetches schema from GET /forms/{key}):
 * ->form(RouteFormDeclaration::external(fn (RouteInfo $ri) => ...))
 * ```
 */
enum RouteFormDocPolicy: string
{
	/**
	 * Automatic detection.
	 *
	 * A Form instance or zero-arg callable is treated as static and embedded in the spec.
	 * A one-arg+ callable is treated as dynamic and produces no requestBody in the spec.
	 */
	case AUTO = 'auto';

	/**
	 * Explicitly hidden from API docs.
	 *
	 * The form works normally at request time but does not appear in the generated OpenAPI spec.
	 * An `x-oz-form: {policy: opaque}` extension is added to the operation instead.
	 */
	case OPAQUE = 'opaque';

	/**
	 * Signals clients to fetch the form structure from an external endpoint.
	 *
	 * The form works normally at request time but the spec shows only an
	 * `x-oz-form: {policy: external}` extension rather than embedding the schema.
	 */
	case EXTERNAL = 'external';
}
