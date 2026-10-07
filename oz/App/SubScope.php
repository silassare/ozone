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
use OZONE\Core\Scopes\AbstractScope;
use OZONE\Core\Scopes\StateLayout;

/**
 * Class SubScope.
 */
final class SubScope extends AbstractScope
{
	/**
	 * SubScope constructor.
	 *
	 * @throws InvalidArgumentException when the name is not one a scope may take
	 */
	public function __construct(protected string $name)
	{
		// Before the parent registers a settings source from it: the name is the scope's directory
		// under `data/`, so a reserved one would have the scope read and write the application's
		// state, or the directory every plugin's state lives under.
		$fault = StateLayout::scopeNameFault($name);

		if (null !== $fault) {
			throw new InvalidArgumentException(\sprintf('Invalid scope "%s". %s', $name, $fault));
		}

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getSourcesDir(): FilesManager
	{
		return app()->getProjectDir()->cd('scopes' . DS . $this->name, true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getDocumentRootDir(): FilesManager
	{
		return app()->getProjectDir()->cd('public' . DS . $this->name, true);
	}
}
