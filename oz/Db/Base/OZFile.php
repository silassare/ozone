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

namespace OZONE\OZ\Db\Base;

/**
 * Class OZFile.
 *
 * @property null|string $id         Getter for column `oz_files`.`id`.
 * @property null|string $user_id    Getter for column `oz_files`.`user_id`.
 * @property string      $key        Getter for column `oz_files`.`key`.
 * @property string      $ref        Getter for column `oz_files`.`ref`.
 * @property string      $driver     Getter for column `oz_files`.`driver`.
 * @property null|string $clone_id   Getter for column `oz_files`.`clone_id`.
 * @property null|string $source_id  Getter for column `oz_files`.`source_id`.
 * @property int         $size       Getter for column `oz_files`.`size`.
 * @property string      $mime_type  Getter for column `oz_files`.`mime_type`.
 * @property string      $extension  Getter for column `oz_files`.`extension`.
 * @property string      $name       Getter for column `oz_files`.`name`.
 * @property string      $label      Getter for column `oz_files`.`label`.
 * @property array       $data       Getter for column `oz_files`.`data`.
 * @property string      $created_at Getter for column `oz_files`.`created_at`.
 * @property string      $updated_at Getter for column `oz_files`.`updated_at`.
 * @property bool        $valid      Getter for column `oz_files`.`valid`.
 */
