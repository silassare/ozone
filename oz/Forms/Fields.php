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

namespace OZONE\OZ\Forms;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeDate;
use OZONE\OZ\Columns\Types\TypePassword;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Exceptions\RuntimeException;

/**
 * Class Fields.
 */
class Fields
{
	/**
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
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	public static function checkPassVPass(array $request): string
	{
		$form = new Form();
		$form->addField(new Field('pass', new TypePassword(), true));
		$form->addField(new Field('vpass', new TypePassword(), true));

		$data = $form->validate($request);

		if ($data['pass'] !== $data['vpass']) {
			throw new InvalidFormException('OZ_FIELD_PASS_AND_VPASS_NOT_EQUAL');
		}

		return $data['pass'];
	}
}
