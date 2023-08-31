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

$cli_server_router = (new class() {
	/**
	 * @var string
	 */
	public string $index_file;

	/**
	 * Run.
	 *
	 * @return bool
	 */
	public function run(): bool
	{
		$root        = $_SERVER['DOCUMENT_ROOT'];
		$request_uri = $_SERVER['REQUEST_URI'];
		$blacklist   = [
			'~\.ht(pass|access)~', // htaccess files
			'~/debug.log$~', // debug log files
			'~/\.git/~', // git files
			'~/\.svn/~', // svn files
		];

		// php cli server check
		if (\PHP_SAPI === 'cli-server') {
			// To help the built-in PHP dev server, check if the request was actually for
			// something which should probably be served as a static file
			$url  = \parse_url($request_uri);
			$file = $root . $url['path'];

			// check if requested file is not in blacklist
			$blacklisted = false;
			foreach ($blacklist as $pattern) {
				if (\preg_match($pattern, $file)) {
					$blacklisted = true;

					break;
				}
			}

			// let the server handle static file if not blacklisted
			if (!$blacklisted && \file_exists($file)) {
				return false;
			}
		}

		$sub = \explode('/', $request_uri)[1] ?? '';

		if (
			!$sub || !\is_file(
				$index_file = $root . \DIRECTORY_SEPARATOR . $sub . \DIRECTORY_SEPARATOR . 'index.php'
			)
		) {
			if (!\is_file($index_file = $root . \DIRECTORY_SEPARATOR . 'index.php')) {
				\header('HTTP/1.1 404 Not Found', true, 404);
				echo 'Requested resource not found: ' . $request_uri;

				exit(1);
			}
		}

		$this->index_file = $index_file;

		return true;
	}

	/**
	 * Log to console.
	 *
	 * @param $message
	 */
	public function log($message): void
	{
		$stderr = \fopen('php://stderr', 'w+b');
		\fwrite($stderr, $message);
		\fclose($stderr);
	}
});

if (!$cli_server_router->run()) {
	$cli_server_router->log("\tHandler: php" . \PHP_EOL);

	return false;
}

$cli_server_router->log("\tHandler: ozone" . \PHP_EOL);

require_once $cli_server_router->index_file;
