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

namespace OZONE\OZ\Core;

use Exception;
use OZONE\OZ\Exceptions\InvalidFormException;

/**
 * Class Assert.
 */
final class Assert
{
	/**
	 * asserts if a given form contains has all required fields.
	 *
	 * @param mixed                 $form            the form to be checked
	 * @param array                 $required_fields the required fields
	 * @param null|Exception|string $error_msg       the error message
	 * @param mixed                 $error_data      the error data
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws string
	 */
	public static function assertForm(
		mixed $form,
		array $required_fields,
		Exception|string|null $error_msg = 'OZ_ERROR_INVALID_FORM',
		array $error_data = null
	): void {
		if (empty($form) || !\is_array($form)) {
			$safe = false;
		} else {
			$safe = true;

			foreach ($required_fields as $field) {
				if (!isset($form[$field])) {
					$safe = false;

					break;
				}
			}
		}

		if (!$safe) {
			if (!$error_msg instanceof Exception) {
				$error_msg = new InvalidFormException($error_msg, $error_data);
			}

			throw $error_msg;
		}
	}
}
