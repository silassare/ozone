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

// Generates OZone's ORM classes in the repository's `.ozone/plugins/` (git-ignored), which psalm
// reads (`make lint`): tests/sandbox_build.php builds them in a temporary sandbox, then they are
// copied over the previous ones.

use OZONE\Core\App\Keys;
use Symfony\Component\Process\Process;

require __DIR__ . '/../vendor/autoload.php';

$rm = static function (string $dir): void {
	if (!\is_dir($dir)) {
		return;
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($files as $file) {
		$file->isDir() && !$file->isLink() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
	}

	\rmdir($dir);
};

$sandbox = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_orm_' . \bin2hex(\random_bytes(4)) . \DIRECTORY_SEPARATOR;
$from    = $sandbox . '.ozone/plugins/OZONE/Core/Db';
$to      = __DIR__ . '/../.ozone/plugins/OZONE/Core/Db';

\mkdir($sandbox, 0o775, true);

// `data/` is the volume a deployment mounts, and OZone refuses to create it (see StateLayout); the
// sandbox stands in for what `oz project create` does, exactly as tests/autoload.php does.
\mkdir($sandbox . 'data', 0o775, true);

\file_put_contents(
	$sandbox . '.env',
	'OZ_APP_SALT="' . \base64_encode(Keys::newSalt()) . '"' . \PHP_EOL
	. 'OZ_APP_SECRET="' . \base64_encode(Keys::newSecret()) . '"' . \PHP_EOL
);

try {
	(new Process([\PHP_BINARY, __DIR__ . '/sandbox_build.php', $sandbox]))->mustRun();

	$rm($to);

	\mkdir($to, 0o775, true);

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($from, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($files as $file) {
		$target = $to . \substr($file->getPathname(), \strlen($from));

		$file->isDir() ? \mkdir($target, 0o775, true) : \copy($file->getPathname(), $target);
	}
} finally {
	$rm($sandbox);
}

echo 'ORM classes generated in .ozone/plugins/', \PHP_EOL;
