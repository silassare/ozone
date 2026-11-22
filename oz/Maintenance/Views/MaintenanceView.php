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

namespace OZONE\Core\Maintenance\Views;

use OZONE\Core\Http\Response;
use OZONE\Core\Web\WebView;

/**
 * Class MaintenanceView.
 *
 * The page a web request held by a maintenance gets, from `oz.maintenance.blate`: a project, or a
 * scope, overrides that template as it overrides any of OZone's.
 */
final class MaintenanceView extends WebView
{
	public const string TEMPLATE = 'oz.maintenance.blate';

	/**
	 * Renders the page of a maintenance.
	 *
	 * @param null|string $message what the maintenance asked the page to say, beside its own text
	 */
	public function renderMaintenance(?string $message): Response
	{
		$this->inject([
			'oz_maintenance_message' => $message,
		]);

		return $this->setTemplate(self::TEMPLATE)
			->respond();
	}
}
