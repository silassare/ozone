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

namespace OZONE\Core\Stores;

/**
 * Class StoreWrite.
 *
 * What a conditional write answers: whether it was applied, and the entry as the store holds it now.
 *
 * The entry comes back either way, and that is the point of the type. A caller whose write was
 * refused needs what is there instead -- its value and its version -- to decide what to do, and
 * asking again for it would be a second read of something that may have moved once more. One round
 * trip answers "applied, and here is its new version" or "refused, and here is what you were
 * missing", which is what a client sending writes it made offline has to hear.
 */
final readonly class StoreWrite
{
	/**
	 * StoreWrite constructor.
	 *
	 * @param bool            $applied whether the write happened
	 * @param null|StoreEntry $entry   the entry as the store holds it now; null where the key holds
	 *                                 nothing (a refused create, or a delete that was applied)
	 */
	private function __construct(
		public bool $applied,
		public ?StoreEntry $entry,
	) {}

	/**
	 * The write happened; the entry is what was written, with the version it took.
	 */
	public static function applied(?StoreEntry $entry): self
	{
		return new self(true, $entry);
	}

	/**
	 * The write did not happen: the entry is what the store holds instead, or null where the key
	 * holds nothing (which refuses a write that expected a version).
	 */
	public static function refused(?StoreEntry $entry): self
	{
		return new self(false, $entry);
	}

	/**
	 * The version the entry now has, or null where the key holds nothing.
	 */
	public function version(): ?int
	{
		return $this->entry?->version;
	}
}
