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

namespace OZONE\Core\FS;

use OZONE\Core\Auth\AuthSecretType;
use OZONE\Core\Auth\Providers\FileAuthProvider;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class FileAccess.
 */
class FileAccess
{
	/**
	 * Checks if the given file can be accessed.
	 *
	 * If the file has guards rules, they will be checked.
	 *
	 * @param OZFile      $file
	 * @param RouteInfo   $ri
	 * @param string      $auth_key
	 * @param null|string $auth_ref
	 *
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public static function check(OZFile $file, RouteInfo $ri, string $auth_key, ?string $auth_ref = null): void
	{
		$expected = $file->getKey();

		// if no auth ref, we check the file key as this is a direct access
		if (!$auth_ref) {
			if (!\hash_equals($expected, $auth_key)) {
				throw new NotFoundException(null, [
					'_reason' => 'Invalid file key.',
				]);
			}
		} else {
			$context = $ri->getContext();
			$auth    = new FileAuthProvider($context, $file);
			$auth->getCredentials()
				->setReference($auth_ref)
				->setToken($auth_key);

			$auth->authorize(AuthSecretType::TOKEN);
		}

		$guards = $file->getAccessGuards();

		if (!empty($guards)) {
			foreach ($guards as $guard) {
				$guard->checkAccess($ri);
			}
		}
	}
}
