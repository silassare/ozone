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

namespace OZONE\Core\Cli\Deploy;

use OZONE\Core\FS\FilesManager;

/**
 * Class DeployPlan.
 *
 * The files `oz deploy init` would write, so they can be listed before anything is touched.
 *
 * Writing never overwrites without being told to: these files are edited by hand after generation
 * (a domain, a certificate path, a worker count), and silently replacing them would throw that away.
 */
final class DeployPlan
{
	/** @var list<DeployFile> */
	private array $files = [];

	public function add(DeployFile $file): self
	{
		$this->files[] = $file;

		return $this;
	}

	/**
	 * @return list<DeployFile>
	 */
	public function files(): array
	{
		return $this->files;
	}

	public function isEmpty(): bool
	{
		return empty($this->files);
	}

	/**
	 * The file at a path, or null.
	 */
	public function get(string $path): ?DeployFile
	{
		foreach ($this->files as $file) {
			if ($file->path === $path) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Writes the plan.
	 *
	 * @param FilesManager $to    the project directory
	 * @param bool         $force whether to replace a file that is already there
	 *
	 * @return array{written: list<string>, skipped: list<string>}
	 */
	public function write(FilesManager $to, bool $force = false): array
	{
		$written = [];
		$skipped = [];

		foreach ($this->files as $file) {
			$path = $to->resolve($file->path);

			if (!$force && \file_exists($path)) {
				$skipped[] = $file->path;

				continue;
			}

			$dir = \dirname($path);

			if (!\is_dir($dir)) {
				\mkdir($dir, 0o775, true);
			}

			\file_put_contents($path, $file->content);

			if ($file->executable) {
				\chmod($path, 0o755);
			}

			$written[] = $file->path;
		}

		return ['written' => $written, 'skipped' => $skipped];
	}
}
