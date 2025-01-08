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

namespace OZONE\Core\Cli\Cron\Tasks;

use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class CallableTask.
 */
class CallableTask extends AbstractTask
{
	/**
	 * @var callable
	 */
	private $callable;

	/**
	 * CallableTask constructor.
	 *
	 * @param string           $name
	 * @param callable():array $callable
	 * @param string           $description
	 */
	public function __construct(string $name, callable $callable, string $description = '')
	{
		parent::__construct($name, $description);

		$this->callable = $callable;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): void
	{
		try {
			$fn           = $this->callable;
			$this->result = $fn();
		} catch (Throwable $t) {
			throw (new RuntimeException('CallableTask failed.', null, $t))->suspectCallable($this->callable);
		}
	}
}
