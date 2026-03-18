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

use Override;
use PHPUtils\Store\Store;

/**
 * Class Settings.
 *
 * A custom store class for settings group that applies the merge strategy when merging data.
 *
 * @extends Store<array>
 *
 * @internal
 */
final class SettingsGroup extends Store
{
	/**
	 * SettingsGroup constructor.
	 */
	public function __construct(array $data)
	{
		parent::__construct($data);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function merge(iterable $data): static
	{
		$bundle = Settings::applyMergeStrategy($this->toArray(), $data);

		return parent::setData($bundle);
	}
}
