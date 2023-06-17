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

namespace OZONE\Core\FS\Traits;

use OZONE\Core\App\Keys;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Trait FileEntityTrait.
 */
trait FileEntityTrait
{
	/**
	 * Clone the current ozone file.
	 *
	 * !This doesn't work as php object clone feature.
	 *
	 * @return static
	 */
	public function cloneFile(): static
	{
		if (!$this->isSaved()) {
			throw new RuntimeException('You cannot clone unsaved file.');
		}

		$f    = new static();
		$data = $this->toArray(false);

		unset($data[self::COL_ID]); // we want a new file id

		$data[self::COL_CLONE_ID] = $this->getID();
		$data[self::COL_KEY]      = Keys::newFileKey();

		if (!$this->getSourceID()) {// first level clone
			$data[self::COL_SOURCE_ID] = $this->getID();
		}

		return $f->hydrate($data);
	}

	/**
	 * Checks if this file can be safely deleted.
	 *
	 * @return bool
	 */
	public function canDelete(): bool
	{
		return !$this->getCloneID() && !$this->hasClones();
	}

	/**
	 * @return bool
	 */
	public function hasClones(): bool
	{
		return (bool) \count($this->getClones([], 1));
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray($hide_sensitive_data = true): array
	{
		$arr = parent::toArray($hide_sensitive_data);

		$arr[self::COL_KEY] = null;

		return $arr;
	}
}
