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

namespace OZONE\Core\App;

use InvalidArgumentException;
use Override;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Loader\ClassLoader;
use OZONE\Core\Scopes\AbstractScope;
use OZONE\Core\Scopes\StateLayout;

/**
 * Class SubScope.
 */
final class SubScope extends AbstractScope
{
	/**
	 * Whether the project namespace reads this scope's directory already: {@see self::addClasses()}.
	 */
	private bool $classes_added = false;
	/**
	 * SubScope constructor.
	 *
	 * Registers no settings source, unlike the parent's constructor: a project's scope is built for
	 * the scope a process serves and just as well for another one it only names (`oz project link`,
	 * `oz deploy init`, a call into another scope), and the stateful settings of a scope this
	 * process does not serve must not join its settings. `OZone::bootstrap()` registers the served
	 * scope's.
	 *
	 * @throws InvalidArgumentException when the name is not one a scope may take
	 */
	public function __construct(protected string $name)
	{
		// The name is the scope's directory under `data/`, so a reserved one would have the scope
		// read and write the application's state, or the directory every plugin's state lives under.
		$fault = StateLayout::scopeNameFault($name);

		if (null !== $fault) {
			throw new InvalidArgumentException(\sprintf('Invalid scope "%s". %s', $name, $fault));
		}
	}

	/**
	 * Makes the project namespace read a scope's own directory too, as the scope's entry point does:
	 * a class only that scope uses -- a route provider its settings enable -- lives in
	 * `scopes/{name}/`.
	 *
	 * For the rest of the process: PHP cannot unload a class, so a directory is never taken away
	 * once added, and of two scopes declaring the same class the first to load it wins
	 * (`oz doctor check` warns of such a class).
	 *
	 * @internal
	 *
	 * @param string $dir the scope's directory, `scopes/{name}`
	 */
	public static function addClassesOf(string $dir): void
	{
		ClassLoader::addNamespace('\\' . (string) Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE'), $dir);
	}

	/**
	 * Makes the project namespace read this scope's own directory too: {@see self::addClassesOf()}.
	 *
	 * @internal
	 */
	public function addClasses(): void
	{
		if (!$this->classes_added) {
			self::addClassesOf($this->getSourcesDir()->getRoot());

			$this->classes_added = true;
		}
	}

	#[Override]
	public function getName(): string
	{
		return $this->name;
	}

	#[Override]
	public function getSourcesDir(): FilesManager
	{
		return app()->getProjectDir()->cd('scopes' . DS . $this->name, true);
	}

	#[Override]
	public function getDocumentRootDir(): FilesManager
	{
		return app()->getProjectDir()->cd('public' . DS . $this->name, true);
	}
}
