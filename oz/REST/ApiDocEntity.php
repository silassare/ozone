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

/**
 * Class ApiDocEntity.
 */
class ApiDocEntity
{
	/**
	 * ApiDocEntity constructor.
	 *
	 * @param string $table_name
	 * @param string $service_path
	 */
	public function __construct(
		public string $table_name,
		public string $service_path
	) {}
}
