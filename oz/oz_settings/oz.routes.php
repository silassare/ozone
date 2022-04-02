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

use OZONE\OZ\FS\Views\GetFilesView;
use OZONE\OZ\Lang\PolyglotRoute;
use OZONE\OZ\Web\Views\RedirectView;

return [
	RedirectView::class     => true,
	GetFilesView::class     => true,
	PolyglotRoute::class    => true,
];
