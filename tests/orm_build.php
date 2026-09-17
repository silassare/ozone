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
// reads (`make lint`): a temporary sandbox project builds them (OZONE\Core\Testing\Sandbox), then
// they are copied over the previous ones.

use OZONE\Core\Testing\Sandbox;

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

try {
	Sandbox::create($sandbox);

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
