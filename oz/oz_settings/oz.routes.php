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

use OZONE\Core\Auth\Views\AuthLinkView;
use OZONE\Core\Cli\Cron\CronEndpoint;
use OZONE\Core\FS\Views\GetFilesView;
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\REST\Views\ApiDocView;
use OZONE\Core\Web\Views\RedirectView;

return [
	RedirectView::class  => true,
	ApiDocView::class    => true,
	GetFilesView::class  => true,
	AuthLinkView::class  => true,
	Polyglot::class      => true,
	CronEndpoint::class  => true,
];
