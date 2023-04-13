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

$root       = $_SERVER['DOCUMENT_ROOT'];
$index_file = $root . '/index.php';
$blacklist  = [
	'/\.php$/', // php files
	'/\.htaccess$/', // htaccess files
	'/debug.log$/', // debug log files
	'/\/oz_private\//', // private files
	'/\/\.git\//', // git files
	'/\/\.svn\//', // svn files
];

// php cli server check
if (\PHP_SAPI === 'cli-server') {
	// To help the built-in PHP dev server, check if the request was actually for
	// something which should probably be served as a static file
	$url  = \parse_url($_SERVER['REQUEST_URI']);
	$file = $root . $url['path'];

	// check if requested file is not in blacklist
	$blacklisted = false;
	foreach ($blacklist as $pattern) {
		if (\preg_match($pattern, $file)) {
			$blacklisted = true;

			break;
		}
	}

	// check if the path is a file in public directory and is not a php file
	if (
		!$blacklisted
		&& \preg_match('/^\/public\//', $url['path'])
		&& !\preg_match('/\.php$/', $file)
		&& \is_file($file)
	) {
		return false;
	}
}

require $index_file;
