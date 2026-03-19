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

	protected int $error;
	protected string $msg;
	protected array $data;

	/**
	 * JSONResult constructor.
	 */
	public function __construct()
	{
		$this->error = static::SUCCESS;
		$this->msg   = 'OK';
		$this->data  = [];
	}

	/**
	 * JSONResult destructor.
	 */
	public function __destruct()
	{
		unset($this->error, $this->msg, $this->data);
	}

	/**
	 * Checks if the result is an error.
	 *
	 * @return bool
	 */
	public function isError(): bool
	{
		return $this->error === static::ERROR;
	}

	/**
	 * Checks if the result is done.
	 *
	 * @return bool
	 */
	public function isDone(): bool
	{
		return $this->error === static::SUCCESS;
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
		$this->error = static::ERROR;
		$this->msg   = $msg;

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
		$this->error = static::SUCCESS;
		$this->msg   = $msg;

		return $this;
	}

	/**
	 * Sets the result data.
	 *
	 * @param array|ArrayCapableInterface $data the data
	 *
	 * @return $this
	 */
	public function setData(array|ArrayCapableInterface $data): static
	{
		$this->data = $data instanceof ArrayCapableInterface ? $data->toArray() : $data;

		return $this;
	}

	/**
	 * Sets a custom key/value to the result data.
	 *
	 * @param string $key   the key name
	 * @param mixed  $value the value to be added
	 *
	 * @return $this
	 */
	public function setDataKey(string $key, mixed $value): static
	{
		if (!empty($key)) {
			$this->data[$key] = $value;
		}

		return $this;
	}

	/**
	 * Gets the result data.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Gets a custom key/value from the result data.
	 *
	 * @param string $key the key name
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function getDataKey(string $key, mixed $def = null): mixed
	{
		return $this->data[$key] ?? $def;
	}

	/**
	 * Merge json result from a given instance.
	 *
	 * @param JSONResult $other
	 *
	 * @return static
	 */
	public function merge(self $other): static
	{
		$this->error = $other->error;
		$this->msg   = $other->msg;
		$this->data  = \array_merge($this->data, $other->data);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'error' => $this->error,
			'msg'   => $this->msg,
			'data'  => $this->data,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function revive(mixed $payload): static
	{
		$r = new static();

		$r->error = $payload['error'] === static::ERROR ? static::ERROR : static::SUCCESS;
		$r->msg   = $payload['msg'] ?? 'OK';
		$r->data  = $payload['data'] ?? [];

		return $r;
	}
}
