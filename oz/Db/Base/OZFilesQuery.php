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
 * Class OZFilesQuery.
 *
 * @extends \Gobl\ORM\ORMTableQuery<\OZONE\Core\Db\OZFile>
 *
 * @method $this whereIdIs(int|string $value)           Filters rows with `eq` condition on column `oz_files`.`id`.
 * @method $this whereIdIsNot(int|string $value)        Filters rows with `neq` condition on column `oz_files`.`id`.
 * @method $this whereIdIsLt(int|string $value)         Filters rows with `lt` condition on column `oz_files`.`id`.
 * @method $this whereIdIsLte(int|string $value)        Filters rows with `lte` condition on column `oz_files`.`id`.
 * @method $this whereIdIsGt(int|string $value)         Filters rows with `gt` condition on column `oz_files`.`id`.
 * @method $this whereIdIsGte(int|string $value)        Filters rows with `gte` condition on column `oz_files`.`id`.
 * @method $this whereIdIsLike(string $value)           Filters rows with `like` condition on column `oz_files`.`id`.
 * @method $this whereIdIsNotLike(string $value)        Filters rows with `not_like` condition on column `oz_files`.`id`.
 * @method $this whereIdIsIn(array $value)              Filters rows with `in` condition on column `oz_files`.`id`.
 * @method $this whereIdIsNotIn(array $value)           Filters rows with `not_in` condition on column `oz_files`.`id`.
 * @method $this whereOwnerIdIs(int|string $value)      Filters rows with `eq` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsNot(int|string $value)   Filters rows with `neq` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsLt(int|string $value)    Filters rows with `lt` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsLte(int|string $value)   Filters rows with `lte` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsGt(int|string $value)    Filters rows with `gt` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsGte(int|string $value)   Filters rows with `gte` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsLike(string $value)      Filters rows with `like` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsNull()                   Filters rows with `is_null` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsNotNull()                Filters rows with `is_not_null` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsIn(array $value)         Filters rows with `in` condition on column `oz_files`.`owner_id`.
 * @method $this whereOwnerIdIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_files`.`owner_id`.
 * @method $this whereKeyIs(string $value)              Filters rows with `eq` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsNot(string $value)           Filters rows with `neq` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsLt(string $value)            Filters rows with `lt` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsLte(string $value)           Filters rows with `lte` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsGt(string $value)            Filters rows with `gt` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsGte(string $value)           Filters rows with `gte` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsLike(string $value)          Filters rows with `like` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsNotLike(string $value)       Filters rows with `not_like` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsIn(array $value)             Filters rows with `in` condition on column `oz_files`.`key`.
 * @method $this whereKeyIsNotIn(array $value)          Filters rows with `not_in` condition on column `oz_files`.`key`.
 * @method $this whereRefIs(string $value)              Filters rows with `eq` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsNot(string $value)           Filters rows with `neq` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsLt(string $value)            Filters rows with `lt` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsLte(string $value)           Filters rows with `lte` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsGt(string $value)            Filters rows with `gt` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsGte(string $value)           Filters rows with `gte` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsLike(string $value)          Filters rows with `like` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsNotLike(string $value)       Filters rows with `not_like` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsIn(array $value)             Filters rows with `in` condition on column `oz_files`.`ref`.
 * @method $this whereRefIsNotIn(array $value)          Filters rows with `not_in` condition on column `oz_files`.`ref`.
 * @method $this whereStorageIs(string $value)          Filters rows with `eq` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsNot(string $value)       Filters rows with `neq` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsLt(string $value)        Filters rows with `lt` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsLte(string $value)       Filters rows with `lte` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsGt(string $value)        Filters rows with `gt` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsGte(string $value)       Filters rows with `gte` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsLike(string $value)      Filters rows with `like` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsIn(array $value)         Filters rows with `in` condition on column `oz_files`.`storage`.
 * @method $this whereStorageIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_files`.`storage`.
 * @method $this whereCloneIdIs(int|string $value)      Filters rows with `eq` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsNot(int|string $value)   Filters rows with `neq` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsLt(int|string $value)    Filters rows with `lt` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsLte(int|string $value)   Filters rows with `lte` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsGt(int|string $value)    Filters rows with `gt` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsGte(int|string $value)   Filters rows with `gte` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsLike(string $value)      Filters rows with `like` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsNull()                   Filters rows with `is_null` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsNotNull()                Filters rows with `is_not_null` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsIn(array $value)         Filters rows with `in` condition on column `oz_files`.`clone_id`.
 * @method $this whereCloneIdIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_files`.`clone_id`.
 * @method $this whereSourceIdIs(int|string $value)     Filters rows with `eq` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsNot(int|string $value)  Filters rows with `neq` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsLt(int|string $value)   Filters rows with `lt` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsLte(int|string $value)  Filters rows with `lte` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsGt(int|string $value)   Filters rows with `gt` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsGte(int|string $value)  Filters rows with `gte` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsLike(string $value)     Filters rows with `like` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsNotLike(string $value)  Filters rows with `not_like` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsNull()                  Filters rows with `is_null` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsNotNull()               Filters rows with `is_not_null` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsIn(array $value)        Filters rows with `in` condition on column `oz_files`.`source_id`.
 * @method $this whereSourceIdIsNotIn(array $value)     Filters rows with `not_in` condition on column `oz_files`.`source_id`.
 * @method $this whereSizeIs(int $value)                Filters rows with `eq` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsNot(int $value)             Filters rows with `neq` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsLt(int $value)              Filters rows with `lt` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsLte(int $value)             Filters rows with `lte` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsGt(int $value)              Filters rows with `gt` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsGte(int $value)             Filters rows with `gte` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsLike(string $value)         Filters rows with `like` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsIn(array $value)            Filters rows with `in` condition on column `oz_files`.`size`.
 * @method $this whereSizeIsNotIn(array $value)         Filters rows with `not_in` condition on column `oz_files`.`size`.
 * @method $this whereMimeTypeIs(string $value)         Filters rows with `eq` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsNot(string $value)      Filters rows with `neq` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsLt(string $value)       Filters rows with `lt` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsLte(string $value)      Filters rows with `lte` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsGt(string $value)       Filters rows with `gt` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsGte(string $value)      Filters rows with `gte` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsLike(string $value)     Filters rows with `like` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsNotLike(string $value)  Filters rows with `not_like` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsIn(array $value)        Filters rows with `in` condition on column `oz_files`.`mime_type`.
 * @method $this whereMimeTypeIsNotIn(array $value)     Filters rows with `not_in` condition on column `oz_files`.`mime_type`.
 * @method $this whereExtensionIs(string $value)        Filters rows with `eq` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsNot(string $value)     Filters rows with `neq` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsLt(string $value)      Filters rows with `lt` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsLte(string $value)     Filters rows with `lte` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsGt(string $value)      Filters rows with `gt` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsGte(string $value)     Filters rows with `gte` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsLike(string $value)    Filters rows with `like` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsIn(array $value)       Filters rows with `in` condition on column `oz_files`.`extension`.
 * @method $this whereExtensionIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_files`.`extension`.
 * @method $this whereNameIs(string $value)             Filters rows with `eq` condition on column `oz_files`.`name`.
 * @method $this whereNameIsNot(string $value)          Filters rows with `neq` condition on column `oz_files`.`name`.
 * @method $this whereNameIsLt(string $value)           Filters rows with `lt` condition on column `oz_files`.`name`.
 * @method $this whereNameIsLte(string $value)          Filters rows with `lte` condition on column `oz_files`.`name`.
 * @method $this whereNameIsGt(string $value)           Filters rows with `gt` condition on column `oz_files`.`name`.
 * @method $this whereNameIsGte(string $value)          Filters rows with `gte` condition on column `oz_files`.`name`.
 * @method $this whereNameIsLike(string $value)         Filters rows with `like` condition on column `oz_files`.`name`.
 * @method $this whereNameIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_files`.`name`.
 * @method $this whereNameIsIn(array $value)            Filters rows with `in` condition on column `oz_files`.`name`.
 * @method $this whereNameIsNotIn(array $value)         Filters rows with `not_in` condition on column `oz_files`.`name`.
 * @method $this whereForIdIs(string $value)            Filters rows with `eq` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsNot(string $value)         Filters rows with `neq` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsLt(string $value)          Filters rows with `lt` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsLte(string $value)         Filters rows with `lte` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsGt(string $value)          Filters rows with `gt` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsGte(string $value)         Filters rows with `gte` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsLike(string $value)        Filters rows with `like` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsNotLike(string $value)     Filters rows with `not_like` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsNull()                     Filters rows with `is_null` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsNotNull()                  Filters rows with `is_not_null` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsIn(array $value)           Filters rows with `in` condition on column `oz_files`.`for_id`.
 * @method $this whereForIdIsNotIn(array $value)        Filters rows with `not_in` condition on column `oz_files`.`for_id`.
 * @method $this whereForTypeIs(string $value)          Filters rows with `eq` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsNot(string $value)       Filters rows with `neq` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsLt(string $value)        Filters rows with `lt` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsLte(string $value)       Filters rows with `lte` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsGt(string $value)        Filters rows with `gt` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsGte(string $value)       Filters rows with `gte` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsLike(string $value)      Filters rows with `like` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsNotLike(string $value)   Filters rows with `not_like` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsNull()                   Filters rows with `is_null` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsNotNull()                Filters rows with `is_not_null` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsIn(array $value)         Filters rows with `in` condition on column `oz_files`.`for_type`.
 * @method $this whereForTypeIsNotIn(array $value)      Filters rows with `not_in` condition on column `oz_files`.`for_type`.
 * @method $this whereForLabelIs(string $value)         Filters rows with `eq` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsNot(string $value)      Filters rows with `neq` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsLt(string $value)       Filters rows with `lt` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsLte(string $value)      Filters rows with `lte` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsGt(string $value)       Filters rows with `gt` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsGte(string $value)      Filters rows with `gte` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsLike(string $value)     Filters rows with `like` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsNotLike(string $value)  Filters rows with `not_like` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsIn(array $value)        Filters rows with `in` condition on column `oz_files`.`for_label`.
 * @method $this whereForLabelIsNotIn(array $value)     Filters rows with `not_in` condition on column `oz_files`.`for_label`.
 * @method $this whereDataIs(array $value)              Filters rows with `eq` condition on column `oz_files`.`data`.
 * @method $this whereDataIsNot(array $value)           Filters rows with `neq` condition on column `oz_files`.`data`.
 * @method $this whereDataIsLike(string $value)         Filters rows with `like` condition on column `oz_files`.`data`.
 * @method $this whereDataIsNotLike(string $value)      Filters rows with `not_like` condition on column `oz_files`.`data`.
 * @method $this whereCreatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_files`.`created_at`.
 * @method $this whereCreatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_files`.`created_at`.
 * @method $this whereUpdatedAtIs(int|string $value)    Filters rows with `eq` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsNot(int|string $value) Filters rows with `neq` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsLt(int|string $value)  Filters rows with `lt` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsLte(int|string $value) Filters rows with `lte` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsGt(int|string $value)  Filters rows with `gt` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsGte(int|string $value) Filters rows with `gte` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsLike(string $value)    Filters rows with `like` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsNotLike(string $value) Filters rows with `not_like` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsIn(array $value)       Filters rows with `in` condition on column `oz_files`.`updated_at`.
 * @method $this whereUpdatedAtIsNotIn(array $value)    Filters rows with `not_in` condition on column `oz_files`.`updated_at`.
 * @method $this whereIsValidIs(bool $value)            Filters rows with `eq` condition on column `oz_files`.`is_valid`.
 * @method $this whereIsValidIsNot(bool $value)         Filters rows with `neq` condition on column `oz_files`.`is_valid`.
 * @method $this whereIsNotValid()                      Filters rows with `is_false` condition on column `oz_files`.`is_valid`.
 * @method $this whereIsValid()                         Filters rows with `is_true` condition on column `oz_files`.`is_valid`.
 */
abstract class OZFilesQuery extends \Gobl\ORM\ORMTableQuery
{
	/**
	 * OZFilesQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			\OZONE\Core\Db\OZFile::TABLE_NAMESPACE,
			\OZONE\Core\Db\OZFile::TABLE_NAME
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public static function new(): static
	{
		return new \OZONE\Core\Db\OZFilesQuery();
	}
}
