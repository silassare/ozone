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

use Gobl\ORM\ORMOptions;
use OZONE\Core\App\Keys;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\FS\Scan\FileScan;
use OZONE\Core\Router\Guards;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;

/**
 * Trait FileEntityTrait.
 */
trait FileEntityTrait
{
	/**
	 * {@inheritDoc}
	 *
	 * A new file goes through the virus scan ({@see FileScan}) when it is enabled.
	 */
	public function save(): bool
	{
		$is_new = $this->isNew();

		if ($is_new) {
			$this->setKey(Keys::newFileKey());

			FileScan::beforeInsert($this);
		}

		$mime = $this->getMime();

		if ($mime) {
			$this->setKind(FileKind::fromMime($mime));
		}

		$saved = parent::save();

		if ($is_new) {
			FileScan::afterInsert($this);
		}

		return $saved;
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
		$data = $this->toRow(); // not toArray(): it blanks the storage ref

		unset($data[self::COL_ID]); // we want a new file id

		$data[self::COL_CLONE_ID] = $this->getID();
		$data[self::COL_KEY]      = Keys::newFileKey();

		if (!$this->getSourceID()) { // first level clone
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
		// The relation query of an unsaved file would look for clones of a null id.
		if ($this->isNew()) {
			return false;
		}

		return (bool) \count($this->getClones(ORMOptions::makePaginated(1)) ?? []);
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
			$map[$guard::class] = $guard->toRules();
		}

		$data['guards_rules'] = $map;

		$this->setData($data);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, mixed>
	 */
	public function toArray($hide_sensitive_data = true): array
	{
		$arr = parent::toArray($hide_sensitive_data);

		$arr[self::COL_REF] = '';

		return $arr;
	}
}
