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

namespace OZONE\OZ\Cli;

use Kli\KliCommand;

/**
 * Class Command.
 */
abstract class Command extends KliCommand
{
	/**
	 * Command constructor.
	 *
	 * @param string            $name command name
	 * @param \OZONE\OZ\Cli\Cli $cli  cli object to use
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	final public function __construct(string $name, Cli $cli)
	{
		parent::__construct($name, $cli);
		$this->describe();
	}

	/**
	 * Describe your command.
	 *
	 * Is called once the cli start.
	 * You can add Actions and Options to your command here.
	 */
	abstract protected function describe();
}
