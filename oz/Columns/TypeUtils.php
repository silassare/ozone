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

namespace OZONE\Core\Columns;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeDate;
use OZONE\Core\App\Settings;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Columns\Types\TypePhone;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class TypeUtils.
 */
class TypeUtils
{
	/**
	 * Returns a birth date field.
	 *
	 * @param int $min_age
	 * @param int $max_age
	 *
	 * @return \Gobl\DBAL\Types\Type
	 */
	public static function birthDate(int $min_age, int $max_age): Type
	{
		try {
			$min_date = \sprintf('%s-01-01', \date('Y') - $max_age);
			$max_date = \sprintf('%s-12-31', \date('Y') - $min_age);
			$type     = new TypeDate();

			return $type->min($min_date)
				->max($max_date)
				->format('Y-m-d');
		} catch (TypesException $e) {
			throw new RuntimeException(null, null, $e);
		}
	}

	/**
	 * Creates a user phone type.
	 *
	 * @return TypePhone
	 */
	public static function userPhone(): TypePhone
	{
		$phone = new TypePhone();
		$phone->notRegistered()
			->nullable(!Settings::get('oz.users', 'OZ_USER_PHONE_REQUIRED'));

		return $phone;
	}

	/**
	 * Creates a user name type.
	 *
	 * @return TypeEmail
	 */
	public static function userMailAddress(): TypeEmail
	{
		$email = new TypeEmail();
		$email->notRegistered()
			->nullable(!Settings::get('oz.users', 'OZ_USER_EMAIL_REQUIRED'));

		return $email;
	}
}
