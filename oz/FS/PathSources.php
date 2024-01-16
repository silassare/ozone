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
	 * Add a path source.
	 *
	 * @param string $path
	 *
	 * @return $this
	 */
	public function add(string $path): self
	{
		$path = FS::fromRoot()->resolve($path);

		if (\str_starts_with($path, OZ_OZONE_DIR)) {
			$this->sources['oz'][$path] = true;
		} elseif (\str_starts_with($path, OZ_PROJECT_DIR . 'vendor')) {
			$this->sources['plugins'][$path] = true;
		} else {
			$this->sources['project'][$path] = true;
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
