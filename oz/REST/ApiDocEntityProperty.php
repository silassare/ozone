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

use Gobl\DBAL\Types\Interfaces\TypeInterface;

class ApiDocEntityProperty
{
	/**
	 * ApiDocEntityProperty constructor.
	 *
	 * @param string        $name
	 * @param TypeInterface $type
	 */
	public function __construct(public string $name, public TypeInterface $type) {}
}
