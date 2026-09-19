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
use Override;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\Utils\ErrorUtils;
use OZONE\Core\FS\Assets;
use OZONE\Core\OZone;
use OZONE\Core\Web\BlatePlugin;
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
	 * JSON mode: the command line asked for `--json`, so stdout holds one JSON object ({@see writeJson()})
	 * and nothing else. Set before bootstrap, so an error at any point is answered in JSON.
	 */
	private static bool $json = false;

	/**
	 * What the command reported while in JSON mode, returned with its JSON output.
	 *
	 * @var list<array{level: string, message: string}>
	 */
	private static array $json_messages = [];

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
	#[Override]
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
	 * @return static
	 */
	public static function getInstance(): static
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

				OZone::bootstrap($app);
			} else {
				// Needed for the oz:// protocol when the CLI runs outside a project (no app).
				Assets::register();
				// we need template for project create command,
				// so we bootstrap with blate plugin to have access to ozone custom template helper in blate templates.
				BlatePlugin::register();
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
	#[Override]
	public function welcome(): void
	{
		$this->write(\file_get_contents(OZ_OZONE_DIR . 'welcome'));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function quit(): void
	{
		$this->info('See you soon!');
		parent::quit();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function log(string $level, mixed $msg, array $context = []): static
	{
		oz_logger()->log($level, $msg, $context);

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
		self::$json = self::asksForJson($args);

		ErrorUtils::registerHandlers();

		self::getInstance()
			->execute($args);
	}

	/**
	 * Whether the command line runs in JSON mode (`--json`).
	 */
	public static function inJsonMode(): bool
	{
		return self::$json;
	}

	/**
	 * Writes the result of a command as the envelope every OZone answer uses
	 * ({@see JSONResponse}): `{error, msg, data, utime}`, with what the command reported
	 * under `data.messages`, and terminates. In JSON mode nothing else reaches stdout.
	 *
	 * @param array<string, mixed> $data
	 * @param bool                 $ok   false when the command failed: `error` is then 1
	 * @param int                  $exit the exit code
	 * @param string               $msg  the message code; `OK` for a success
	 */
	public function writeJson(array $data, bool $ok = true, int $exit = 0, string $msg = ''): never
	{
		$json = new JSONResponse();

		$ok
			? $json->setDone('' === $msg ? 'OK' : $msg)
			: $json->setError('' === $msg ? 'OZ_ERROR_INTERNAL' : $msg);

		if (!empty(self::$json_messages)) {
			$data['messages'] = self::$json_messages;
		}

		$payload          = $json->setData($data)->toArray();
		$payload['utime'] = \time();

		// Never wrapped: Kli word-wraps by default, which would break long JSON strings.
		echo \json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), \PHP_EOL;

		$this->terminate($exit);
	}

	#[Override]
	public function writeLn(string $str = '', bool $wrap = true): static
	{
		return self::$json ? $this : parent::writeLn($str, $wrap);
	}

	#[Override]
	public function write(string $str, bool $wrap = false): static
	{
		return self::$json ? $this : parent::write($str, $wrap);
	}

	#[Override]
	public function info(string $msg, bool $wrap = true): static
	{
		if (!self::$json) {
			return parent::info($msg, $wrap);
		}

		self::$json_messages[] = ['level' => 'info', 'message' => $msg];

		return $this;
	}

	#[Override]
	public function warn(string $msg, bool $wrap = true, ?int $exit = null): static
	{
		if (!self::$json) {
			return parent::warn($msg, $wrap, $exit);
		}

		if (null !== $exit) {
			$this->writeJson([], 0 === $exit, $exit, $msg);
		}

		self::$json_messages[] = ['level' => 'warn', 'message' => $msg];

		return $this;
	}

	#[Override]
	public function success(string $msg, bool $wrap = true, ?int $exit = null): static
	{
		if (!self::$json) {
			return parent::success($msg, $wrap, $exit);
		}

		self::$json_messages[] = ['level' => 'success', 'message' => $msg];

		if (null !== $exit) {
			$this->writeJson([], 0 === $exit, $exit);
		}

		return $this;
	}

	#[Override]
	public function error(string $msg, bool $wrap = true, ?int $exit = 1): static
	{
		if (!self::$json) {
			return parent::error($msg, $wrap, $exit);
		}

		if (null !== $exit) {
			$this->writeJson([], false, $exit, $msg);
		}

		self::$json_messages[] = ['level' => 'error', 'message' => $msg];

		return $this;
	}

	/**
	 * Whether a command line asks for JSON output: `--json`, or `--json=<value>` with a true value.
	 *
	 * @param array<int, string> $args
	 */
	private static function asksForJson(array $args): bool
	{
		foreach ($args as $arg) {
			if ('--json' === $arg) {
				return true;
			}

			if (\str_starts_with($arg, '--json=')) {
				return !\in_array(\strtolower(\substr($arg, 7)), ['', '0', 'false', 'no', 'off'], true);
			}
		}

		return false;
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
