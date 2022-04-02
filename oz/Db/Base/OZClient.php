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

use OZONE\OZ\Db\OZSession as OZSessionRealR;
use OZONE\OZ\Db\OZUser as OZUserRealR;

/**
 * Class OZClient.
 *
 * @property string $id                Getter for
 *                                     column `oz_clients`.`id`.
 * @property string $api_key           Getter for
 *                                     column `oz_clients`.`api_key`.
 * @property string $added_by          Getter for
 *                                     column `oz_clients`.`added_by`.
 * @property string $user_id           Getter for
 *                                     column `oz_clients`.`user_id`.
 * @property string $url               Getter for
 *                                     column `oz_clients`.`url`.
 * @property string $session_life_time Getter for
 *                                     column `oz_clients`.`session_life_time`.
 * @property string $about             Getter for
 *                                     column `oz_clients`.`about`.
 * @property array  $data              Getter for
 *                                     column `oz_clients`.`data`.
 * @property string $created_at        Getter for
 *                                     column `oz_clients`.`created_at`.
 * @property string $updated_at        Getter for
 *                                     column `oz_clients`.`updated_at`.
 * @property bool   $valid             Getter for
 *                                     column `oz_clients`.`valid`.
 */
abstract class OZClient extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME            = 'oz_clients';
	public const TABLE_NAMESPACE       = 'OZONE\\OZ\\Db';
	public const COL_ID                = 'client_id';
	public const COL_API_KEY           = 'client_api_key';
	public const COL_ADDED_BY          = 'client_added_by';
	public const COL_USER_ID           = 'client_user_id';
	public const COL_URL               = 'client_url';
	public const COL_SESSION_LIFE_TIME = 'client_session_life_time';
	public const COL_ABOUT             = 'client_about';
	public const COL_DATA              = 'client_data';
	public const COL_CREATED_AT        = 'client_created_at';
	public const COL_UPDATED_AT        = 'client_updated_at';
	public const COL_VALID             = 'client_valid';

	/**
	 * OZClient constructor.
	 *
	 * @param bool $is_new true for new entity false for entity fetched
	 *                     from the database, default is true
	 * @param bool $strict Enable/disable strict mode
	 */
	public function __construct(bool $is_new = true, bool $strict = true)
	{
		parent::__construct(self::TABLE_NAMESPACE, self::TABLE_NAME, $is_new, $strict);
	}

	/**
	 * Getter for column `oz_clients`.`id`.
	 *
	 * @return string
	 */
	public function getID(): string
	{
		return $this->{self::COL_ID};
	}

	/**
	 * Setter for column `oz_clients`.`id`.
	 *
	 * @param null|int|string $id
	 *
	 * @return static
	 */
	public function setID(string|int|null $id): self
	{
		$this->{self::COL_ID} = $id;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`api_key`.
	 *
	 * @return string
	 */
	public function getApiKey(): string
	{
		return $this->{self::COL_API_KEY};
	}

	/**
	 * Setter for column `oz_clients`.`api_key`.
	 *
	 * @param string $api_key
	 *
	 * @return static
	 */
	public function setApiKey(string $api_key): self
	{
		$this->{self::COL_API_KEY} = $api_key;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`added_by`.
	 *
	 * @return string
	 */
	public function getAddedBY(): string
	{
		return $this->{self::COL_ADDED_BY};
	}

	/**
	 * Setter for column `oz_clients`.`added_by`.
	 *
	 * @param int|string $added_by
	 *
	 * @return static
	 */
	public function setAddedBY(string|int $added_by): self
	{
		$this->{self::COL_ADDED_BY} = $added_by;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`user_id`.
	 *
	 * @return string
	 */
	public function getUserID(): string
	{
		return $this->{self::COL_USER_ID};
	}

	/**
	 * Setter for column `oz_clients`.`user_id`.
	 *
	 * @param null|int|string $user_id
	 *
	 * @return static
	 */
	public function setUserID(string|int|null $user_id): self
	{
		$this->{self::COL_USER_ID} = $user_id;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`url`.
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->{self::COL_URL};
	}

	/**
	 * Setter for column `oz_clients`.`url`.
	 *
	 * @param string $url
	 *
	 * @return static
	 */
	public function setUrl(string $url): self
	{
		$this->{self::COL_URL} = $url;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`session_life_time`.
	 *
	 * @return string
	 */
	public function getSessionLifeTime(): string
	{
		return $this->{self::COL_SESSION_LIFE_TIME};
	}

	/**
	 * Setter for column `oz_clients`.`session_life_time`.
	 *
	 * @param int|string $session_life_time
	 *
	 * @return static
	 */
	public function setSessionLifeTime(string|int $session_life_time): self
	{
		$this->{self::COL_SESSION_LIFE_TIME} = $session_life_time;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`about`.
	 *
	 * @return string
	 */
	public function getAbout(): string
	{
		return $this->{self::COL_ABOUT};
	}

	/**
	 * Setter for column `oz_clients`.`about`.
	 *
	 * @param string $about
	 *
	 * @return static
	 */
	public function setAbout(string $about): self
	{
		$this->{self::COL_ABOUT} = $about;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->{self::COL_DATA};
	}

	/**
	 * Setter for column `oz_clients`.`data`.
	 *
	 * @param array $data
	 *
	 * @return static
	 */
	public function setData(array $data): self
	{
		$this->{self::COL_DATA} = $data;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_clients`.`created_at`.
	 *
	 * @param int|string $created_at
	 *
	 * @return static
	 */
	public function setCreatedAT(string|int $created_at): self
	{
		$this->{self::COL_CREATED_AT} = $created_at;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_clients`.`updated_at`.
	 *
	 * @param int|string $updated_at
	 *
	 * @return static
	 */
	public function setUpdatedAT(string|int $updated_at): self
	{
		$this->{self::COL_UPDATED_AT} = $updated_at;

		return $this;
	}

	/**
	 * Getter for column `oz_clients`.`valid`.
	 *
	 * @return bool
	 */
	public function getValid(): bool
	{
		return $this->{self::COL_VALID};
	}

	/**
	 * Setter for column `oz_clients`.`valid`.
	 *
	 * @param bool $valid
	 *
	 * @return static
	 */
	public function setValid(bool $valid): self
	{
		$this->{self::COL_VALID} = $valid;

		return $this;
	}

	/**
	 * ManyToOne relation between `oz_clients` and `oz_users`.
	 *
	 * @return null|OZUserRealR
	 */
	public function getOwner(): ?OZUserRealR
	{
		$getters        = [\OZONE\OZ\Db\OZUser::COL_ID => [$this, 'getAddedBY']];
		$filters_bundle = $this->buildRelationFilter($getters, []);
		if (null === $filters_bundle) {
			return null;
		}

		return (new \OZONE\OZ\Db\OZUsersController())->getItem($filters_bundle);
	}

	/**
	 * ManyToOne relation between `oz_clients` and `oz_users`.
	 *
	 * @return null|OZUserRealR
	 */
	public function getUser(): ?OZUserRealR
	{
		$getters        = [\OZONE\OZ\Db\OZUser::COL_ID => [$this, 'getUserID']];
		$filters_bundle = $this->buildRelationFilter($getters, []);
		if (null === $filters_bundle) {
			return null;
		}

		return (new \OZONE\OZ\Db\OZUsersController())->getItem($filters_bundle);
	}

	/**
	 * OneToMany relation between `oz_clients` and `oz_sessions`.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param null|int $total    total rows without limit
	 *
	 * @return OZSessionRealR[]
	 */
	public function getSessions(array $filters = [], int $max = null, int $offset = 0, array $order_by = [], ?int &$total = -1): array
	{
		$getters        = [\OZONE\OZ\Db\OZSession::COL_CLIENT_ID => [$this, 'getID']];
		$filters_bundle = $this->buildRelationFilter($getters, $filters);
		if (null === $filters_bundle) {
			return [];
		}

		return (new \OZONE\OZ\Db\OZSessionsController())->getAllItems($filters_bundle, $max, $offset, $order_by, $total);
	}
}
