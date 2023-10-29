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
 * Class OZAuth.
 *
 * @property string                     $ref         Getter for column `oz_auths`.`ref`.
 * @property string                     $label       Getter for column `oz_auths`.`label`.
 * @property string                     $refresh_key Getter for column `oz_auths`.`refresh_key`.
 * @property string                     $provider    Getter for column `oz_auths`.`provider`.
 * @property array                      $payload     Getter for column `oz_auths`.`payload`.
 * @property string                     $code_hash   Getter for column `oz_auths`.`code_hash`.
 * @property string                     $token_hash  Getter for column `oz_auths`.`token_hash`.
 * @property \OZONE\Core\Auth\AuthState $state       Getter for column `oz_auths`.`state`.
 * @property int                        $try_max     Getter for column `oz_auths`.`try_max`.
 * @property int                        $try_count   Getter for column `oz_auths`.`try_count`.
 * @property int                        $lifetime    Getter for column `oz_auths`.`lifetime`.
 * @property string                     $expire      Getter for column `oz_auths`.`expire`.
 * @property array                      $options     Getter for column `oz_auths`.`options`.
 * @property string                     $created_at  Getter for column `oz_auths`.`created_at`.
 * @property string                     $updated_at  Getter for column `oz_auths`.`updated_at`.
 * @property bool                       $is_valid    Getter for column `oz_auths`.`is_valid`.
 */
abstract class OZAuth extends \Gobl\ORM\ORMEntity
{
	public const TABLE_NAME      = 'oz_auths';
	public const TABLE_NAMESPACE = 'OZONE\\Core\\Db';
	public const COL_REF         = 'auth_ref';
	public const COL_LABEL       = 'auth_label';
	public const COL_REFRESH_KEY = 'auth_refresh_key';
	public const COL_PROVIDER    = 'auth_provider';
	public const COL_PAYLOAD     = 'auth_payload';
	public const COL_CODE_HASH   = 'auth_code_hash';
	public const COL_TOKEN_HASH  = 'auth_token_hash';
	public const COL_STATE       = 'auth_state';
	public const COL_TRY_MAX     = 'auth_try_max';
	public const COL_TRY_COUNT   = 'auth_try_count';
	public const COL_LIFETIME    = 'auth_lifetime';
	public const COL_EXPIRE      = 'auth_expire';
	public const COL_OPTIONS     = 'auth_options';
	public const COL_CREATED_AT  = 'auth_created_at';
	public const COL_UPDATED_AT  = 'auth_updated_at';
	public const COL_IS_VALID    = 'auth_is_valid';

