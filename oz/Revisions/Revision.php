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

use OZONE\Core\Db\OZRevision;

/**
 * Class Revision.
 *
 * One revision of a resource: what it was, who wrote it and when. Null content is a revision too
 * -- "nothing is stored for this key" -- which is how a reset stays in the history.
 */
final readonly class Revision
{
	/**
	 * @param array<array-key, mixed>      $meta     what the owner of the namespace recorded
	 * @param null|array<array-key, mixed> $content
	 */
	public function __construct(
		public string $namespace,
		public string $key,
		public int $number,
		public ?array $content,
		public ?string $digest,
		public array $meta,
		public ?string $note,
		public ?string $authorType,
		public ?string $authorId,
		public int $createdAt,
	) {}

	/**
	 * The revision a row holds.
	 */
	public static function of(OZRevision $row): self
	{
		$content  = $row->getContent();
		$meta     = $row->getMeta()->getData();

		return new self(
			$row->getNamespace(),
			$row->getKey(),
			$row->getNumber(),
			null === $content ? null : (array) $content->getData(),
			$row->getDigest(),
			\is_array($meta) ? $meta : [],
			$row->getNote(),
			$row->getAuthorType(),
			$row->getAuthorID(),
			(int) $row->getCreatedAT(),
		);
	}

	/**
	 * Whether this revision removed the resource: the key holds nothing from here on.
	 */
	public function isRemoval(): bool
	{
		return null === $this->content;
	}
}
