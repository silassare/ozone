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

namespace OZONE\Core\Collections;

use OZONE\Core\App\Settings;
use OZONE\Core\Collections\Interfaces\EntityCollectionsProviderInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\OZone;

/**
 * Class EntityCollections.
 */
final class EntityCollections implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	public static function boot(): void
	{
		DbReadyHook::listen(static fn (DbReadyHook $ev) => self::registerCollections($ev));
	}

	/**
	 * Register collections providers.
	 *
	 * @param DbReadyHook $ev
	 */
	private static function registerCollections(DbReadyHook $ev): void
	{
		// make sure ozone is fully installed first
		if (!OZone::isInstalled()) {
			return;
		}

		$providers = Settings::load('oz.gobl.collections');

		foreach ($providers as $provider => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($provider, EntityCollectionsProviderInterface::class)) {
					throw new RuntimeException(
						\sprintf(
							'Table collections provider "%s" should extends "%s".',
							$provider,
							EntityCollectionsProviderInterface::class
						)
					);
				}

				/** @var EntityCollectionsProviderInterface $provider */
				$provider::register($ev->db);
			}
		}
	}
}