	/**
	 * OZAuth constructor.
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
		return new \OZONE\Core\Db\OZAuth($is_new, $strict);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZAuthsCrud
	 */
	public static function crud(): \OZONE\Core\Db\OZAuthsCrud
	{
		return \OZONE\Core\Db\OZAuthsCrud::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZAuthsController
	 */
	public static function ctrl(): \OZONE\Core\Db\OZAuthsController
	{
		return \OZONE\Core\Db\OZAuthsController::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZAuthsQuery
	 */
	public static function qb(): \OZONE\Core\Db\OZAuthsQuery
	{
		return \OZONE\Core\Db\OZAuthsQuery::new();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\Db\OZAuthsResults
	 */
	public static function results(\Gobl\DBAL\Queries\QBSelect $query): \OZONE\Core\Db\OZAuthsResults
	{
		return \OZONE\Core\Db\OZAuthsResults::new($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function table(): \Gobl\DBAL\Table
	{
		return \Gobl\ORM\ORM::table(static::TABLE_NAMESPACE, static::TABLE_NAME);
	}

	/**
	 * Getter for column `oz_auths`.`ref`.
	 *
	 * @return string
	 */
	public function getRef(): string
	{
		return $this->ref;
	}

	/**
	 * Setter for column `oz_auths`.`ref`.
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
	 * Getter for column `oz_auths`.`label`.
	 *
	 * @return string
	 */
	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * Setter for column `oz_auths`.`label`.
	 *
	 * @param string $label
	 *
	 * @return static
	 */
	public function setLabel(string $label): static
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`refresh_key`.
	 *
	 * @return string
	 */
	public function getRefreshKey(): string
	{
		return $this->refresh_key;
	}

	/**
	 * Setter for column `oz_auths`.`refresh_key`.
	 *
	 * @param string $refresh_key
	 *
	 * @return static
	 */
	public function setRefreshKey(string $refresh_key): static
	{
		$this->refresh_key = $refresh_key;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`provider`.
	 *
	 * @return string
	 */
	public function getProvider(): string
	{
		return $this->provider;
	}

	/**
	 * Setter for column `oz_auths`.`provider`.
	 *
	 * @param string $provider
	 *
	 * @return static
	 */
	public function setProvider(string $provider): static
	{
		$this->provider = $provider;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`payload`.
	 *
	 * @return array
	 */
	public function getPayload(): array
	{
		return $this->payload;
	}

	/**
	 * Setter for column `oz_auths`.`payload`.
	 *
	 * @param array $payload
	 *
	 * @return static
	 */
	public function setPayload(array $payload): static
	{
		$this->payload = $payload;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`code_hash`.
	 *
	 * @return string
	 */
	public function getCodeHash(): string
	{
		return $this->code_hash;
	}

	/**
	 * Setter for column `oz_auths`.`code_hash`.
	 *
	 * @param string $code_hash
	 *
	 * @return static
	 */
	public function setCodeHash(string $code_hash): static
	{
		$this->code_hash = $code_hash;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`token_hash`.
	 *
	 * @return string
	 */
	public function getTokenHash(): string
	{
		return $this->token_hash;
	}

	/**
	 * Setter for column `oz_auths`.`token_hash`.
	 *
	 * @param string $token_hash
	 *
	 * @return static
	 */
	public function setTokenHash(string $token_hash): static
	{
		$this->token_hash = $token_hash;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`state`.
	 *
	 * @return \OZONE\Core\Auth\AuthState
	 */
	public function getState(): \OZONE\Core\Auth\AuthState
	{
		return $this->state;
	}

	/**
	 * Setter for column `oz_auths`.`state`.
	 *
	 * @param \OZONE\Core\Auth\AuthState|string $state
	 *
	 * @return static
	 */
	public function setState(\OZONE\Core\Auth\AuthState|string $state): static
	{
		$this->state = $state;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`try_max`.
	 *
	 * @return int
	 */
	public function getTryMax(): int
	{
		return $this->try_max;
	}

	/**
	 * Setter for column `oz_auths`.`try_max`.
	 *
	 * @param int $try_max
	 *
	 * @return static
	 */
	public function setTryMax(int $try_max): static
	{
		$this->try_max = $try_max;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`try_count`.
	 *
	 * @return int
	 */
	public function getTryCount(): int
	{
		return $this->try_count;
	}

	/**
	 * Setter for column `oz_auths`.`try_count`.
	 *
	 * @param int $try_count
	 *
	 * @return static
	 */
	public function setTryCount(int $try_count): static
	{
		$this->try_count = $try_count;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`lifetime`.
	 *
	 * @return int
	 */
	public function getLifetime(): int
	{
		return $this->lifetime;
	}

	/**
	 * Setter for column `oz_auths`.`lifetime`.
	 *
	 * @param int $lifetime
	 *
	 * @return static
	 */
	public function setLifetime(int $lifetime): static
	{
		$this->lifetime = $lifetime;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`expire`.
	 *
	 * @return string
	 */
	public function getExpire(): string
	{
		return $this->expire;
	}

	/**
	 * Setter for column `oz_auths`.`expire`.
	 *
	 * @param int|string $expire
	 *
	 * @return static
	 */
	public function setExpire(int|string $expire): static
	{
		$this->expire = $expire;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`options`.
	 *
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * Setter for column `oz_auths`.`options`.
	 *
	 * @param array $options
	 *
	 * @return static
	 */
	public function setOptions(array $options): static
	{
		$this->options = $options;

		return $this;
	}

	/**
	 * Getter for column `oz_auths`.`created_at`.
	 *
	 * @return string
	 */
	public function getCreatedAT(): string
	{
		return $this->created_at;
	}

	/**
	 * Setter for column `oz_auths`.`created_at`.
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
	 * Getter for column `oz_auths`.`updated_at`.
	 *
	 * @return string
	 */
	public function getUpdatedAT(): string
	{
		return $this->updated_at;
	}

	/**
	 * Setter for column `oz_auths`.`updated_at`.
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
	 * Getter for column `oz_auths`.`is_valid`.
	 *
	 * @return bool
	 */
	public function isValid(): bool
	{
		return $this->is_valid;
	}

	/**
	 * Setter for column `oz_auths`.`is_valid`.
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
}
