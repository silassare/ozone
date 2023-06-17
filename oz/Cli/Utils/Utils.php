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

namespace OZONE\Core\Cli\Utils;

use Gobl\DBAL\Table;
use Kli\KliOption;
use Kli\Types\KliTypeString;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Cli\Platforms\Interfaces\PlatformInterface;
use OZONE\Core\Cli\Platforms\PlatformDOS;
use OZONE\Core\Cli\Platforms\PlatformLinux;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
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
	 * Checks if provided folder is an ozone project root directory.
	 * If provided folder is null, it will use current working directory.
	 *
	 * @param null|string $folder
	 *
	 * @return null|string
	 */
	public static function isProjectFolder(?string $folder = null): ?string
	{
		if (empty($folder)) {
			$folder = \getcwd();
		}

		$fm   = new FilesManager($folder);
		$path = $fm->resolve('app/app.php');

		return $fm->filter()
			->isFile()
			->check($path) ? $path : null;
	}

	/**
	 * Checks if a project is loaded.
	 *
	 * @return bool
	 */
	public static function isProjectLoaded(): bool
	{
		return null !== self::tryGetProjectApp();
	}

	/**
	 * Checks if a project is loaded and returns the app instance.
	 *
	 * @return null|\OZONE\Core\App\Interfaces\AppInterface
	 */
	public static function tryGetProjectApp(): ?AppInterface
	{
		static $app = null;

		if (null === $app && \defined('OZ_APP_DIR') && \file_exists($app_file = OZ_APP_DIR . 'app.php')) {
			/** @psalm-suppress MissingFile */
			$return = require $app_file;

			if (!$return instanceof AppInterface) {
				throw new RuntimeException(\sprintf(
					'Invalid app instance in "%s", found "%s" while expecting "%s".',
					$app_file,
					\gettype($return),
					AppInterface::class
				));
			}

			$app = $return;
		}

		return $app;
	}

	/**
	 * Asserts if a project is loaded.
	 */
	public static function assertProjectLoaded(): void
	{
		if (!self::tryGetProjectApp()) {
			throw new RuntimeException(\sprintf(
				'Error: there is no ozone project in "%s".'
				. \PHP_EOL . 'Are you in project root folder?',
				\getcwd()
			));
		}
	}

	/**
	 * Asserts if we are in a project folder with database access.
	 */
	public static function assertDatabaseAccess(): void
	{
		try {
			self::assertProjectLoaded();

			// we get connection to make sure that
			// we have access to the database
			// will throw error when something went wrong
			db()->getConnection();
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
	 * @return \OZONE\Core\Cli\Platforms\Interfaces\PlatformInterface
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
