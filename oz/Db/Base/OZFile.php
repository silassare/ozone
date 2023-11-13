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

namespace OZONE\Core\Db\Base;

/**
 * Class OZFile.
 *
 * @property null|string                   $id         Getter for column `oz_files`.`id`.
 * @property null|string                   $owner_id   Getter for column `oz_files`.`owner_id`.
 * @property string                        $key        Getter for column `oz_files`.`key`.
 * @property string                        $ref        Getter for column `oz_files`.`ref`.
 * @property string                        $storage    Getter for column `oz_files`.`storage`.
 * @property null|string                   $clone_id   Getter for column `oz_files`.`clone_id`.
 * @property null|string                   $source_id  Getter for column `oz_files`.`source_id`.
 * @property int                           $size       Getter for column `oz_files`.`size`.
 * @property \OZONE\Core\FS\Enums\FileType $type       Getter for column `oz_files`.`type`.
 * @property string                        $mime_type  Getter for column `oz_files`.`mime_type`.
 * @property string                        $extension  Getter for column `oz_files`.`extension`.
 * @property string                        $name       Getter for column `oz_files`.`name`.
 * @property null|string                   $for_id     Getter for column `oz_files`.`for_id`.
 * @property null|string                   $for_type   Getter for column `oz_files`.`for_type`.
 * @property string                        $for_label  Getter for column `oz_files`.`for_label`.
 * @property array                         $data       Getter for column `oz_files`.`data`.
 * @property string                        $created_at Getter for column `oz_files`.`created_at`.
 * @property string                        $updated_at Getter for column `oz_files`.`updated_at`.
 * @property bool                          $is_valid   Getter for column `oz_files`.`is_valid`.
 */
