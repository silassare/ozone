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

// Prepares a unit suite sandbox: generates OZone's ORM classes (`.ozone/plugins/`), as `oz db build`
// does in a project, and creates the schema in its SQLite database. tests/autoload.php runs it in
// its own process, so the suite then boots with both present, like an installed project.
//
// With `installed` as second argument the schema is created the way a deployed project gets it: a
// migration is created and installed, which records its version (`OZ_MIGRATION_VERSION`), so every
// later boot loads the schema from that migration instead of rebuilding it from the table builders.
// `make benchmark-http` serves such a sandbox; the unit suite keeps the development path.

use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use OZONE\Core\App\Db;
use OZONE\Core\App\Settings;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use OZONE\Tests\App;

$loader = require __DIR__ . '/../vendor/autoload.php';

// Also with a vendor installed without dev dependencies (`make benchmark-http`).
$loader->addPsr4('OZONE\Tests\\', __DIR__ . '/');

$sandbox   = $argv[1] ?? '';
$installed = 'installed' === ($argv[2] ?? '');

if ('' === $sandbox || !\is_dir($sandbox)) {
	\fwrite(\STDERR, 'Usage: php tests/sandbox_build.php <sandbox directory>' . \PHP_EOL);

	exit(1);
}

Settings::addSource(__DIR__ . '/settings');

OZone::bootstrap(new App($sandbox));

$db  = db();
$ns  = Db::getOZoneDbNamespace();
$gen = new CSGeneratorORM($db);

$gen->ignorePrivateTables(false);
$gen->ignorePrivateColumns(false);
$gen->generate($db->getTables($ns), ORM::getOutputDir($ns));

if ($installed) {
	$mg = new Migrations();

	$mg->create(true, 'sandbox');
	$mg->install($mg->getLatestMigration());
} else {
	// The schema, in the sandbox SQLite database (tests/settings/oz.db.php).
	$sql = \trim($db->getGenerator()->buildDatabase());

	if ('' !== $sql) {
		$db->executeMulti($sql);
	}
}