abstract class OZFile extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_files';
	public const TABLE_NAMESPACE = 'OZONE\\OZ\\Db';
	public const COL_ID          = 'file_id';
	public const COL_USER_ID     = 'file_user_id';
	public const COL_KEY         = 'file_key';
	public const COL_REF         = 'file_ref';
	public const COL_DRIVER      = 'file_driver';
	public const COL_CLONE_ID    = 'file_clone_id';
	public const COL_SOURCE_ID   = 'file_source_id';
	public const COL_SIZE        = 'file_size';
	public const COL_MIME_TYPE   = 'file_mime_type';
	public const COL_EXTENSION   = 'file_extension';
	public const COL_NAME        = 'file_name';
	public const COL_LABEL       = 'file_label';
	public const COL_DATA        = 'file_data';
	public const COL_CREATED_AT  = 'file_created_at';
	public const COL_UPDATED_AT  = 'file_updated_at';
	public const COL_VALID       = 'file_valid';

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
	public static function createInstance(bool $is_new = true, bool $strict = true): static
	{
		return new \OZONE\OZ\Db\OZFile($is_new, $strict);
	}

	/**
	 * Getter for column `oz_files`.`id`.
	 *
	 * @return null|string
	 */
	public function getID(): string|null
	{
		return $this->{self::COL_ID};
	}

	/**
	 * Setter for column `oz_files`.`id`.
	 *
	 * @param null|int|string $id
	 *
	 * @return static
	 */
	public function setID(string|int|null $id): static
	{
		$this->{self::COL_ID} = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`user_id`.
	 *
	 * @return null|string
	 */
	public function getUserID(): string|null
	{
		return $this->{self::COL_USER_ID};
	}

	/**
	 * Setter for column `oz_files`.`user_id`.
	 *
	 * @param null|int|string $user_id
	 *
	 * @return static
	 */
	public function setUserID(string|int|null $user_id): static
	{
		$this->{self::COL_USER_ID} = $user_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`key`.
	 *
	 * @return string
	 */
	public function getKey(): string
	{
		return $this->{self::COL_KEY};
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
		$this->{self::COL_KEY} = $key;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`ref`.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->{self::COL_REF};
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
		$this->{self::COL_REF} = $ref;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`driver`.
	 *
	 * @return string
	 */
	public function getDriver(): string
	{
		return $this->{self::COL_DRIVER};
	}

	/**
	 * Setter for column `oz_files`.`driver`.
	 *
	 * @param string $driver
	 *
	 * @return static
	 */
	public function setDriver(string $driver): static
	{
		$this->{self::COL_DRIVER} = $driver;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`clone_id`.
	 *
	 * @return null|string
	 */
	public function getCloneID(): string|null
	{
		return $this->{self::COL_CLONE_ID};
	}

	/**
	 * Setter for column `oz_files`.`clone_id`.
	 *
	 * @param null|int|string $clone_id
	 *
	 * @return static
	 */
	public function setCloneID(string|int|null $clone_id): static
	{
		$this->{self::COL_CLONE_ID} = $clone_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`source_id`.
	 *
	 * @return null|string
	 */
	public function getSourceID(): string|null
	{
		return $this->{self::COL_SOURCE_ID};
	}

	/**
	 * Setter for column `oz_files`.`source_id`.
	 *
	 * @param null|int|string $source_id
	 *
	 * @return static
	 */
	public function setSourceID(string|int|null $source_id): static
	{
		$this->{self::COL_SOURCE_ID} = $source_id;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`size`.
	 *
	 * @return int
	 */
	public function getSize(): int
	{
		return $this->{self::COL_SIZE};
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
		$this->{self::COL_SIZE} = $size;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`mime_type`.
	 *
	 * @return string
	 */
	public function getMimeType(): string
	{
		return $this->{self::COL_MIME_TYPE};
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
		$this->{self::COL_MIME_TYPE} = $mime_type;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`extension`.
	 *
	 * @return string
	 */
	public function getExtension(): string
	{
		return $this->{self::COL_EXTENSION};
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
		$this->{self::COL_EXTENSION} = $extension;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`name`.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->{self::COL_NAME};
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
		$this->{self::COL_NAME} = $name;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`label`.
	 *
	 * @return string
	 */
	public function getLabel(): string
	{
		return $this->{self::COL_LABEL};
	}

	/**
	 * Setter for column `oz_files`.`label`.
	 *
	 * @param string $label
	 *
	 * @return static
	 */
	public function setLabel(string $label): static
	{
		$this->{self::COL_LABEL} = $label;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->{self::COL_DATA};
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
		$this->{self::COL_DATA} = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_files`.`created_at`.
	 *
	 * @param int|string $created_at
	 *
	 * @return static
	 */
	public function setCreatedAT(string|int $created_at): static
	{
		$this->{self::COL_CREATED_AT} = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_files`.`updated_at`.
	 *
	 * @param int|string $updated_at
	 *
	 * @return static
	 */
	public function setUpdatedAT(string|int $updated_at): static
	{
		$this->{self::COL_UPDATED_AT} = $updated_at;

		return $this;
	}

	/**
	 * Getter for column `oz_files`.`valid`.
	 *
	 * @return bool
	 */
	public function getValid(): bool
	{
		return $this->{self::COL_VALID};
	}

	/**
	 * Setter for column `oz_files`.`valid`.
	 *
	 * @param bool $valid
	 *
	 * @return static
	 */
	public function setValid(bool $valid): static
	{
		$this->{self::COL_VALID} = $valid;

		return $this;
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_users`.
	 *
	 * @return ?\OZONE\OZ\Db\OZUser
	 */
	public function getOwner(): ?\OZONE\OZ\Db\OZUser
	{
		$getters        = [\OZONE\OZ\Db\OZUser::COL_ID => $this->getUserID(...)];
		$filters_bundle = $this->buildRelationFilter($getters, []);

		if (null === $filters_bundle) {
			return null;
		}

		return (new \OZONE\OZ\Db\OZUsersController())->getItem($filters_bundle);
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
	 * @return \OZONE\OZ\Db\OZFile[]
	 */
	public function getClones(array $filters = [
	], ?int $max = null, int $offset = 0, array $order_by = [
	], ?int &$total = -1): array
	{
		$getters        = [\OZONE\OZ\Db\OZFile::COL_CLONE_ID => $this->getID(...)];
		$filters_bundle = $this->buildRelationFilter($getters, $filters);

		if (null === $filters_bundle) {
			return [];
		}

		return (new \OZONE\OZ\Db\OZFilesController())->getAllItems($filters_bundle, $max, $offset, $order_by, $total);
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_files`.
	 *
	 * @return ?\OZONE\OZ\Db\OZFile
	 */
	public function getClonedFrom(): ?\OZONE\OZ\Db\OZFile
	{
		$getters        = [\OZONE\OZ\Db\OZFile::COL_ID => $this->getCloneID(...)];
		$filters_bundle = $this->buildRelationFilter($getters, []);

		if (null === $filters_bundle) {
			return null;
		}

		return (new \OZONE\OZ\Db\OZFilesController())->getItem($filters_bundle);
	}

	/**
	 * ManyToOne relation between `oz_files` and `oz_files`.
	 *
	 * @return ?\OZONE\OZ\Db\OZFile
	 */
	public function getSource(): ?\OZONE\OZ\Db\OZFile
	{
		$getters        = [\OZONE\OZ\Db\OZFile::COL_SOURCE_ID => $this->getID(...)];
		$filters_bundle = $this->buildRelationFilter($getters, []);

		if (null === $filters_bundle) {
			return null;
		}

		return (new \OZONE\OZ\Db\OZFilesController())->getItem($filters_bundle);
	}
}
