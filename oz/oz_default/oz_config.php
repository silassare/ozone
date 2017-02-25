<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	//En phase de TEST
	//error_reporting( E_ALL );
	//SILO soyons ferme: -1 pour tout type que meme E_ALL n'inclut pas avant php 5.2
	error_reporting( -1 );

	//En phase de PRODUCTION
	//error_reporting( 0 );

	//Ameliore la configuration de PHP
	ini_set( 'upload_max_filesize', '100M' );
	ini_set( 'post_max_size', '100M' );
	ini_set( 'default_charset', 'UTF-8' );
	ini_set( 'magic_quotes_runtime', 0 );

	//permet d'economiser des octets
	ini_set( "zlib.output_compression", 'On' );

	//===============FOR OZONE USE ONLY

	//Enable cookies if not done
	ini_set( 'session.use_cookies', '1' );

	//SILO::nous gerons nous meme nos sessions grace à la classe OZoneSessions
	ini_set( 'session.save_handler', 'user' );

	//Evitons de prendre en compte des sessions id visibles dans les url
	ini_set( 'session.use_trans_sid', '0' );

	//Only allow the session ID to come from cookies and nothing else.
	ini_set( 'session.use_only_cookies', '1' );

	//empeche que la version de php soit presente dans la reponse envoyee au navigateur/client
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'X-Powered-By' ); // PHP 5.3+
	} else {
		ini_set( 'expose_php', 'off' );
	}