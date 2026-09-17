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

namespace OZONE\Core\Cli;

use Kli\KliCommand;
use Override;
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class Command.
 */
abstract class Command extends KliCommand
{
	/**
	 * Command constructor.
	 *
	 * @param string $name command name
	 * @param Cli    $cli  cli object to use
	 */
	protected function __construct(string $name, Cli $cli)
	{
		parent::__construct($name, $cli);
		$this->describe();
	}

	/**
	 * Should return a new instance.
	 *
	 * @param string $name
	 * @param Cli    $cli
	 */
	public static function instance(string $name, Cli $cli): static
	{
		return new static($name, $cli);
	}

	/**
	 * The OZone command line, with its JSON mode ({@see Cli::writeJson()}).
	 */
	#[Override]
	public function getCli(): Cli
	{
		$cli = parent::getCli();

		if (!$cli instanceof Cli) {
			throw new RuntimeException('An OZone command runs in the OZone command line.');
		}

		return $cli;
	}

	/**
	 * Describe your command.
	 *
	 * Is called once the cli start.
	 * You can add Actions and Options to your command here.
	 */
	abstract protected function describe();
}
