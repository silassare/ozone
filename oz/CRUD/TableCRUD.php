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

namespace OZONE\Core\CRUD;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\CRUD\Interfaces\TableCRUDListenerInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\OZone;

/**
 * Class TableCRUD.
 */
final class TableCRUD
{
	/**
	 * Register CRUD event listeners.
	 *
	 * @param Context $context
	 */
	public static function registerListeners(Context $context): void
	{
		// make sure ozone is fully installed first
		if (!OZone::isInstalled()) {
			return;
		}

		$gobl_crud = Settings::load('oz.gobl.crud');

		foreach ($gobl_crud as $listener => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($listener, TableCRUDListenerInterface::class)) {
					throw new RuntimeException(
						\sprintf(
							'CRUD listener "%s" should extends "%s".',
							$listener,
							TableCRUDListenerInterface::class
						)
					);
				}

				/** @var TableCRUDListenerInterface $listener */
				$listener::register($context);
			}
		}
	}
}
