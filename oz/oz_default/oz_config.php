<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	// Lets write clean code -1 same as E_ALL
	error_reporting(-1);
	// error_reporting( 0 );

	// Ameliore la configuration de PHP
	ini_set('upload_max_filesize', '100M');
	ini_set('post_max_size', '100M');
	ini_set('default_charset', 'UTF-8');
	ini_set('magic_quotes_runtime', 0);

	// Permet d'économiser des octets
	ini_set("zlib.output_compression", 'On');

	// =============== FOR OZONE USE ONLY

	// Enable cookies if not done
	ini_set('session.use_cookies', '1');

	// Nous gérons nous même nos sessions grace à la classe \OZONE\OZ\Core\OZoneSessions
	ini_set('session.save_handler', 'user');

	// Evitons de prendre en compte des sessions id visibles dans les url
	ini_set('session.use_trans_sid', '0');

	// Only allow the session ID to come from cookies and nothing else.
	ini_set('session.use_only_cookies', '1');

	// Empêche que la version de php soit présente dans la réponse envoyée au navigateur/client
	if (function_exists('header_remove')) {
		header_remove('X-Powered-By'); // PHP 5.3+
	} else {
		ini_set('expose_php', 'off');
	}

	// we should use UTC for any date
	date_default_timezone_set('UTC');