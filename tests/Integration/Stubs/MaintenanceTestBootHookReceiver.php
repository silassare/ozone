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

use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Router\Events\RouteNotFound;
use PHPUtils\Events\Event;

/**
 * Answers every path no route matches with a page of its own, as a project serving a client-side
 * application from any path does.
 */
final class MaintenanceTestBootHookReceiver implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		RouteNotFound::listen(static function (RouteNotFound $event): void {
			$context = $event->context;

			$context->respond($context->getResponse()->withJson(['fallback' => true]));
		}, Event::RUN_FIRST);
	}
}
