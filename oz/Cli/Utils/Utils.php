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

namespace OZONE\OZ\Cli\Utils;

use Gobl\DBAL\Table;
use Kli\KliOption;
use Kli\Types\KliTypeString;
use OZONE\OZ\Cli\Platforms\Interfaces\PlatformInterface;
use OZONE\OZ\Cli\Platforms\PlatformDOS;
use OZONE\OZ\Cli\Platforms\PlatformLinux;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Exceptions\RuntimeException;
use Throwable;

/**
 * Class Utils.
 *
 * @internal
 */
final class Utils
{
	private static ?bool $sig_child = null;

	private static ?array $env = null;

	/**
	 * Try load project config from a given project folder or current working dir.
	 *
	 * @param null|string $folder
	 *
	 * @return null|array
	 */
	public static function tryGetProjectConfig(?string $folder = null): ?array
	{
		if (empty($folder)) {
			$folder = \getcwd();
		}

		$oz_config = $folder . DS . 'api' . DS . 'app' . DS . 'oz_settings' . DS . 'oz.config.php';

		if (\file_exists($oz_config)) {
			$config = include $oz_config;

			if (self::isProjectConfigLike($config)) {
				return $config;
			}
		}

		return null;
	}

	/**
	 * Checks if provided folder is an ozone project root directory.
	 * If provided folder is null, it will use current working directory.
	 *
	 * @param null|string $folder
	 *
	 * @return bool
	 */
	public static function isProjectRootDir(?string $folder = null): bool
	{
		$config = self::tryGetProjectConfig($folder);

		return null !== $config;
	}

	/**
	 * Checks for ozone config.
	 *
	 * @param mixed $config
	 *
	 * @return bool
	 */
	public static function isProjectConfigLike(mixed $config): bool
	{
		return \is_array($config) && isset($config['OZ_PROJECT_NAME']);
	}

	/**
	 * Asserts if a folder or current working directory contains OZone project.
	 *
	 * @param null|string $folder the project folder
	 */
	public static function assertProjectFolder(?string $folder = null): void
	{
		if (!self::isProjectRootDir($folder)) {
			$err = 'Error: there is no ozone project in "%s".' . \PHP_EOL . 'Are you in project root folder?';

			throw new RuntimeException(\sprintf($err, $folder));
		}
	}

	/**
	 * Asserts if we are in a project folder with database access.
	 */
	public static function assertDatabaseAccess(): void
	{
		try {
			self::assertProjectFolder();

			// we get connection to make sure that
			// we have access to the database
			// will throw error when something went wrong
			DbManager::getDb()
				->getConnection();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to access database.', null, $t);
		}
	}

	/**
	 * Checks for Windows environment.
	 *
	 * @return bool
	 */
	public static function isDOS(): bool
	{
		return '\\' === \DIRECTORY_SEPARATOR;
	}

	/**
	 * Returns the current platform.
	 *
	 * @return \OZONE\OZ\Cli\Platforms\Interfaces\PlatformInterface
	 */
	public static function getPlatform(): PlatformInterface
	{
		if (self::isDOS()) {
			return new PlatformDOS();
		}

		return new PlatformLinux();
	}

	/**
	 * Checks if PHP has been compiled with the '--enable-sigchild' option or not.
	 *
	 * @return bool
	 */
	public static function sigChildEnabled(): bool
	{
		if (null !== self::$sig_child) {
			return self::$sig_child;
		}

		\ob_start();
		\phpinfo(\INFO_GENERAL);
		$info = \ob_get_clean();

		if (\str_contains($info, '--enable-sigchild')) {
			self::$sig_child = true;
		}

		return self::$sig_child = false;
	}

	/**
	 * Returns default env.
	 *
	 * @return array
	 */
	public static function getDefaultEnv(): array
	{
		if (null === self::$env) {
			$env     = [];
			$sources = [$_SERVER, $_ENV];

			foreach ($sources as $source) {
				foreach ($source as $k => $v) {
					if (\is_string($v) && false !== ($v = \getenv($k))) {
						$env[$k] = $v;
					}
				}
			}

			self::$env = $env;
		}

		return self::$env;
	}

	/**
	 * Builds cli options from a table.
	 *
	 * @param \Gobl\DBAL\Table $table
	 * @param array            $includes
	 * @param array            $excludes
	 *
	 * @return KliOption[]
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	public static function buildTableCliOptions(Table $table, array $includes = [], array $excludes = []): array
	{
		$options = [];
		foreach ($table->getColumns() as $column) {
			$name = $column->getFullName();
			if (!empty($includes) && !\in_array($name, $includes, true)) {
				continue;
			}
			if (\in_array($name, $excludes, true)) {
				continue;
			}

			$db_type = $column->getType();

			if ($column->isPrivate() || $db_type->isAutoIncremented()) {
				continue;
			}

			$option   = new KliOption($name);
			$kli_type = new KliTypeString();

			$kli_type->validator(function ($value) use ($db_type) {
				return $db_type->validate($value);
			});

			$option->type($kli_type)
				->prompt(true, $name);

			if ($db_type->hasDefault()) {
				$kli_type->def($db_type->getDefault());
			}

			if (!$db_type->isNullable()) {
				$option->required();
			}

			$options[$name] = $option;
		}

		return $options;
	}
}
