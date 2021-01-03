<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Exceptions;

use Throwable;

/**
 * Class InternalErrorException
 */
class InternalErrorException extends BaseException
{
	/**
	 * InternalErrorException constructor.
	 *
	 * @param null|string     $message  the exception message
	 * @param null|array      $data     additional exception data
	 * @param null|\Throwable $previous previous throwable used for the exception chaining
	 */
	public function __construct($message = null, array $data = null, Throwable $previous = null)
	{
		parent::__construct((empty($message) ? 'OZ_ERROR_INTERNAL' : $message), BaseException::INTERNAL_ERROR, $data, $previous);
	}
}
