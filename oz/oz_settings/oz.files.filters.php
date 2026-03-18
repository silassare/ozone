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

use OZONE\Core\FS\Filters\ImageFileFilterHandler;

/**
 * File filter handler registry.
 *
 * Map filter handler classes to `true` to enable them.
 * Handlers are checked in the order they appear here; the first whose
 * `canHandle()` returns true is used for the request.
 *
 * To add support for new file types (e.g. PDF thumbnails, video screenshots),
 * implement FileFilterHandlerInterface and register the class below or call
 * FileFilters::register() in your plugin's boot() method (higher priority).
 */
return [
	ImageFileFilterHandler::class => true,
];
