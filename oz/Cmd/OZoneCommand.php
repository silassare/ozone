<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cmd;

	use OZONE\OZ\OZoneCli;
	use Kli\KliCommand;

	abstract class OZoneCommand extends KliCommand
	{

		/**
		 * OZoneCommand constructor.
		 *
		 * @param string             $name command name.
		 * @param \OZONE\OZ\OZoneCli $cli  cli object to use.
		 */
		final public function __construct($name, OZoneCli $cli)
		{
			parent::__construct($name, $cli);
			$this->describe();
		}

		/**
		 * describe your command.
		 *
		 * called once the cli start.
		 *
		 * @return void
		 */
		abstract protected function describe();
	}