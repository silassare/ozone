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

namespace OZONE\Core\Forms;

use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FormValidationContext.
 */
class FormValidationContext
{
	use ArrayCapableTrait;

	/**
	 * ValidationContext constructor.
	 *
	 * @param \OZONE\Core\Forms\FormData $unsafe_fd
	 * @param \OZONE\Core\Forms\FormData $clean_fd
	 */
	public function __construct(
		protected readonly FormData $unsafe_fd,
		protected readonly FormData $clean_fd,
	) {
	}

	/**
	 * Gets the unsafe form data.
	 *
	 * @return \OZONE\Core\Forms\FormData
	 */
	public function getUnsafeFormData(): FormData
	{
		return $this->unsafe_fd;
	}

	/**
	 * Gets the clean form data.
	 *
	 * @return \OZONE\Core\Forms\FormData
	 */
	public function getCleanFormData(): FormData
	{
		return $this->clean_fd;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'unsafe' => $this->unsafe_fd->getData(),
			'clean'  => $this->clean_fd->getData(),
		];
	}
}
