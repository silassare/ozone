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

namespace OZONE\Core\Utils;

use OZONE\Core\App\InstanceLayout;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use PHPUtils\Env\EnvParser;
use PHPUtils\FS\PathUtils;
use Throwable;

/**
 * Class Env.
 *
 * Values are read from a compiled copy of the file, a PHP array under `.ozone/build/env/` next to it,
 * named after the file's path and content: an edit makes a new one, and OPcache keeps it in memory.
 * Parsing the file, for every request under PHP-FPM, cost more than the rest of reading it.
 */
class Env
{
	/**
	 * A name an env file can hold, which is what the process environment is filtered by: a process
	 * may carry names no file could (an exported shell function among them).
	 */
	public const string VAR_NAME_PATTERN = '~^[A-Za-z_][A-Za-z0-9_]*$~';

	protected ?EnvParser $env = null;
	protected string $path;

	/**
	 * @var array<string, null|bool|float|int|string>
	 */
	protected array $values;

	protected string $signature;

	/**
	 * Env constructor.
	 *
	 * @param null|string $path the path to the env file, or null for the process environment
	 *                          ({@see self::ofProcess()}, which is what names that case)
	 */
	public function __construct(?string $path = null)
	{
		if (null === $path) {
			$this->path   = '';
			$this->values = self::processValues();
			// There is no file to key a cache by, so the values themselves are what identifies them.
			$this->signature = \hash('xxh128', \serialize($this->values));

			return;
		}

		$this->path = PathUtils::isRelative($path) ? app()->getProjectDir()->resolve($path) : $path;

		[$this->values, $this->signature] = self::compiled($this->path);
	}

	/**
	 * The variables the process itself was given, for a process with no project to read a file in.
	 *
	 * What {@see OZone::env()} answers when no app is running and no project directory is known:
	 * `getenv()` is the only environment there is then. It has no file, so {@see self::getPath()}
	 * answers an empty string and {@see self::patch()} refuses.
	 */
	public static function ofProcess(): static
	{
		return new static();
	}

	/**
	 * A hash of the file's content: what a cache built from its values is to be keyed by.
	 */
	public function getSignature(): string
	{
		return $this->signature;
	}

	/**
	 * Gets the path to the env file.
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Gets an environment variable.
	 *
	 * @param string     $key     the env key
	 * @param null|mixed $default the default value
	 */
	public function get(string $key, mixed $default = null): bool|float|int|string|null
	{
		return $this->values[$key] ?? $default;
	}

	/**
	 * Upsets an environment variable.
	 *
	 * @return $this
	 */
	public function upset(string $key, bool|float|int|string $value): static
	{
		return $this->patch([$key => $value]);
	}

	/**
	 * Upsets multiple environment variables.
	 *
	 * @param array<string, array{type:string, value:mixed}|bool|float|int|string> $patch_envs
	 *
	 * @return $this
	 */
	public function patch(array $patch_envs): static
	{
		if ('' === $this->path) {
			throw new RuntimeException('The process environment has no file to patch.');
		}

		$editor = ($this->env ??= EnvParser::fromFile($this->path))->edit();

		foreach ($patch_envs as $key => $options) {
			if (\is_array($options) && isset($options['type'], $options['value'])) {
				switch ($options['type']) {
					case 'bool':
						$editor->upset($key, $options['value'] ? 'true' : 'false');

						break;

					case 'number':
						$editor->upset($key, $options['value']);

						break;

					case 'string':
						$editor->upset($key, $options['value'], false, true);

						break;

					default:
						throw new RuntimeException('Invalid value type.')
							->suspectObject((object) $patch_envs, $key . '.type');
				}
			} else {
				$value = $options;
				if (\is_bool($value)) {
					$editor->upset($key, $value ? 'true' : 'false');
				} elseif (\is_numeric($value)) {
					$editor->upset($key, (string) $value);
				} elseif (\is_string($value)) {
					$editor->upset($key, $value, false, true);
				} else {
					throw new RuntimeException('Invalid value type.')
						->suspectObject((object) $patch_envs, $key);
				}
			}
		}

		// The path is absolute (see the constructor): no running app is needed to write it, so a tool
		// can edit a project's .env from outside (OZONE\Core\Testing\OZTestProject::writeEnv()).
		new FilesManager(\dirname($this->path))->wf(\basename($this->path), (string) $editor);

		$this->env = null;

		[$this->values, $this->signature] = self::compiled($this->path);

		return $this;
	}

	/**
	 * The process environment, read the way an env file's values are.
	 *
	 * The parser does the reading, over a document made of the variables, so `true` is a bool and
	 * `3306` an int here exactly as in a file rather than by a second set of rules. What a document
	 * cannot carry -- a name an env file could not hold, a value holding a line break -- is left out
	 * of it and kept as the string the process gave.
	 *
	 * @return array<string, null|bool|float|int|string>
	 */
	private static function processValues(): array
	{
		$given  = [];
		$simple = [];

		foreach (\getenv() as $key => $value) {
			if (!\is_string($key) || !\preg_match(self::VAR_NAME_PATTERN, $key) || !\is_string($value)) {
				continue;
			}

			$given[$key] = $value;

			if (!\str_contains($value, "\n") && !\str_contains($value, "\r")) {
				$simple[$key] = $value;
			}
		}

		$editor = EnvParser::fromString('')->edit();

		foreach ($simple as $key => $value) {
			$editor->upset($key, $value, false, self::needsQuotes($value));
		}

		return \array_replace($given, EnvParser::fromString((string) $editor)->getEnvs());
	}

	/**
	 * Whether a value has to be written quoted, which also stops the parser reading it as a number
	 * or a boolean: everything that is not a bare word or a number is.
	 */
	private static function needsQuotes(string $value): bool
	{
		return '' === $value || 1 !== \preg_match('~^[A-Za-z0-9_.+-]+$~', $value);
	}

	/**
	 * The values of an env file, from its compiled copy (compiled when there is none for its content),
	 * and a hash of its content.
	 *
	 * @return array{0: array<string, null|bool|float|int|string>, 1: string}
	 */
	private static function compiled(string $path): array
	{
		$content = \file_get_contents($path);

		if (false === $content) {
			throw new RuntimeException(\sprintf('Unable to read the env file "%s".', $path));
		}

		// Next to the file: in its release's build directory, even for a .env linked from a shared
		// directory. The name is the env file's path, so a scope with its own .env gets its own copy.
		$dir       = InstanceLayout::releaseBuildPath(\dirname($path), InstanceLayout::BUILD_ENV);
		$name      = \hash('xxh128', $path);
		$signature = \hash('xxh128', $content);
		$file      = $dir . DS . $name . '.' . $signature . '.php';

		if (\is_file($file)) {
			$values = include $file;

			if (\is_array($values)) {
				return [$values, $signature];
			}
		}

		$values = EnvParser::fromString($content)->getEnvs();

		// Best effort: the values are known either way, and the next process compiles them again.
		try {
			if (!\is_dir($dir) && !\mkdir($dir, 0o775, true) && !\is_dir($dir)) {
				return [$values, $signature];
			}

			// The copies of earlier contents of the same file.
			foreach (\glob($dir . DS . $name . '.*.php') ?: [] as $old) {
				\unlink($old);
			}

			new FilesManager($dir)->writeAtomic(
				\basename($file),
				'<?php' . \PHP_EOL . \PHP_EOL . 'return ' . \var_export($values, true) . ';' . \PHP_EOL
			);
		} catch (Throwable) {
			// another process got there first, or the cache directory is not writable
		}

		return [$values, $signature];
	}
}
