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

namespace OZONE\Core\Utils;

use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class JSONResult.
 */
class JSONResult implements ArrayCapableInterface, JsonOfInterface
{
	use ArrayCapableTrait;

	/**
	 * @var int success code
	 */
	public const SUCCESS = 0;

	/**
	 * @var int error code
	 */
	public const ERROR = 1;

	/**
	 * @var array payload data
	 */
	protected array $payload;

	/**
	 * JSONResult constructor.
	 */
	public function __construct()
	{
		$this->payload = [
			'error' => static::SUCCESS,
			'msg'   => 'OK',
			'data'  => [],
		];
	}

	/**
	 * JSONResult destructor.
	 */
	public function __destruct()
	{
		unset($this->payload);
	}

	/**
	 * Checks if the result is an error.
	 *
	 * @return bool
	 */
	public function isError(): bool
	{
		return $this->payload['error'] === static::ERROR;
	}

	/**
	 * Checks if the result is done.
	 *
	 * @return bool
	 */
	public function isDone(): bool
	{
		return $this->payload['error'] === static::SUCCESS;
	}

	/**
	 * Sets an error message.
	 *
	 * @param string $msg the error message
	 *
	 * @return $this
	 */
	public function setError(string $msg = 'OZ_ERROR_INTERNAL'): static
	{
		$this->payload['error'] = static::ERROR;
		$this->payload['msg']   = $msg;

		return $this;
	}

	/**
	 * Sets a successful message.
	 *
	 * @param string $msg the message
	 *
	 * @return $this
	 */
	public function setDone(string $msg = 'OK'): static
	{
		$this->payload['error'] = static::SUCCESS;
		$this->payload['msg']   = $msg;

		return $this;
	}

	/**
	 * Adds data to the payload.
	 *
	 * @param array|ArrayCapableInterface $data the data
	 *
	 * @return $this
	 */
	public function setData(array|ArrayCapableInterface $data): static
	{
		$this->payload['data'] = $data instanceof ArrayCapableInterface ? $data->toArray() : $data;

		return $this;
	}

	/**
	 * Sets a custom key/value to the payload data.
	 *
	 * @param string $key   the key name
	 * @param mixed  $value the value to be added
	 *
	 * @return $this
	 */
	public function setDataKey(string $key, mixed $value): static
	{
		if (!empty($key)) {
			$this->payload['data'][$key] = $value;
		}

		return $this;
	}

	/**
	 * Gets a custom key/value from the payload data.
	 *
	 * @param string $key the key name
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function getDataKey(string $key, mixed $def = null): mixed
	{
		return $this->payload['data'][$key] ?? $def;
	}

	/**
	 * Merge json payload from a given instance.
	 *
	 * @param JSONResult $payload
	 *
	 * @return static
	 */
	public function merge(self $payload): static
	{
		$this->payload += $payload->toArray();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		return $this->payload;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function revive(mixed $value): static
	{
		$r = new self();
		$r->payload += (array) $value;

		return $r;
	}
}
