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

namespace OZONE\OZ\Auth;

use OZONE\OZ\Auth\Providers\AuthProvider;
use OZONE\OZ\Cli\Cli;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\OZAuthsQuery;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Hooks\Events\FinishHook;
use OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Auth.
 */
final class Auth extends AuthProvider implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		FinishHook::handle(static function () {
			self::gc();
		}, Event::RUN_LAST);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bootCli(Cli $cli): void
	{
	}

	/**
	 * Deletes expired authorization process.
	 */
	private static function gc(): void
	{
		if (Hasher::randomBool()) {
			try {
				$an_hour_ago = \time() - 3600;

				$qb = new OZAuthsQuery();

				$qb->whereExpireIsGt(0)
					->whereExpireIsLte($an_hour_ago)
					->delete()
					->execute();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to delete expired authorization.', null, $t);
			}
		}
	}
}
