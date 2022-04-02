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

namespace OZONE\OZ\Cli;

use Kli\Kli;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\OZ\Loader\ClassLoader;
use PHPUtils\Str;

/**
 * Class Cli.
 */
final class Cli extends Kli
{
	/**
	 * Cli constructor.
	 */
	protected function __construct(protected bool $in_project_root)
	{
		parent::__construct('oz', true);
	}

	/**
	 * Are we in a project root.
	 *
	 * @return bool
	 */
	public function inProjectRoot(): bool
	{
		return $this->in_project_root;
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
	 * @return \OZONE\OZ\Cli\Cli
	 */
	public function log(mixed $msg, bool $wrap = true): self
	{
		oz_logger($msg);

		return $this;
	}

	/**
	 * Runs the commands.
	 *
	 * @param array $arg
	 *
	 * @throws \Kli\Exceptions\KliException
	 *
	 * @return \OZONE\OZ\Cli\Cli
	 */
	public static function run(array $arg): self
	{
		if (!\defined('OZ_OZONE_IS_CLI') || !OZ_OZONE_IS_CLI) {
			echo 'This is the command line tool for OZone Framework.';

			exit(1);
		}

		$title          = 'oz';
		$project_loaded = false;

		if ($config = Utils::loadProjectConfig()) {
			$title .= ':' . Str::stringToURLSlug($config['OZ_PROJECT_NAME']);
			// Adds project namespace root directory
			ClassLoader::addNamespace($config['OZ_PROJECT_NAMESPACE'], OZ_APP_DIR);
			// Init database
			DbManager::init();
			$project_loaded = true;
		}

		\cli_set_process_title($title);

		$cli = new self($project_loaded);

		self::notifyBootHookReceivers($cli);

		$cli->loadCommands()
			->execute($arg);

		return $cli;
	}

	/**
	 * Notify all boot hook receivers.
	 */
	private static function notifyBootHookReceivers(self $cli): void
	{
		$hook_receivers = Configs::load('oz.boot');

		foreach ($hook_receivers as $receiver => $enabled) {
			if ($enabled) {
				if (!\is_subclass_of($receiver, BootHookReceiverInterface::class)) {
					throw new RuntimeException(\sprintf(
						'Boot hook receiver "%s" should implements "%s".',
						$receiver,
						BootHookReceiverInterface::class
					));
				}

				/* @var \OZONE\OZ\Hooks\Interfaces\BootHookReceiverInterface $receiver */
				$receiver::bootCli($cli);
			}
		}
	}

	/**
	 * Loads all defined commands in oz.cli settings.
	 *
	 * @return \OZONE\OZ\Cli\Cli
	 */
	private function loadCommands(): self
	{
		$list = Configs::load('oz.cli');

		if (\is_array($list) && \count($list)) {
			foreach ($list as $cmd_name => $cmd_class) {
				if (!\is_subclass_of($cmd_class, Command::class)) {
					throw new RuntimeException(\sprintf(
						'Your custom command "%s" class "%s" should extends "%s".',
						$cmd_name,
						$cmd_class,
						Command::class
					));
				}

				/* @var \OZONE\OZ\Cli\Command $cmd_class */
				$this->addCommand(new $cmd_class($cmd_name, $this));
			}
		}

		return $this;
	}
}
