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

namespace OZONE\Core\Cli;

use Kli\Exceptions\KliException;
use Kli\Kli;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\OZone;
use PHPUtils\Str;

/**
 * Class Cli.
 */
final class Cli extends Kli
{
	/**
	 * @var null|Cli The Cli singleton instance
	 */
	private static ?Cli $instance = null;

	/**
	 * Cli constructor.
	 */
	private function __construct()
	{
		parent::__construct('oz', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion(bool $full = false): string
	{
		if ($full) {
			return OZ_OZONE_VERSION_NAME;
		}

		return OZ_OZONE_VERSION;
	}

	/**
	 * Gets the Cli instance.
	 *
	 * @return Cli
	 */
	public static function getInstance(): self
	{
		if (null === self::$instance) {
			if (!OZone::isCliMode()) {
				echo 'This is the command line tool for OZone Framework.';

				exit(1);
			}

			$title = 'oz';

			if ($app = Utils::tryGetProjectApp()) {
				$project_name = Settings::get('oz.config', 'OZ_PROJECT_NAME');

				$title = \sprintf('oz:%s', Str::stringToURLSlug($project_name));

				OZone::run($app);
			}

			\cli_set_process_title($title);

			self::$instance = $cli = new self();

			$cli->loadCommands();

			// collect cron tasks
			Cron::collect();
		}

		return self::$instance;
	}

	/**
	 * {@inheritDoc}
	 */
	public function welcome(): void
	{
		$this->write(\file_get_contents(OZ_OZONE_DIR . 'welcome'));
	}

	/**
	 * {@inheritDoc}
	 */
	public function quit(): void
	{
		$this->info('See you soon!');
		parent::quit();
	}

	/**
	 * Creates log file or append to existing.
	 *
	 * @param mixed $msg  the message to log
	 * @param bool  $wrap to wrap string or not
	 *
	 * @return Cli
	 */
	public function log(mixed $msg, bool $wrap = true): self
	{
		oz_logger($msg);

		return $this;
	}

	/**
	 * Runs the commands.
	 *
	 * @param array $args
	 *
	 * @throws KliException
	 */
	public static function run(array $args): void
	{
		self::getInstance()
			->execute($args);
	}

	/**
	 * Loads all defined commands in oz.cli settings.
	 */
	private function loadCommands(): void
	{
		$list = Settings::load('oz.cli');

		foreach ($list as $cmd_name => $cmd_class) {
			if (!\is_subclass_of($cmd_class, Command::class)) {
				throw new RuntimeException(
					\sprintf(
						'Your custom command "%s" class "%s" should extends "%s".',
						$cmd_name,
						$cmd_class,
						Command::class
					)
				);
			}

			/* @var \OZONE\Core\Cli\Command $cmd_class */
			$this->addCommand($cmd_class::instance($cmd_name, $this));
		}
	}
}
