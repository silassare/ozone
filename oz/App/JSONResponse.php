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

namespace OZONE\Core\App;

use OZONE\Core\Forms\Form;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class JSONResponse.
 */
final class JSONResponse implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var int ozone done response code
	 */
	public const RESPONSE_CODE_DONE = 0;

	/**
	 * @var int ozone error response code
	 */
	public const RESPONSE_CODE_ERROR = 1;

	/**
	 * @var array response data
	 */
	private array $response;

	/**
	 * JSONResponse constructor.
	 */
	public function __construct()
	{
		$this->response = [
			'error' => self::RESPONSE_CODE_DONE,
			'msg'   => 'OK',
			'data'  => [],
			'form'  => null,
		];
	}

	/**
	 * JSONResponse destructor.
	 */
	public function __destruct()
	{
		unset($this->response);
	}

	/**
	 * Sets an error message.
	 *
	 * @param string $msg the error message
	 *
	 * @return $this
	 */
	public function setError(string $msg = 'OZ_ERROR_INTERNAL'): self
	{
		$this->response['error'] = self::RESPONSE_CODE_ERROR;
		$this->response['msg']   = $msg;

		return $this;
	}

	/**
	 * Sets a successful message.
	 *
	 * @param string $msg the message
	 *
	 * @return $this
	 */
	public function setDone(string $msg = 'OK'): self
	{
		$this->response['error'] = self::RESPONSE_CODE_DONE;
		$this->response['msg']   = $msg;

		return $this;
	}

	/**
	 * Adds data to the response.
	 *
	 * @param array|ArrayCapableInterface $data the data
	 *
	 * @return $this
	 */
	public function setData(array|ArrayCapableInterface $data): self
	{
		$this->response['data'] = $data instanceof ArrayCapableInterface ? $data->toArray() : $data;

		return $this;
	}

	/**
	 * Sets a custom key/value to the response data.
	 *
	 * @param string $key   the key name
	 * @param mixed  $value the value to be added
	 *
	 * @return $this
	 */
	public function setDataKey(string $key, mixed $value): self
	{
		if (!empty($key)) {
			$this->response['data'][$key] = $value;
		}

		return $this;
	}

	/**
	 * Gets a custom key/value from the response data.
	 *
	 * @param string $key the key name
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function getDataKey(string $key, mixed $def = null): mixed
	{
		return $this->response['data'][$key] ?? $def;
	}

	/**
	 * Gets form.
	 *
	 * @return null|Form
	 */
	public function getForm(): ?Form
	{
		return $this->response['form'] ?? null;
	}

	/**
	 * Sets form.
	 *
	 * @param null|Form $form
	 *
	 * @return $this
	 */
	public function setForm(?Form $form): self
	{
		$this->response['form'] = $form;

		return $this;
	}

	/**
	 * Merge json response from a given instance.
	 *
	 * @param self $response
	 *
	 * @return $this
	 */
	public function merge(self $response): self
	{
		$this->response = $response->toArray();

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$res = $this->response;

		if (empty($res['form'])) {
			unset($res['form']);
		}

		return $res;
	}
}
