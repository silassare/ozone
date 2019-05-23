<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli;

	use Kli\KliCommand;

	abstract class Command extends KliCommand
	{
		/**
		 * Command constructor.
		 *
		 * @param string                 $name command name.
		 * @param \OZONE\OZ\Cli\OZoneCli $cli  cli object to use.
		 *
		 * @throws \Kli\Exceptions\KliException
		 */
		final public function __construct($name, OZoneCli $cli)
		{
			parent::__construct($name, $cli);
			$this->describe();
		}

		/**
		 * Describe your command.
		 *
		 * Is called once the cli start.
		 * You can add Actions and Options to your command.
		 *
		 * @return void
		 */
		abstract protected function describe();
	}