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

namespace OZONE\Core\Users;

use OZONE\Core\Db\OZCountriesQuery;
use OZONE\Core\Db\OZCountry;
use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class Countries.
 */
final class Countries
{
	/**
	 * Gets country info by cc2 (country code 2).
	 *
	 * @param string $cc2 the country code 2
	 *
	 * @return null|\OZONE\Core\Db\OZCountry
	 */
	public static function get(string $cc2): ?OZCountry
	{
		if (!empty($cc2) && 2 === \strlen($cc2)) {
			try {
				$cq = new OZCountriesQuery();

				return $cq->whereCc2Is($cc2)
					->find(1)
					->fetchClass();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load country info.', [
					'cc2' => $cc2,
				], $t);
			}
		}

		return null;
	}

	/**
	 * Checks if a country (with a given cc2) is allowed.
	 *
	 * @param string $cc2 the country code 2
	 *
	 * @return bool
	 */
	public static function allowed(string $cc2): bool
	{
		if ($country = self::get($cc2)) {
			return $country->isValid();
		}

		return false;
	}
}
