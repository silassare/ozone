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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Collections\Interfaces\EntityCollectionsProviderInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;

/**
 * Class EntityCollections.
 */
final class EntityCollections implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		DbReadyHook::listen(self::registerCollections(...));
	}

	/**
	 * Register collections providers.
	 *
	 * @param DbReadyHook $ev
	 */
	private static function registerCollections(DbReadyHook $ev): void
	{
		// A database a migration was installed on (a settings read, not the two queries of
		// OZone::isInstalled(), whose super admin collections do not depend on).
		if (Migrations::DB_NOT_INSTALLED_VERSION === Migrations::getInstalledDbVersion()) {
			return;
		}

		self::registerProviders($ev->db, Settings::load('oz.gobl.collections'));
	}

	/**
	 * Registers the enabled providers of a `oz.gobl.collections` map.
	 *
	 * @param array<string, bool> $providers provider class -> enabled
	 */
	private static function registerProviders(RDBMSInterface $db, array $providers): void
	{
		foreach ($providers as $provider => $enabled) {
			if (!$enabled) {
				continue;
			}

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
			$provider::register($db);
		}
	}
}
