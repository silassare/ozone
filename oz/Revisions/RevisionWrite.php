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

namespace OZONE\Core\Revisions;

/**
 * Class RevisionWrite.
 *
 * What a write answers: whether it was applied, and the resource's current revision either way --
 * the one written, or the one a stale write was refused over, which is what its author has to see
 * before trying again ({@see \OZONE\Core\Stores\StoreWrite}, whose rule this is).
 */
final readonly class RevisionWrite
{
	/**
	 * @param bool          $applied  whether the revision was written
	 * @param null|Revision $revision the current revision; null where the key has none (a refused
	 *                                write that expected one)
	 */
	private function __construct(
		public bool $applied,
		public ?Revision $revision,
	) {}

	/**
	 * The revision was written, and is this one.
	 */
	public static function applied(Revision $revision): self
	{
		return new self(true, $revision);
	}

	/**
	 * The write was based on a revision that is no longer the current one: this is the current one.
	 */
	public static function refused(?Revision $current): self
	{
		return new self(false, $current);
	}
}
