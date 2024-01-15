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

use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMEntity;
use OZONE\Core\Db\OZFile as OZFileReal;
use OZONE\Core\Db\OZFilesController;
use OZONE\Core\Db\OZFilesCrud;
use OZONE\Core\Db\OZFilesQuery;
use OZONE\Core\Db\OZFilesResults;
use OZONE\Core\Db\OZUser;
use OZONE\Core\FS\Enums\FileKind;

/**
 * Class OZFile.
 *
 * @property null|string $id          Getter for column `oz_files`.`id`.
 * @property string      $key         Getter for column `oz_files`.`key`.
 * @property string      $ref         Getter for column `oz_files`.`ref`.
 * @property string      $storage     Getter for column `oz_files`.`storage`.
 * @property int         $size        Getter for column `oz_files`.`size`.
 * @property FileKind    $kind        Getter for column `oz_files`.`kind`.
 * @property string      $mime        Getter for column `oz_files`.`mime`.
 * @property string      $extension   Getter for column `oz_files`.`extension`.
 * @property string      $name        Getter for column `oz_files`.`name`.
 * @property string      $real_name   Getter for column `oz_files`.`real_name`.
 * @property null|string $for_id      Getter for column `oz_files`.`for_id`.
 * @property null|string $for_type    Getter for column `oz_files`.`for_type`.
 * @property string      $for_label   Getter for column `oz_files`.`for_label`.
 * @property array       $data        Getter for column `oz_files`.`data`.
 * @property bool        $is_valid    Getter for column `oz_files`.`is_valid`.
 * @property string      $created_at  Getter for column `oz_files`.`created_at`.
 * @property string      $updated_at  Getter for column `oz_files`.`updated_at`.
 * @property bool        $deleted     Getter for column `oz_files`.`deleted`.
 * @property null|string $deleted_at  Getter for column `oz_files`.`deleted_at`.
 * @property null|string $uploaded_by Getter for column `oz_files`.`uploaded_by`.
 * @property null|string $clone_id    Getter for column `oz_files`.`clone_id`.
 * @property null|string $source_id   Getter for column `oz_files`.`source_id`.
 */
abstract class OZFile extends ORMEntity
{
	public const TABLE_NAME      = 'oz_files';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_ID          = 'file_id';
	public const COL_KEY         = 'file_key';
	public const COL_REF         = 'file_ref';
	public const COL_STORAGE     = 'file_storage';
	public const COL_SIZE        = 'file_size';
	public const COL_KIND        = 'file_kind';
	public const COL_MIME        = 'file_mime';
	public const COL_EXTENSION   = 'file_extension';
	public const COL_NAME        = 'file_name';
	public const COL_REAL_NAME   = 'file_real_name';
	public const COL_FOR_ID      = 'file_for_id';
	public const COL_FOR_TYPE    = 'file_for_type';
	public const COL_FOR_LABEL   = 'file_for_label';
	public const COL_DATA        = 'file_data';
	public const COL_IS_VALID    = 'file_is_valid';
	public const COL_CREATED_AT  = 'file_created_at';
	public const COL_UPDATED_AT  = 'file_updated_at';
	public const COL_DELETED     = 'file_deleted';
	public const COL_DELETED_AT  = 'file_deleted_at';
	public const COL_UPLOADED_BY = 'file_uploaded_by';
	public const COL_CLONE_ID    = 'file_clone_id';
	public const COL_SOURCE_ID   = 'file_source_id';

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
		return new OZFileReal($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZFilesCrud
	 */
	public static function crud(): OZFilesCrud
	{
		return OZFilesCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZFilesController
	 */
	public static function ctrl(): OZFilesController
	{
		return OZFilesController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZFilesQuery
	 */
	public static function qb(): OZFilesQuery
	{
		return OZFilesQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return OZFilesResults
	 */
	public static function results(QBSelect $query): OZFilesResults
	{
		return OZFilesResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): Table
	{
		return ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
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
	 * Getter for column `oz_files`.`kind`.
	 *
	 * @return FileKind
	 */
	public function getKind(): FileKind
	{
		return $this->kind;
	}

	/**
	 * Setter for column `oz_files`.`kind`.
	 *
	 * @param \OZONE\Core\FS\Enums\FileKind|string $kind
	 *
	 * @return static
	 */
	public function setKind(FileKind|string $kind): static
	{
		$this->kind = $kind;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`mime`.
	 *
	 * @return string
	 */
	public function getMime(): string
	{
		return $this->mime;
	}

	/**
	 * Setter for column `oz_files`.`mime`.
	 *
	 * @param string $mime
	 *
	 * @return static
	 */
	public function setMime(string $mime): static
	{
		$this->mime = $mime;

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
	 * Getter for column `oz_files`.`real_name`.
	 *
	 * @return string
	 */
	public function getRealName(): string
	{
		return $this->real_name;
	}

	/**
	 * Setter for column `oz_files`.`real_name`.
	 *
	 * @param string $real_name
	 *
	 * @return static
	 */
	public function setRealName(string $real_name): static
	{
		$this->real_name = $real_name;

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
	 * Getter for column `oz_files`.`deleted`.
	 *
	 * @return bool
	 */
	public function isDeleted(): bool
	{
		return $this->deleted;
	}

	/**
	 * Setter for column `oz_files`.`deleted`.
	 *
	 * @param bool $deleted
	 *
	 * @return static
	 */
	public function setDeleted(bool $deleted): static
	{
		$this->deleted = $deleted;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`deleted_at`.
	 *
	 * @return null|string
	 */
	public function getDeletedAT(): null|string
	{
		return $this->deleted_at;
	}

	/**
	 * Setter for column `oz_files`.`deleted_at`.
	 *
	 * @param null|int|string $deleted_at
	 *
	 * @return static
	 */
	public function setDeletedAT(null|int|string $deleted_at): static
	{
		$this->deleted_at = $deleted_at;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`uploaded_by`.
	 *
	 * @return null|string
	 */
	public function getUploadedBY(): null|string
	{
		return $this->uploaded_by;
	}

	/**
	 * Setter for column `oz_files`.`uploaded_by`.
	 *
	 * @param null|int|string $uploaded_by
	 *
	 * @return static
	 */
	public function setUploadedBY(null|int|string $uploaded_by): static
	{
		$this->uploaded_by = $uploaded_by;

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
	 * ManyToOne relation between `oz_files` and `oz_users`.
	 *
	 * @return ?\OZONE\Core\Db\OZUser
	 *
	 * @throws GoblException
	 */
	public function getUploader(): ?OZUser
	{
		return OZUser::ctrl()->getRelative(
			$this,
			static::table()->getRelation('uploader')
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
	 * @throws GoblException
	 */
	public function getClones(array $filters =  [
	], ?int $max = null, int $offset = 0, array $order_by =  [
	], ?int &$total = -1): array
	{
		return OZFileReal::ctrl()->getAllRelatives(
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
	 * @throws GoblException
	 */
	public function getClonedFrom(): ?OZFileReal
	{
		return OZFileReal::ctrl()->getRelative(
			$this,
			static::table()->getRelation('cloned_from')
		);
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_files`.
	 *
	 * @return ?\OZONE\Core\Db\OZFile
	 *
	 * @throws GoblException
	 */
	public function getSource(): ?OZFileReal
	{
		return OZFileReal::ctrl()->getRelative(
			$this,
			static::table()->getRelation('source')
		);
	}
}
