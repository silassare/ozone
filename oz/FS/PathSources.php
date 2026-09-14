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

namespace OZONE\Core\FS;

/**
 * Class PathSources.
 */
class PathSources
{
	/**
	 * @var array{oz:array<string, bool>,project:array<string, bool>,plugins:array<string, bool>}
	 */
	protected array $sources = [
		'oz'      => [],
		'project' => [],
		'plugins' => [],
	];

	/**
	 * The sources written at runtime (stateful settings, under `data/`), as {@see getAllSources()}
	 * lists them.
	 *
	 * @var array<string, true>
	 */
	protected array $stateful = [];

	/**
	 * Add a path source.
	 *
	 * @param string $path
	 * @param bool   $stateful whether the source is written at runtime, rather than shipped with the code
	 */
	public function add(string $path, bool $stateful = false): static
	{
		$path = FS::fromRoot()->resolve($path);

		if ($stateful) {
			$this->stateful[$path] = true;
		}

		if (\str_starts_with($path, OZ_OZONE_DIR)) {
			$this->sources['oz'][$path] = true;
		} elseif (
			// is in project dir/sub-dir and not in vendor dir/sub-dir
			\str_starts_with($path, OZ_PROJECT_DIR)
			&& !\str_starts_with($path, OZ_PROJECT_DIR . 'vendor')
		) {
			$this->sources['project'][$path] = true;
		} else {
			// is in vendor dir/sub-dir or elsewhere for plugins
			// installed via composer with repository type path
			// when developing/testing plugins
			$this->sources['plugins'][$path] = true;
		}

		return $this;
	}

	/**
	 * Returns internal path sources.
	 *
	 * @return string[]
	 */
	public function getInternalSources(): array
	{
		return \array_keys($this->sources['oz']);
	}

	/**
	 * Returns project path sources.
	 *
	 * @return string[]
	 */
	public function getProjectSources(): array
	{
		return \array_keys($this->sources['project']);
	}

	/**
	 * Returns plugins path sources.
	 *
	 * @return string[]
	 */
	public function getPluginsSources(): array
	{
		return \array_keys($this->sources['plugins']);
	}

	/**
	 * Whether a source, as {@see getAllSources()} lists it, is written at runtime.
	 */
	public function isStateful(string $path): bool
	{
		return isset($this->stateful[$path]);
	}

	/**
	 * Returns all path sources.
	 *
	 * @return string[]
	 */
	public function getAllSources(): array
	{
		return [
			...$this->getInternalSources(),
			...$this->getPluginsSources(),
			...$this->getProjectSources(),
		];
	}
}
