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
use PHPUtils\Env\EnvParser;

/**
 * Class Env.
 */
class Env
{
	protected EnvParser $env;
	protected string $path;

	/**
	 * Env constructor.
	 *
	 * @param string $path The path to the env file
	 */
	public function __construct(string $path)
	{
		$this->path = app()->getProjectDir()->resolve($path);
		$this->env  = EnvParser::fromFile($this->path);
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
		return $this->env->getEnv($key, $default);
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
		$editor = $this->env->edit();

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

		$this->env = EnvParser::fromFile($this->path);

		return $this;
	}
}
