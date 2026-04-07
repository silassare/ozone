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

use OZONE\Core\Router\RouteFormDeclaration;
use OZONE\Core\Router\RouteSharedOptions;

/**
 * Enum RouteFormDocPolicy.
 *
 * Controls how a route's form declaration is represented in generated API documentation.
 *
 * | Value   | `requestBody` in docs     | `x-oz-form` extension                                             |
 * |---------|---------------------------|-------------------------------------------------------------------|
 * | STATIC  | embedded (schema present) | `{policy:'static',  resumable:bool, require_real_context:bool}`   |
 * | OPAQUE  | none                      | `{policy:'opaque',  resumable:bool, require_real_context:bool, provider_name?:str, init_form:null}` |
 * | DYNAMIC | none                      | `{policy:'dynamic', resumable:bool, require_real_context:bool, provider_name?:str, init_form:array|null}` |
 *
 * Usage via {@see RouteSharedOptions::form()}:
 *
 * ```php
 * // Form instance or zero-arg factory -> STATIC (auto-detected, schema embedded in docs):
 * ->form(new MyForm())
 * ->form(fn () => new MyForm())
 *
 * // One-arg+ factory -> DYNAMIC (auto-detected, only x-oz-form extension in docs):
 * ->form(fn (RouteInfo $ri) => buildForm($ri))
 *
 * // Explicitly opaque (form present at request time, fully hidden from docs):
 * ->form(new MyForm(), RouteFormDocPolicy::OPAQUE)
 * ->form(RouteFormDeclaration::opaque(fn (RouteInfo $ri) => ...))
 *
 * // Explicitly dynamic (factory or form treated as dynamic regardless of arity):
 * ->form(RouteFormDeclaration::dynamic(fn (RouteInfo $ri) => ...))
 *
 * // Resumable-form provider (always DYNAMIC):
 * ->form(MyResumableProvider::class)
 * ```
 */
enum RouteFormDocPolicy: string
{
	/**
	 * The form schema is available at doc-generation time and is embedded in the
	 * OpenAPI `requestBody`. Applies to Form instances and zero-arg callables.
	 *
	 * Extension: `x-oz-form: {policy:'static', resumable:bool, require_real_context:bool}`
	 */
	case STATIC = 'static';

	/**
	 * Explicitly hidden from API docs. The form is present and validated at request
	 * time but nothing about its structure is revealed in the generated OpenAPI spec.
	 *
	 * Extension: `x-oz-form: {policy:'opaque', resumable:bool, require_real_context:bool, provider_name?:str, init_form:null}`
	 */
	case OPAQUE = 'opaque';

	/**
	 * The form is dynamic or managed by a resumable-form provider. No `requestBody`
	 * schema is embedded; clients use the `x-oz-form` extension to discover the flow.
	 *
	 * Extension: `x-oz-form: {policy:'dynamic', resumable:bool, require_real_context:bool, provider_name?:str, init_form:array|null}`
	 */
	case DYNAMIC = 'dynamic';
}
