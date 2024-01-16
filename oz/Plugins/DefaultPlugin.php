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

namespace OZONE\Core\Plugins;

/**
 * Class DefaultPlugin.
 */
class DefaultPlugin extends AbstractPlugin
{
	/**
	 * DefaultPlugin constructor.
	 */
	public function __construct()
	{
		parent::__construct('ozone', 'OZONE\\Core', OZ_OZONE_DIR . '..');
	}

	/**
	 * {@inheritDoc}
	 */
	public static function instance(): Interfaces\PluginInterface
	{
		return new self();
	}
}
