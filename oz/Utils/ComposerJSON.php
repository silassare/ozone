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

use AssertionError;
use InvalidArgumentException;
use JsonException;
use PHPUtils\Store\Store;

/**
 * Class ComposerJSON.
 */
class ComposerJSON
{
	private Store $data;

	/**
	 * ComposerJSON constructor.
	 */
	public function __construct(string $path)
	{
		try {
			\assert(\is_file($path));
			$data = \json_decode(\file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
		} catch (AssertionError) {
			throw new InvalidArgumentException('Invalid composer.json path.');
		} catch (JsonException) {
			throw new InvalidArgumentException('Invalid composer.json content.');
		}

		$this->data = new Store($data);
	}

	/**
	 * Gets a value from composer.json.
	 *
	 * @param string     $key     The key path
	 * @param null|mixed $default The default value
	 *
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->data->get($key, $default);
	}
}
