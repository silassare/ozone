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

namespace OZONE\Core\Access;

use OZONE\Core\Access\Interfaces\AtomicActionInterface;
use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AtomicAction.
 */
class AtomicAction implements AtomicActionInterface
{
	use ArrayCapableTrait;

	public function __construct(
		protected readonly string $action,
		protected readonly I18nMessage $description,
		protected readonly I18nMessage $error,
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'action'      => $this->action,
			'description' => $this->description->toArray(),
			'error'       => $this->error->toArray(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAction(): string
	{
		return $this->action;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): I18nMessage
	{
		return $this->description;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getErrorMessage(): I18nMessage
	{
		return $this->error;
	}
}
