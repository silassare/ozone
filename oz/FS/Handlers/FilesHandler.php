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

namespace OZONE\Core\FS\Handlers;

use Gobl\CRUD\Events\BeforeCreateFlush;
use OZONE\Core\App\Context;
use OZONE\Core\CRUD\TableCRUDListener;
use OZONE\Core\Db\OZFile;

/**
 * Class FilesHandler.
 */
class FilesHandler extends TableCRUDListener
{
	public static function register(Context $context): void
	{
		if (!\class_exists(OZFile::class)) {
			return;
		}

		$crud = OZFile::crud();

		$crud->onBeforeCreateFlush(static function (BeforeCreateFlush $ev) use ($context) {
			if ($context->hasAuthenticatedUser()) {
				$user = $context->auth()->user();

				$ev->setField(OZFile::COL_UPLOADER_TYPE, $user->getAuthUserTypeName());
				$ev->setField(OZFile::COL_UPLOADER_ID, $user->getAuthIdentifier());
			}
		});
	}
}
