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

namespace OZONE\Core\Access\Interfaces;

use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;

/**
 * Interface AtomicActionInterface.
 */
interface AtomicActionInterface extends ArrayCapableInterface
{
	/**
	 * Gets the action.
	 *
	 * @return string
	 */
	public function getAction(): string;

	/**
	 * Gets the description.
	 *
	 * @return I18nMessage
	 */
	public function getDescription(): I18nMessage;

	/**
	 * Gets the error message.
	 *
	 * @return I18nMessage
	 */
	public function getErrorMessage(): I18nMessage;
}
