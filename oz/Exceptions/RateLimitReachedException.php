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

use OZONE\Core\Lang\I18nMessage;
use Throwable;

/**
 * Class RateLimitReachedException.
 */
class RateLimitReachedException extends BaseException
{
	/**
	 * The message code a client is sent by default: what the API documentation states for a 429.
	 */
	public const string DEFAULT_MESSAGE = 'OZ_ERROR_RATE_LIMIT_REACHED';

	/**
	 * RateLimitReachedException constructor.
	 *
	 * @param null|I18nMessage|string $message  the exception message
	 * @param null|array              $data     additional exception data
	 * @param null|Throwable          $previous previous throwable used for the exception chaining
	 */
	public function __construct(
		I18nMessage|string|null $message = null,
		?array $data = null,
		?Throwable $previous = null
	) {
		parent::__construct(
			empty($message) ? self::DEFAULT_MESSAGE : $message,
			$data,
			$previous,
			BaseException::RATE_LIMIT_REACHED,
		);
	}
}
