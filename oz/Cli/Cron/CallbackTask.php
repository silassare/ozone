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

namespace OZONE\OZ\Cli\Cron;

use OZONE\OZ\Cli\Cron\Traits\TaskBase;

/**
 * Class CallbackTask.
 */
class CallbackTask extends TaskBase
{
	/**
	 * @var callable
	 */
	private $callable;

	/**
	 * CallbackTask constructor.
	 *
	 * @param string   $name
	 * @param callable $callable
	 * @param string   $description
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
		$fn = $this->callable;
		$fn();
	}
}
