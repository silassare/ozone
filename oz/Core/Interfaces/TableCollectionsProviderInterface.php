<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core\Interfaces;

interface TableCollectionsProviderInterface
{
	/**
	 * Returns custom collections definition.
	 *
	 * ```php
	 * [
	 *    'table_name' => [
	 *         'collection_1' => callable,
	 *            ...
	 *         'collection_n' => callable
	 *     ],
	 *      ...
	 * ]
	 * ```
	 *
	 * @return array
	 */
	public static function getCollectionsDefinition();
}
