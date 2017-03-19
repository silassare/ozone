<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Install;

	use OZONE\OZ\Utils\OZoneStr;

	class AppCreate {

		public function __construct() {
		}

		private function genQuotedString() {
			return "'" . preg_quote( OZoneStr::genRandomString(), "'" ) . "'";
		}

		public function createSettings() {
			$data = array();

			//sel utilisé pour générer les clefs des fichiers
			$data[ 'OZ_FKEY_GEN_SALT' ] = $this->genQuotedString();
			//sel utilisé pour générer les identifiants de sessions
			$data[ 'OZ_SID_GEN_SALT' ] = $this->genQuotedString();
			//sel utilisé pour générer les tokens d'authentification
			$data[ 'OZ_AUTH_TOKEN_SALT' ] = $this->genQuotedString();
			//sel utilisé pour générer les identifiants de client
			$data[ 'OZ_CLID_GEN_SALT' ] = $this->genQuotedString();

			$otpl = new \OTpl();
			$otpl->parse( 'oz.keygen.salt.php.otpl' )->runSave( $data, 'oz.keygen.salt.php' );
		}
	}