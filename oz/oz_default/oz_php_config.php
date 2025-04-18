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

// =============== GOOD PRACTICE
// help us to write clean code -1 same as E_ALL
\error_reporting(-1);

// Improves PHP configuration
\ini_set('upload_max_filesize', '100M');
\ini_set('post_max_size', '100M');
\ini_set('default_charset', 'UTF-8');
// stop PHP sending a Content-Type automatically
\ini_set('default_mimetype', '');

// Save bandwidth
\ini_set('zlib.output_compression', 'On');

// Prevents the php version from being present in the response sent to the browser/client
if (\function_exists('header_remove')) {
	\header_remove('X-Powered-By'); // PHP 5.3+
} else {
	\ini_set('expose_php', 'off');
}

// =============== FOR OZONE USE ONLY

// Enable cookies if not done
\ini_set('session.use_cookies', '1');

// We manage our sessions ourselves
// ini_set('session.save_handler', 'user'); // not allowed from php-7.2

// Let's avoid taking into account id sessions visible in url
\ini_set('session.use_trans_sid', '0');

// Only allow the session ID to come from cookies and nothing else.
\ini_set('session.use_only_cookies', '1');

// We should use UTC for any date
\date_default_timezone_set('UTC');

// Prevents user from setting self made sessions id
\ini_set('session.use_strict_mode', '1');
