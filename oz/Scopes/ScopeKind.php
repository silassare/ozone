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

namespace OZONE\Core\Scopes;

use JsonException;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;

/**
 * Whether a scope serves the API or the web: which router answers its requests, and how an error
 * is answered to them.
 *
 * The process serving a scope learns it from its entry point, which defines `OZ_OZONE_IS_WEB_CONTEXT`
 * for a web scope ({@see self::ofProcess()}). Anything that needs it for a scope it does not serve
 * -- the build of each scope, a call into another scope -- reads the record `oz scopes add` writes
 * in the scope's directory ({@see self::RECORD_FILE}), and never the entry point's source.
 */
enum ScopeKind: string
{
	case API = 'api';

	case WEB = 'web';

	/**
	 * The record of a scope's kind, in its `scopes/{name}/` directory: version-controlled with the
	 * scope, and denied to the web like the rest of that directory.
	 */
	public const string RECORD_FILE = 'scope.json';

	/**
	 * The kind of the scope this process serves.
	 */
	public static function ofProcess(): self
	{
		return \defined('OZ_OZONE_IS_WEB_CONTEXT') ? self::WEB : self::API;
	}

	/**
	 * The kind of a scope of the running project.
	 *
	 * The scope this process serves answers as the process does. The root scope has no entry point
	 * of its own and is served as the API, as the console that runs under it is.
	 *
	 * @throws RuntimeException when a sub-scope has no readable record
	 */
	public static function of(ScopeInterface $scope): self
	{
		$app = app();

		if ($scope === $app->getScope(\OZ_SCOPE_NAME)) {
			return self::ofProcess();
		}

		if ($scope instanceof AppInterface) {
			return self::API;
		}

		return self::read($app->getProjectDir()->getRoot(), $scope->getName());
	}

	/**
	 * The recorded kind of a scope of a project.
	 *
	 * @param string $project_dir the project directory
	 * @param string $scope       the scope name
	 *
	 * @throws RuntimeException when the scope has no readable record
	 */
	public static function read(string $project_dir, string $scope): self
	{
		$kind = self::find($project_dir, $scope);

		if (null === $kind) {
			throw new RuntimeException(\sprintf(
				'The scope "%s" has no readable record of whether it serves the API or the web (%s):'
					. ' run "oz project link" to write it.',
				$scope,
				self::recordPath($project_dir, $scope)
			));
		}

		return $kind;
	}

	/**
	 * The recorded kind of a scope of a project, or null when it has no readable record.
	 *
	 * @param string $project_dir the project directory
	 * @param string $scope       the scope name
	 */
	public static function find(string $project_dir, string $scope): ?self
	{
		$file = self::recordPath($project_dir, $scope);

		if (!\is_file($file)) {
			return null;
		}

		try {
			$record = \json_decode((string) \file_get_contents($file), true, 4, \JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		$kind = \is_array($record) ? ($record['kind'] ?? null) : null;

		return \is_string($kind) ? self::tryFrom($kind) : null;
	}

	/**
	 * The content of a record of this kind.
	 */
	public function record(): string
	{
		return \json_encode(['kind' => $this->value], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT) . \PHP_EOL;
	}

	/**
	 * Writes the record of a scope's kind.
	 *
	 * @param string $project_dir the project directory
	 * @param string $scope       the scope name
	 *
	 * @throws RuntimeException when it cannot be written
	 */
	public function write(string $project_dir, string $scope): void
	{
		$file = self::recordPath($project_dir, $scope);

		if (false === @\file_put_contents($file, $this->record())) {
			throw new RuntimeException(\sprintf('Unable to write the record of the scope "%s" (%s).', $scope, $file));
		}
	}

	/**
	 * The kind a scope's entry point declares, for a scope made before its kind was recorded: the
	 * entry point `oz scopes add` generates defines `OZ_OZONE_IS_WEB_CONTEXT` for a web scope only.
	 * Null when the scope has no entry point to read.
	 *
	 * Read once, to write the record ({@see self::write()}); nothing else reads the entry point.
	 *
	 * @param string $project_dir the project directory
	 * @param string $scope       the scope name
	 */
	public static function fromEntryPoint(string $project_dir, string $scope): ?self
	{
		$index = \rtrim($project_dir, '/\\') . DS . 'public' . DS . $scope . DS . 'index.php';

		if (!\is_file($index)) {
			return null;
		}

		$source = \file_get_contents($index);

		if (false === $source) {
			return null;
		}

		return \str_contains($source, 'OZ_OZONE_IS_WEB_CONTEXT') ? self::WEB : self::API;
	}

	/**
	 * Where the record of a scope's kind is.
	 *
	 * @param string $project_dir the project directory
	 * @param string $scope       the scope name
	 */
	public static function recordPath(string $project_dir, string $scope): string
	{
		return \rtrim($project_dir, '/\\') . DS . 'scopes' . DS . $scope . DS . self::RECORD_FILE;
	}
}
