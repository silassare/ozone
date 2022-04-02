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

namespace OZONE\OZ\Exceptions;

use OZONE\OZ\Exceptions\Traits\ExceptionCustomSuspectTrait;
use PHPUtils\Interfaces\RichExceptionInterface;
use PHPUtils\Traits\RichExceptionTrait;
use Throwable;

/**
 * Class RuntimeException.
 */
class RuntimeException extends \RuntimeException implements RichExceptionInterface
{
	use ExceptionCustomSuspectTrait;
	use RichExceptionTrait;

	/**
	 * RuntimeException constructor.
	 *
	 * @param null|string    $message  the exception message
	 * @param null|array     $data     additional exception data
	 * @param null|Throwable $previous previous throwable used for the exception chaining
	 */
	public function __construct(string $message = null, array $data = null, Throwable $previous = null)
	{
		parent::__construct((empty($message) ? 'OZ_RUNTIME_EXCEPTION' : $message), 0, $previous);

		$this->data = $data ?? [];
	}
}
