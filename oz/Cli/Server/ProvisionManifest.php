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

namespace OZONE\Core\Cli\Server;

use JsonException;
use Throwable;

/**
 * Class ProvisionManifest.
 *
 * What a provision run installed, recorded on the host.
 *
 * It is the difference between a script that can be re-run and one that cannot: a second run is a
 * diff against this file, and nothing the manifest does not claim is ever touched or removed. It is
 * also how an operator finds out what OZone put on a server months later.
 */
final class ProvisionManifest
{
	public const DEFAULT_PATH = '/etc/ozone/provision.json';

	/** @var array<string, array{at: int, version: string, commands: list<string>}> */
	private array $steps = [];

	private function __construct(
		public readonly string $path,
		public readonly array $loaded = [],
	) {
		/** @var array<string, array{at: int, version: string, commands: list<string>}> $steps */
		$steps       = \is_array($loaded['steps'] ?? null) ? $loaded['steps'] : [];
		$this->steps = $steps;
	}

	/**
	 * Reads the manifest of a host, empty when there is none.
	 */
	public static function load(string $path = self::DEFAULT_PATH): self
	{
		$data = [];

		try {
			if (\is_file($path) && \is_readable($path)) {
				$decoded = \json_decode((string) \file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
				$data    = \is_array($decoded) ? $decoded : [];
			}
		} catch (JsonException|Throwable) {
			// A manifest we cannot read is treated as absent: a run then re-checks the host itself
			// rather than refusing to work.
			$data = [];
		}

		return new self($path, $data);
	}

	/**
	 * Whether a step was recorded as done by an earlier run.
	 */
	public function has(string $step): bool
	{
		return isset($this->steps[$step]);
	}

	/**
	 * The step names this manifest claims.
	 *
	 * @return list<string>
	 */
	public function steps(): array
	{
		return \array_keys($this->steps);
	}

	/**
	 * When a step was recorded, or null.
	 */
	public function at(string $step): ?int
	{
		return $this->steps[$step]['at'] ?? null;
	}

	/**
	 * Records a step as done.
	 *
	 * @param list<string> $commands the commands that were run
	 */
	public function record(ProvisionStep $step, array $commands): self
	{
		$this->steps[$step->name] = [
			'at'       => \time(),
			'version'  => OZ_OZONE_VERSION,
			'commands' => $commands,
		];

		return $this;
	}

	/**
	 * Writes the manifest, creating its directory.
	 *
	 * @return bool false when the file could not be written (a run without root, typically)
	 */
	public function save(): bool
	{
		$dir = \dirname($this->path);

		if (!\is_dir($dir) && !@\mkdir($dir, 0o755, true) && !\is_dir($dir)) {
			return false;
		}

		try {
			$json = \json_encode([
				'ozone'   => OZ_OZONE_VERSION,
				'updated' => \time(),
				'steps'   => $this->steps,
			], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
		} catch (JsonException) {
			return false;
		}

		return false !== @\file_put_contents($this->path, $json . \PHP_EOL);
	}
}
