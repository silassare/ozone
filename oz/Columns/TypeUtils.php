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
use Gobl\DBAL\Types\TypeString;
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
	 * @return Type
	 */
	public static function birthDate(int $min_age, int $max_age): Type
	{
		try {
			$year     = (int) \date('Y');
			$min_date = \mktime(0, 0, 0, 1, 1, $year - $max_age);
			$max_date = \mktime(23, 59, 59, 12, 31, $year - $min_age);

			$type = new TypeDate();

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
	 * @param string $registered_as
	 *
	 * @return TypePhone
	 */
	public static function userPhone(string $registered_as): TypePhone
	{
		$phone = new TypePhone();
		$phone->notRegistered($registered_as)
			->nullable(!Settings::get('oz.users', 'OZ_USER_PHONE_REQUIRED'));

		return $phone;
	}

	/**
	 * Creates a user name type.
	 *
	 * @param string $registered_as
	 *
	 * @return TypeEmail
	 */
	public static function userMailAddress(string $registered_as): TypeEmail
	{
		$email = new TypeEmail();
		$email->notRegistered($registered_as)
			->nullable(!Settings::get('oz.users', 'OZ_USER_EMAIL_REQUIRED'));

		return $email;
	}

	/**
	 * Returns a morph any id field type.
	 * This is used to store the id of any morphed model.
	 *
	 * @return TypeString
	 *
	 * @throws TypesException
	 */
	public static function morphAnyId(): TypeString
	{
		return (new TypeString())->max(128);
	}
}
