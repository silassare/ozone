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

namespace OZONE\OZ\FS;

use OZONE\OZ\Auth\AuthSecretType;
use OZONE\OZ\Auth\Providers\AuthFile;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Db\OZFile;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Router\RouteGuard;

/**
 * Class FileAccess.
 */
class FileAccess
{
	/**
	 * FileAccess constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param \OZONE\OZ\Db\OZFile    $file
	 */
	public function __construct(protected Context $context, protected OZFile $file)
	{
	}

	/**
	 * FileAccess destructor.
	 */
	public function __destruct()
	{
		unset($this->context, $this->file);
	}

	/**
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function check(string $key, ?string $ref = null): void
	{
		$expected = $this->file->getKey();

		if (!$ref) {
			if (!\hash_equals($expected, $key)) {
				throw new NotFoundException(null, [
					'_reason' => 'Invalid file key.',
				]);
			}
		} else {
			$auth = new AuthFile($this->context);
			$auth->getScope()->setValue($this->file->getID());
			$auth->getCredentials()
				->setReference($ref)
				->setToken($key);

			$auth->authorize(AuthSecretType::TOKEN);
		}

		$data = $this->file->getData();

		if (isset($data['access_rules'])) {
			$guard = new RouteGuard($this->context, $data['access_rules'] ?? []);

			$guard->assertHasAccess();
		}
	}

	/**
	 * @return \OZONE\OZ\Db\OZFile
	 */
	public function getFile(): OZFile
	{
		return $this->file;
	}
}