abstract class OZFile extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_files';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'file_id';
	public const COL_OWNER_ID    = 'file_owner_id';
	public const COL_KEY         = 'file_key';
	public const COL_REF         = 'file_ref';
	public const COL_STORAGE     = 'file_storage';
	public const COL_CLONE_ID    = 'file_clone_id';
	public const COL_SOURCE_ID   = 'file_source_id';
	public const COL_SIZE        = 'file_size';
	public const COL_TYPE        = 'file_type';
	public const COL_MIME_TYPE   = 'file_mime_type';
	public const COL_EXTENSION   = 'file_extension';
	public const COL_NAME        = 'file_name';
	public const COL_FOR_ID      = 'file_for_id';
	public const COL_FOR_TYPE    = 'file_for_type';
	public const COL_FOR_LABEL   = 'file_for_label';
	public const COL_DATA        = 'file_data';
	public const COL_CREATED_AT  = 'file_created_at';
	public const COL_UPDATED_AT  = 'file_updated_at';
	public const COL_IS_VALID    = 'file_is_valid';

	/**
	 * OZFile constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct(bool $is_new = true, bool $strict = true)
	{
		parent::__construct(
			self::TABLE_NAMESPACE,
			self::TABLE_NAME,
			$is_new,
			$strict
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\Core\Db\OZFile($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZFilesCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZFilesCrud
	{
		return \OZONE\Core\Db\OZFilesCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZFilesController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZFilesController
	{
		return \OZONE\Core\Db\OZFilesController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZFilesQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZFilesQuery
	{
		return \OZONE\Core\Db\OZFilesQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZFilesResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZFilesResults
	{
		return \OZONE\Core\Db\OZFilesResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_files`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): null|string
	{
		return $this->id;
	}

	/**
	 * Setter for column `oz_files`.`id`.
	 *
	 * @param null|int|string $id
	 *
	 * @return static
	 */
	public function setID(null|int|string $id): static
	{
		$this->id = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`owner_id`.
	 *
	 * @return null|string
	 */
	public function getOwnerID(): null|string
	{
		return $this->owner_id;
	}

	/**
	 * Setter for column `oz_files`.`owner_id`.
	 *
	 * @param null|int|string $owner_id
	 *
	 * @return static
	 */
	public function setOwnerID(null|int|string $owner_id): static
	{
		$this->owner_id = $owner_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`key`.
	 *
	 * @return string
	 */
	public function getKey(): string
	{
		return $this->key;
	}

	/**
	 * Setter for column `oz_files`.`key`.
	 *
	 * @param string $key
	 *
	 * @return static
	 */
	public function setKey(string $key): static
	{
		$this->key = $key;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`ref`.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * Setter for column `oz_files`.`ref`.
	 *
	 * @param string $ref
	 *
	 * @return static
	 */
	public function setRef(string $ref): static
	{
		$this->ref = $ref;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`storage`.
	 *
	 * @return string
	 */
	public function getStorage(): string
	{
		return $this->storage;
	}

	/**
	 * Setter for column `oz_files`.`storage`.
	 *
	 * @param string $storage
	 *
	 * @return static
	 */
	public function setStorage(string $storage): static
	{
		$this->storage = $storage;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`clone_id`.
	 *
	 * @return null|string
	 */
	public function getCloneID(): null|string
	{
		return $this->clone_id;
	}

	/**
	 * Setter for column `oz_files`.`clone_id`.
	 *
	 * @param null|int|string $clone_id
	 *
	 * @return static
	 */
	public function setCloneID(null|int|string $clone_id): static
	{
		$this->clone_id = $clone_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`source_id`.
	 *
	 * @return null|string
	 */
	public function getSourceID(): null|string
	{
		return $this->source_id;
	}

	/**
	 * Setter for column `oz_files`.`source_id`.
	 *
	 * @param null|int|string $source_id
	 *
	 * @return static
	 */
	public function setSourceID(null|int|string $source_id): static
	{
		$this->source_id = $source_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`size`.
	 *
	 * @return int
	 */
	public function getSize(): int
	{
		return $this->size;
	}

	/**
	 * Setter for column `oz_files`.`size`.
	 *
	 * @param int $size
	 *
	 * @return static
	 */
	public function setSize(int $size): static
	{
		$this->size = $size;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`type`.
	 *
	 * @return \OZONE\Core\FS\Enums\FileType
	 */
	public function getType(): \OZONE\Core\FS\Enums\FileType
	{
		return $this->type;
	}

	/**
	 * Setter for column `oz_files`.`type`.
	 *
	 * @param \OZONE\Core\FS\Enums\FileType|string $type
	 *
	 * @return static
	 */
	public function setType(\OZONE\Core\FS\Enums\FileType|string $type): static
	{
		$this->type = $type;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`mime_type`.
	 *
	 * @return string
	 */
	public function getMimeType(): string
	{
		return $this->mime_type;
	}

	/**
	 * Setter for column `oz_files`.`mime_type`.
	 *
	 * @param string $mime_type
	 *
	 * @return static
	 */
	public function setMimeType(string $mime_type): static
	{
		$this->mime_type = $mime_type;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`extension`.
	 *
	 * @return string
	 */
	public function getExtension(): string
	{
		return $this->extension;
	}

	/**
	 * Setter for column `oz_files`.`extension`.
	 *
	 * @param string $extension
	 *
	 * @return static
	 */
	public function setExtension(string $extension): static
	{
		$this->extension = $extension;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Setter for column `oz_files`.`name`.
	 *
	 * @param string $name
	 *
	 * @return static
	 */
	public function setName(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`for_id`.
	 *
	 * @return null|string
	 */
	public function getForID(): null|string
	{
		return $this->for_id;
	}

	/**
	 * Setter for column `oz_files`.`for_id`.
	 *
	 * @param null|string $for_id
	 *
	 * @return static
	 */
	public function setForID(null|string $for_id): static
	{
		$this->for_id = $for_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`for_type`.
	 *
	 * @return null|string
	 */
	public function getForType(): null|string
	{
		return $this->for_type;
	}

	/**
	 * Setter for column `oz_files`.`for_type`.
	 *
	 * @param null|string $for_type
	 *
	 * @return static
	 */
	public function setForType(null|string $for_type): static
	{
		$this->for_type = $for_type;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`for_label`.
	 *
	 * @return string
	 */
	public function getForLabel(): string
	{
		return $this->for_label;
	}

	/**
	 * Setter for column `oz_files`.`for_label`.
	 *
	 * @param string $for_label
	 *
	 * @return static
	 */
	public function setForLabel(string $for_label): static
	{
		$this->for_label = $for_label;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Setter for column `oz_files`.`data`.
	 *
	 * @param array $data
	 *
	 * @return static
	 */
	public function setData(array $data): static
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_files`.`created_at`.
	 *
	 * @param int|string $created_at
	 *
	 * @return static
	 */
	public function setCreatedAT(int|string $created_at): static
	{
		$this->created_at = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_files`.`updated_at`.
	 *
	 * @param int|string $updated_at
	 *
	 * @return static
	 */
	public function setUpdatedAT(int|string $updated_at): static
	{
		$this->updated_at = $updated_at;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
	}

	/**
	 * Setter for column `oz_files`.`is_valid`.
	 *
	 * @param bool $is_valid
	 *
	 * @return static
	 */
	public function setISValid(bool $is_valid): static
	{
		$this->is_valid = $is_valid;

		return $this;
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_users`.
	 *
	 * @return ?\OZONE\Core\Db\OZUser
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getOwner(): ?\OZONE\Core\Db\OZUser
	{
		return \OZONE\Core\Db\OZUser::ctrl()->getRelative(
			$this,
			static::table()->getRelation('owner')
		);
	}

	/**
	 * OneToMany relation between `oz_files` and `oz_files`.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int $total    total rows without limit
	 *
	 * @return \OZONE\Core\Db\OZFile[]
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getClones(array $filters =  [
	], ?int $max = null, int $offset = 0, array $order_by =  [
	], ?int &$total = -1): array
	{
		return \OZONE\Core\Db\OZFile::ctrl()->getAllRelatives(
			$this,
			static::table()->getRelation('clones'),
			$filters,
			$max,
			$offset,
			$order_by,
			$total
		);
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_files`.
	 *
	 * @return ?\OZONE\Core\Db\OZFile
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getClonedFrom(): ?\OZONE\Core\Db\OZFile
	{
		return \OZONE\Core\Db\OZFile::ctrl()->getRelative(
			$this,
			static::table()->getRelation('cloned_from')
		);
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_files`.
	 *
	 * @return ?\OZONE\Core\Db\OZFile
	 *
	 * @throws \Gobl\Exceptions\GoblException
	 */
	public function getSource(): ?\OZONE\Core\Db\OZFile
	{
		return \OZONE\Core\Db\OZFile::ctrl()->getRelative(
			$this,
			static::table()->getRelation('source')
		);
	}
}
