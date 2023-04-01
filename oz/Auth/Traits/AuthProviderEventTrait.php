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

namespace OZONE\OZ\Auth\Traits;

use OZONE\OZ\Db\OZAuth;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Exceptions\UnauthorizedActionException;

/**
 * Trait AuthProviderEventTrait.
 */
trait AuthProviderEventTrait
{
	/**
	 * Called when a new authorization process start.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 */
	protected function onInit(OZAuth $auth): void
	{
		$this->json_response
			->setDone()
			->setData([
				OZAuth::COL_REF         => $auth->getRef(),
				OZAuth::COL_REFRESH_KEY => $auth->getRefreshKey(),
			]);
	}

	/**
	 * Called when the authorization process is refreshed.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 */
	protected function onRefresh(OZAuth $auth): void
	{
		$this->json_response
			->setDone()
			->setData([
				OZAuth::COL_REF         => $auth->getRef(),
				OZAuth::COL_REFRESH_KEY => $auth->getRefreshKey(),
			]);
	}

	/**
	 * Called when the provided refresh key is invalid.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	protected function onInvalidRefreshKey(OZAuth $auth): void
	{
		throw new InvalidFormException('OZ_AUTH_INVALID_REFRESH_KEY', $this->debug($auth));
	}

	/**
	 * Called when the authorization process is canceled.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 */
	protected function onCancel(OZAuth $auth): void
	{
		$this->json_response->setDone()
			->setData([
				OZAuth::COL_REF => $auth->ref,
			]);
	}

	/**
	 * Called when the authorization process succeeded.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 */
	protected function onAuthorized(OZAuth $auth): void
	{
		$this->json_response->setDone()
			->setData([
				OZAuth::COL_REF         => $auth->getRef(),
				OZAuth::COL_REFRESH_KEY => $auth->getRefreshKey(),
			]);
	}

	/**
	 * Called when the authorization has expired.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	protected function onExpired(OZAuth $auth): void
	{
		throw new UnauthorizedActionException('OZ_AUTH_HAS_EXPIRED', $this->debug($auth));
	}

	/**
	 * Called when the provided code is invalid.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	protected function onInvalidCode(OZAuth $auth): void
	{
		throw new InvalidFormException('OZ_AUTH_INVALID_CODE', $this->debug($auth));
	}

	/**
	 * Called when the provided token is invalid.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	protected function onInvalidToken(OZAuth $auth): void
	{
		throw new InvalidFormException('OZ_AUTH_INVALID_TOKEN', $this->debug($auth));
	}

	/**
	 * Called when authorization attempt exceeded try_max.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	protected function onTooMuchRetry(OZAuth $auth): void
	{
		throw new UnauthorizedActionException('OZ_AUTH_TOO_MUCH_ATTEMPT', $this->debug($auth));
	}

	/**
	 * Generate debug info to be passed to exception.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 *
	 * @return array[]
	 */
	private function debug(OZAuth $auth): array
	{
		return ['_debug' => [OZAuth::COL_REF => $auth->ref]];
	}
}
