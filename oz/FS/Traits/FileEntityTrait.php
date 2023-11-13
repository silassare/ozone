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
use OZONE\Core\FS\Enums\FileType;
use OZONE\Core\Router\Guards;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;

/**
 * Trait FileEntityTrait.
 */
trait FileEntityTrait
{
	/**
	 * {@inheritDoc}
	 */
	public function save(): bool
	{
		if ($this->isNew()) {
			$this->setKey(Keys::newFileKey());
		}

		$mime = $this->getMimeType();

		if ($mime) {
			$this->setType(FileType::fromMime($mime));
		}

		return parent::save();
	}

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
	 * Checks if this file has clones.
	 *
	 * @return bool
	 */
	public function hasClones(): bool
	{
		return (bool) \count($this->getClones([], 1));
	}

	/**
	 * Gets the file access guards.
	 *
	 * @return RouteGuardInterface[]
	 */
	public function getAccessGuards(): array
	{
		$data = $this->getData();

		if (isset($data['guards_rules']) && \is_array($data['guards_rules'])) {
			return Guards::resolve($data['guards_rules']);
		}

		return [];
	}

	/**
	 * Sets the file access guards.
	 *
	 * @param RouteGuardInterface[] $guards
	 *
	 * @return $this
	 */
	public function setAccessGuards(array $guards): static
	{
		$data = $this->getData();

		$map = [];

		foreach ($guards as $guard) {
			$map[$guard::class] = $guard->getRules();
		}

		$data['guards_rules'] = $map;

		$this->setData($data);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray($hide_sensitive_data = true): array
	{
		$arr = parent::toArray($hide_sensitive_data);

		$arr[self::COL_KEY] = '';
		$arr[self::COL_REF] = '';

		return $arr;
	}
}
