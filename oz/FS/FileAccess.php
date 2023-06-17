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
use OZONE\Core\Auth\Providers\FileAccessAuthProvider;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Router\Guards;
use OZONE\Core\Router\RouteInfo;

/**
 * Class FileAccess.
 */
class FileAccess
{
	/**
	 * @param \OZONE\Core\Db\OZFile        $file
	 * @param \OZONE\Core\Router\RouteInfo $ri
	 * @param string                       $key
	 * @param null|string                  $ref
	 *
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public static function check(OZFile $file, RouteInfo $ri, string $key, ?string $ref = null): void
	{
		$expected = $file->getKey();

		if (!$ref) {
			if (!\hash_equals($expected, $key)) {
				throw new NotFoundException(null, [
					'_reason' => 'Invalid file key.',
				]);
			}
		} else {
			$context = $ri->getContext();
			$auth    = new FileAccessAuthProvider($context, $file);
			$auth->getCredentials()
				->setReference($ref)
				->setToken($key);

			$auth->authorize(AuthSecretType::TOKEN);
		}

		$data = $file->getData();

		if (isset($data['guards_rules']) && \is_array($data['guards_rules'])) {
			$guards = Guards::resolve($data['guards_rules']);

			foreach ($guards as $guard) {
				$guard->checkAccess($ri);
			}
		}
	}
}
