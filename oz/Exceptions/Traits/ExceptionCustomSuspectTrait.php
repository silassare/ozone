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

namespace OZONE\Core\Exceptions\Traits;

/**
 * Trait ExceptionCustomSuspectTrait.
 */
trait ExceptionCustomSuspectTrait
{
	/**
	 * Specify the config that cause the error.
	 *
	 * @param string $group
	 * @param string $key
	 *
	 * @return $this
	 */
	public function suspectConfig(string $group, string $key): static
	{
		return $this->suspect([
			'type'  => 'config',
			'group' => $group,
			'key'   => $key,
		]);
	}

	/**
	 * Specify the environment variable that cause the error.
	 *
	 * @param string $key
	 *
	 * @return $this
	 */
	public function suspectEnv(string $key): static
	{
		return $this->suspect([
			'type' => 'env',
			'key'  => $key,
		]);
	}
}
