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

namespace OZONE\Core\CRUD;

use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AllowCheckResult.
 */
class AllowCheckResult implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	public function __construct(protected bool $allowed, protected I18nMessage $reason) {}

	/**
	 * Gets the result of the allow check.
	 *
	 * @return bool
	 */
	public function isAllowed(): bool
	{
		return $this->allowed;
	}

	/**
	 * Gets the reason for the allow check result.
	 *
	 * @return I18nMessage
	 */
	public function getReason(): I18nMessage
	{
		return $this->reason;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'allowed' => $this->allowed,
			'reason'  => $this->reason,
		];
	}

	/**
	 * Creates a new AllowCheckResult instance with rejection status.
	 *
	 * @param I18nMessage $reason
	 *
	 * @return self
	 */
	public static function reject(I18nMessage $reason): self
	{
		return new self(false, $reason);
	}

	/**
	 * Creates a new AllowCheckResult instance with approval status.
	 *
	 * @param I18nMessage $reason
	 *
	 * @return self
	 */
	public static function allow(I18nMessage $reason): self
	{
		return new self(true, $reason);
	}
}
