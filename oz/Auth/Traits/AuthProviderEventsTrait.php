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

namespace OZONE\Core\Auth\Traits;

use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnauthorizedException;

/**
 * Trait AuthProviderEventsTrait.
 */
trait AuthProviderEventsTrait
{
	/**
	 * Called when a new auth process start.
	 *
	 * @param OZAuth $auth
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
	 * Called when the auth process is refreshed.
	 *
	 * @param OZAuth $auth
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
	 * @param OZAuth $auth
	 *
	 * @throws InvalidFormException
	 */
	protected function onInvalidRefreshKey(OZAuth $auth): void
	{
		throw new InvalidFormException('OZ_AUTH_INVALID_REFRESH_KEY', $this->debug($auth));
	}

	/**
	 * Called when the auth process is canceled.
	 *
	 * @param OZAuth $auth
	 */
	protected function onCancel(OZAuth $auth): void
	{
		$this->json_response->setDone()
			->setData([
				OZAuth::COL_REF => $auth->ref,
			]);
	}

	/**
	 * Called when the auth process succeeded.
	 *
	 * @param OZAuth $auth
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
	 * Called when the auth entity has expired.
	 *
	 * @param OZAuth $auth
	 *
	 * @throws UnauthorizedException
	 */
	protected function onExpired(OZAuth $auth): void
	{
		throw new UnauthorizedException('OZ_AUTH_HAS_EXPIRED', $this->debug($auth));
	}

	/**
	 * Called when the provided code is invalid.
	 *
	 * @param OZAuth $auth
	 *
	 * @throws InvalidFormException
	 */
	protected function onInvalidCode(OZAuth $auth): void
	{
		throw new InvalidFormException('OZ_AUTH_INVALID_CODE', $this->debug($auth));
	}

	/**
	 * Called when the provided token is invalid.
	 *
	 * @param OZAuth $auth
	 *
	 * @throws InvalidFormException
	 */
	protected function onInvalidToken(OZAuth $auth): void
	{
		throw new InvalidFormException('OZ_AUTH_INVALID_TOKEN', $this->debug($auth));
	}

	/**
	 * Called when attempt exceeded try_max.
	 *
	 * @param OZAuth $auth
	 *
	 * @throws UnauthorizedException
	 */
	protected function onTooMuchRetry(OZAuth $auth): void
	{
		throw new UnauthorizedException('OZ_AUTH_TOO_MUCH_ATTEMPT', $this->debug($auth));
	}

	/**
	 * Generate debug info to be passed to exception.
	 *
	 * @param OZAuth $auth
	 *
	 * @return array[]
	 */
	private function debug(OZAuth $auth): array
	{
		return ['_debug' => [OZAuth::COL_REF => $auth->ref]];
	}
}
