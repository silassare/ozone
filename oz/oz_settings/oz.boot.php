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

use OZONE\Core\App\GarbageCollector;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Auth2FA;
use OZONE\Core\Cli\Cron\CronRunner;
use OZONE\Core\Collections\EntityCollections;
use OZONE\Core\FS\Assets;
use OZONE\Core\FS\Filters\ImageFileFilterHandler;
use OZONE\Core\FS\Scan\FileScan;
use OZONE\Core\FS\TempFS;
use OZONE\Core\Hooks\MainBootHookReceiver;
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\Queue\QueueBootHookReceiver;
use OZONE\Core\Sessions\Session;
use OZONE\Core\Stores\StoresGarbageCollector;
use OZONE\Core\Web\BlatePlugin;

return [
	MainBootHookReceiver::class   => true,
	Polyglot::class               => true,
	Assets::class                 => true,
	EntityCollections::class      => true,
	Session::class                => true,
	Auth::class                   => true,
	Auth2FA::class                => true,
	TempFS::class                 => true,
	BlatePlugin::class            => true,
	QueueBootHookReceiver::class  => true,
	StoresGarbageCollector::class => true,
	GarbageCollector::class       => true,
	CronRunner::class             => true,
	FileScan::class               => true,
	ImageFileFilterHandler::class => true,
];
