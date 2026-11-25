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

namespace __PLH_NAMESPACE__;

use Gobl\DBAL\Builders\TableBuilder;
use OZONE\Core\App\Db;
use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Hooks\Events\DbSchemaCollectHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;

/**
 * A project's own auth user table beside `oz_users`: `admins`, of type `admin`, made by
 * `makeAuthUserTable()` as any auth user table of a project is.
 */
final class AuthUserPictureAdmins implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		DbSchemaCollectHook::listen(static function (DbSchemaCollectHook $hook): void {
			$hook->db->ns(Db::getProjectDbNamespace())->table('admins', static function (TableBuilder $tb): void {
				$tb->plural('admins')
					->singular('admin')
					->columnPrefix('admin')
					->morphType('admin');

				AuthUsersRepository::makeAuthUserTable($tb);
			});
		});
	}
}
