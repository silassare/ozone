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
 * Class OZAuth.
 *
 * @property string $ref         Getter for
 *                               column `oz_auths`.`ref`.
 * @property string $label       Getter for
 *                               column `oz_auths`.`label`.
 * @property string $refresh_key Getter for
 *                               column `oz_auths`.`refresh_key`.
 * @property string $for         Getter for
 *                               column `oz_auths`.`for`.
 * @property string $code_hash   Getter for
 *                               column `oz_auths`.`code_hash`.
 * @property string $token_hash  Getter for
 *                               column `oz_auths`.`token_hash`.
 * @property string $state       Getter for
 *                               column `oz_auths`.`state`.
 * @property int    $try_max     Getter for
 *                               column `oz_auths`.`try_max`.
 * @property int    $try_count   Getter for
 *                               column `oz_auths`.`try_count`.
 * @property int    $lifetime    Getter for
 *                               column `oz_auths`.`lifetime`.
 * @property string $expire      Getter for
 *                               column `oz_auths`.`expire`.
 * @property array  $data        Getter for
 *                               column `oz_auths`.`data`.
 * @property string $created_at  Getter for
 *                               column `oz_auths`.`created_at`.
 * @property string $updated_at  Getter for
 *                               column `oz_auths`.`updated_at`.
 * @property bool   $disabled    Getter for
 *                               column `oz_auths`.`disabled`.
 */
abstract class OZAuth extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_auths';
	public const TABLE_NAMESPACE = 'OZONE\\OZ\\Db';
	public const COL_REF         = 'auth_ref';
	public const COL_LABEL       = 'auth_label';
	public const COL_REFRESH_KEY = 'auth_refresh_key';
	public const COL_FOR         = 'auth_for';
	public const COL_CODE_HASH   = 'auth_code_hash';
	public const COL_TOKEN_HASH  = 'auth_token_hash';
	public const COL_STATE       = 'auth_state';
	public const COL_TRY_MAX     = 'auth_try_max';
	public const COL_TRY_COUNT   = 'auth_try_count';
	public const COL_LIFETIME    = 'auth_lifetime';
	public const COL_EXPIRE      = 'auth_expire';
	public const COL_DATA        = 'auth_data';
	public const COL_CREATED_AT  = 'auth_created_at';
	public const COL_UPDATED_AT  = 'auth_updated_at';
	public const COL_DISABLED    = 'auth_disabled';

	/**
	 * OZAuth constructor.
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
	 * Getter for column `oz_auths`.`ref`.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->{self::COL_REF};
	}

	/**
	 * Setter for column `oz_auths`.`ref`.
	 *
	 * @param string $ref
	 *
	 * @return static
	 */
	public function setRef(string $ref): self
	{
		$this->{self::COL_REF} = $ref;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`label`.
	 *
	 * @return string
	 */
	public function getLabel(): string
	{
		return $this->{self::COL_LABEL};
	}

	/**
	 * Setter for column `oz_auths`.`label`.
	 *
	 * @param string $label
	 *
	 * @return static
	 */
	public function setLabel(string $label): self
	{
		$this->{self::COL_LABEL} = $label;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`refresh_key`.
	 *
	 * @return string
	 */
	public function getRefreshKey(): string
	{
		return $this->{self::COL_REFRESH_KEY};
	}

	/**
	 * Setter for column `oz_auths`.`refresh_key`.
	 *
	 * @param string $refresh_key
	 *
	 * @return static
	 */
	public function setRefreshKey(string $refresh_key): self
	{
		$this->{self::COL_REFRESH_KEY} = $refresh_key;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`for`.
	 *
	 * @return string
	 */
	public function getFor(): string
	{
		return $this->{self::COL_FOR};
	}

	/**
	 * Setter for column `oz_auths`.`for`.
	 *
	 * @param string $for
	 *
	 * @return static
	 */
	public function setFor(string $for): self
	{
		$this->{self::COL_FOR} = $for;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`code_hash`.
	 *
	 * @return string
	 */
	public function getCodeHash(): string
	{
		return $this->{self::COL_CODE_HASH};
	}

	/**
	 * Setter for column `oz_auths`.`code_hash`.
	 *
	 * @param string $code_hash
	 *
	 * @return static
	 */
	public function setCodeHash(string $code_hash): self
	{
		$this->{self::COL_CODE_HASH} = $code_hash;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`token_hash`.
	 *
	 * @return string
	 */
	public function getTokenHash(): string
	{
		return $this->{self::COL_TOKEN_HASH};
	}

	/**
	 * Setter for column `oz_auths`.`token_hash`.
	 *
	 * @param string $token_hash
	 *
	 * @return static
	 */
	public function setTokenHash(string $token_hash): self
	{
		$this->{self::COL_TOKEN_HASH} = $token_hash;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`state`.
	 *
	 * @return string
	 */
	public function getState(): string
	{
		return $this->{self::COL_STATE};
	}

	/**
	 * Setter for column `oz_auths`.`state`.
	 *
	 * @param string $state
	 *
	 * @return static
	 */
	public function setState(string $state): self
	{
		$this->{self::COL_STATE} = $state;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`try_max`.
	 *
	 * @return int
	 */
	public function getTryMax(): int
	{
		return $this->{self::COL_TRY_MAX};
	}

	/**
	 * Setter for column `oz_auths`.`try_max`.
	 *
	 * @param int $try_max
	 *
	 * @return static
	 */
	public function setTryMax(int $try_max): self
	{
		$this->{self::COL_TRY_MAX} = $try_max;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`try_count`.
	 *
	 * @return int
	 */
	public function getTryCount(): int
	{
		return $this->{self::COL_TRY_COUNT};
	}

	/**
	 * Setter for column `oz_auths`.`try_count`.
	 *
	 * @param int $try_count
	 *
	 * @return static
	 */
	public function setTryCount(int $try_count): self
	{
		$this->{self::COL_TRY_COUNT} = $try_count;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`lifetime`.
	 *
	 * @return int
	 */
	public function getLifetime(): int
	{
		return $this->{self::COL_LIFETIME};
	}

	/**
	 * Setter for column `oz_auths`.`lifetime`.
	 *
	 * @param int $lifetime
	 *
	 * @return static
	 */
	public function setLifetime(int $lifetime): self
	{
		$this->{self::COL_LIFETIME} = $lifetime;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`expire`.
	 *
	 * @return string
	 */
	public function getExpire(): string
	{
		return $this->{self::COL_EXPIRE};
	}

	/**
	 * Setter for column `oz_auths`.`expire`.
	 *
	 * @param int|string $expire
	 *
	 * @return static
	 */
	public function setExpire(string|int $expire): self
	{
		$this->{self::COL_EXPIRE} = $expire;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`data`.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->{self::COL_DATA};
	}

	/**
	 * Setter for column `oz_auths`.`data`.
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
	 * Getter for column `oz_auths`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->{self::COL_CREATED_AT};
	}

	/**
	 * Setter for column `oz_auths`.`created_at`.
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
	 * Getter for column `oz_auths`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->{self::COL_UPDATED_AT};
	}

	/**
	 * Setter for column `oz_auths`.`updated_at`.
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
	 * Getter for column `oz_auths`.`disabled`.
	 *
	 * @return bool
	 */
	public function getDisabled(): bool
	{
		return $this->{self::COL_DISABLED};
	}

	/**
	 * Setter for column `oz_auths`.`disabled`.
	 *
	 * @param bool $disabled
	 *
	 * @return static
	 */
	public function setDisabled(bool $disabled): self
	{
		$this->{self::COL_DISABLED} = $disabled;

		return $this;
	}
}
