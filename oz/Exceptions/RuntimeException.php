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

namespace OZONE\Core\Exceptions;

use OZONE\Core\Exceptions\Traits\ExceptionCustomSuspectTrait;
use OZONE\Core\Lang\I18nMessage;
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
	 * @param null|I18nMessage|string $message  the exception message
	 * @param null|array              $data     additional exception data
	 * @param null|Throwable          $previous previous throwable used for the exception chaining
	 */
	public function __construct(I18nMessage|string $message = null, array $data = null, Throwable $previous = null)
	{
		$this->data = $data ?? [];
		if ($message instanceof I18nMessage) {
			$this->data = \array_merge($message->getInject(), $this->data);
			$message    = $message->getText();
		}
		parent::__construct(empty($message) ? 'OZ_ERROR_RUNTIME' : $message, 0, $previous);
	}
}
