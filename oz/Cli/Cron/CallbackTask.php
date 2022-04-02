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

use OZONE\OZ\Cli\Cron\Interfaces\TaskInterface;

class CallbackTask implements TaskInterface
{
	private string $name;

	private string $description;

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
		$this->name        = $name;
		$this->description = $description;
		$this->callable    = $callable;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string
	{
		return $this->description;
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
