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

namespace OZONE\Core\Seeders\Interfaces;

/**
 * Interface SeederInterface.
 *
 * Data a project or a plugin puts in its tables: **reference** data it needs to work (terms,
 * templates, permissions), run on every deploy, or **demo** data, invented for development and the
 * tests, which a deploy never runs. Listed in `oz.seeders`, so a plugin ships its seeders in its
 * settings and a disabled one seeds nothing.
 *
 * A seeder writes what is missing and never changes what is there: run on every deploy, it must leave
 * an administrator's edit as it found it. {@see \OZONE\Core\Seeders\Seeders::insertMissing()} is that
 * rule, for a seeder to use.
 */
interface SeederInterface
{
	/**
	 * Writes the rows that are missing, and answers how many it wrote.
	 */
	public function run(): int;

	/**
	 * Whether this seeds invented data, run only when demo data is asked for (`oz db seed --demo`).
	 */
	public static function isDemo(): bool;
}
