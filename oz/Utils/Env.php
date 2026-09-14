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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use PHPUtils\Env\EnvParser;
use PHPUtils\FS\PathUtils;
use Throwable;

/**
 * Class Env.
 *
 * Values are read from a compiled copy of the file, a PHP array under `.ozone/cache/env/` next to it,
 * named after the file's path and content: an edit makes a new one, and OPcache keeps it in memory.
 * Parsing the file, for every request under PHP-FPM, cost more than the rest of reading it.
 */
class Env
{
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
	 * @param string $path The path to the env file
	 */
	public function __construct(string $path)
	{
		$this->path = PathUtils::isRelative($path) ? app()->getProjectDir()->resolve($path) : $path;

		[$this->values, $this->signature] = self::compiled($this->path);
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
	 *
	 * @return string
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
	 *
	 * @return null|bool|float|int|string
	 */
	public function get(string $key, mixed $default = null): bool|float|int|string|null
	{
		return $this->values[$key] ?? $default;
	}

	/**
	 * Upsets an environment variable.
	 *
	 * @param string                $key
	 * @param bool|float|int|string $value
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
						throw (new RuntimeException('Invalid value type.'))
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
					throw (new RuntimeException('Invalid value type.'))
						->suspectObject((object) $patch_envs, $key);
				}
			}
		}

		app()->getProjectDir()->wf($this->path, (string) $editor);

		$this->env = null;

		[$this->values, $this->signature] = self::compiled($this->path);

		return $this;
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

		// Next to the file: in its project's .ozone/, even for a .env linked from a shared directory.
		$dir       = \dirname($path) . DS . '.ozone' . DS . 'cache' . DS . 'env';
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

			(new FilesManager($dir))->writeAtomic(
				\basename($file),
				'<?php' . \PHP_EOL . \PHP_EOL . 'return ' . \var_export($values, true) . ';' . \PHP_EOL
			);
		} catch (Throwable) {
			// another process got there first, or the cache directory is not writable
		}

		return [$values, $signature];
	}
}
