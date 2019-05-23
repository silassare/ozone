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

	defined('OZ_SELF_SECURITY_CHECK') or die;

	interface TableCollectionsProviderInterface
	{
		/**
		 * Returns custom collections definition.
		 *
		 * ```
		 * [
		 *    'table_A_name' => [
		 *         'collection_1' => callable,
		 *         'collection_n' => callable
		 *     ],
		 *    'table_B_name' => [
		 *         'collection_1' => callable,
		 *         'collection_n' => callable
		 *     ]
		 * ]
		 * ```
		 *
		 * @return array
		 */
		static function getCollectionsDefinition();
	}