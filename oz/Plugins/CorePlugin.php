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

use Override;
use OZONE\Core\App\Db;
use OZONE\Core\App\Settings;

/**
 * Class CorePlugin.
 */
final class CorePlugin extends AbstractPlugin
{
	/**
	 * CorePlugin constructor.
	 */
	public function __construct()
	{
		parent::__construct('ozone', 'OZONE\Core', OZ_OZONE_DIR . '..');
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}

	#[Override]
	public function getSettingsSource(): string
	{
		return self::settingsSource();
	}

	#[Override]
	public function getSchemaFile(): string
	{
		return self::schemaFile();
	}

	#[Override]
	public function getVersion(): string
	{
		return OZ_OZONE_VERSION;
	}

	/**
	 * OZone's own settings: static, because settings are read before any plugin is built.
	 */
	public static function settingsSource(): string
	{
		return OZ_OZONE_DIR . 'oz_settings';
	}

	/**
	 * OZone's own tables.
	 */
	public static function schemaFile(): string
	{
		return OZ_OZONE_DIR . 'oz_default' . DS . 'oz_schema.php';
	}

	/**
	 * Adds nothing: OZone's settings are the first source settings read ({@see Settings::getSources()})
	 * and its tables the first a database collects ({@see Db::loadDevSchemaInto()}), both before any
	 * plugin boots. Added again here, each would be read twice.
	 */
	#[Override]
	protected function addSources(): void {}
}
