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

namespace OZONE\Core\REST\Enums;

use OZONE\Core\REST\RESTFulService;

/**
 * Enum RESTFulAction.
 *
 * The actions of a {@see RESTFulService}, which drive both its routes and its API docs.
 * Declaration order is the route registration order.
 */
enum RESTFulAction: string
{
	case CREATE_ONE   = 'create_one';
	case GET_ALL      = 'get_all';
	case UPDATE_ALL   = 'update_all';
	case DELETE_ALL   = 'delete_all';
	case GET_ONE      = 'get_one';
	case UPDATE_ONE   = 'update_one';
	case DELETE_ONE   = 'delete_one';
	case GET_RELATION = 'get_relation';

	/**
	 * The HTTP method of the action's route.
	 */
	public function httpMethod(): string
	{
		return match ($this) {
			self::CREATE_ONE                                 => 'POST',
			self::GET_ALL, self::GET_ONE, self::GET_RELATION => 'GET',
			self::UPDATE_ALL, self::UPDATE_ONE               => 'PATCH',
			self::DELETE_ALL, self::DELETE_ONE               => 'DELETE',
		};
	}

	/**
	 * Whether the action targets one entry: its route is under `/:<key column>`.
	 */
	public function onEntry(): bool
	{
		return match ($this) {
			self::GET_ONE, self::UPDATE_ONE, self::DELETE_ONE, self::GET_RELATION => true,
			default                                                               => false,
		};
	}

	/**
	 * The route path, relative to the service path, or to `/:<key column>` for entry actions.
	 */
	public function path(): string
	{
		return self::GET_RELATION === $this ? '/:relation' : '';
	}
}
