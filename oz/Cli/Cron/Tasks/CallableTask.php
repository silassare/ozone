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

use Override;
use OZONE\Core\Exceptions\BaseException;
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
	 * @param string                    $name
	 * @param callable(JSONResult):void $callable    callable that will be called when the task runs
	 * @param string                    $description
	 */
	public function __construct(string $name, callable $callable, string $description = '')
	{
		parent::__construct($name, $description);

		$this->callable = $callable;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function run(): void
	{
		try {
			$fn = $this->callable;

			$fn($this->result);
		} catch (Throwable $t) {
			$this->result->setError($t->getMessage())
				->setData(BaseException::throwableDescribe($t, true));
		}
	}
}
