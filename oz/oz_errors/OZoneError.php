<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	abstract class OZoneError extends Exception {

		const BAD_REQUEST = 400;
		const FORBIDDEN = 403;
		const NOT_FOUND = 404;
		const INTERNAL_ERROR = 500;

		const UNKNOWN_ERROR = 520;

		//ozone custom error codes
		const UNVERIFIED_USER = 1;
		const UNAUTHORIZED_ACTION = 2;
		const INVALID_FORM = 3;

		private static $ERROR_HEADER_MAP = array(
			//SILO:: si on doit forcement montrer une custom page alors c'est que user n'a pas les droits requis
			1   => 'HTTP/1.1 403 Forbidden',
			2   => 'HTTP/1.1 403 Forbidden',

			//SILO:: un formulaire invalide est conduit a une mauvaise requete lorsque nous ne redirigeons pas vers le formulaire
			//cas oû on n'a pas de formulaire ou que se soit juste une detection de paramettres invalides/manquantes d'un lien
			3   => 'HTTP/1.1 400 Bad Request',

			//SILO:: affichage normale et naturelle
			//SILO::TODO sois un peu comique tout utilisateur est important et ne doit pas être fustree d'avantage
			//TROP DE POISSONS NE GATTENT PAS LA SAUCE DIT-ON :)
			400 => 'HTTP/1.1 400 Bad Request',
			403 => 'HTTP/1.1 403 Forbidden',
			404 => 'HTTP/1.1 404 Not Found',
			500 => 'HTTP/1.1 500 Internal Server Error',

			//SILO:: default error str same as the CloudFlare's Unknown Error
			520 => 'HTTP/1.1 520 Unknown Error'
		);

		private $err_code;
		private $err_msg;
		private $err_data;
		private $err_name;

		private static $resp;

		public function __construct( $code, $name, $msg, $data = null ) {
			$this->err_code = $code;
			$this->err_name = $name;
			$this->err_msg = $msg;
			$this->err_data = $data;

			self::$resp = OZoneResponsesHolder::getInstance( $name );

			parent::__construct( $this->err_msg, $this->err_code );
		}

		public function getErrorData() {
			return $this->err_data;
		}

		private function getHeaderString() {
			$err_code = OZoneError::UNKNOWN_ERROR;

			if ( array_key_exists( $this->err_code, self::$ERROR_HEADER_MAP ) ) {
				$err_code = $this->err_code;
			}

			return self::$ERROR_HEADER_MAP[ $err_code ];
		}

		protected function showJson() {

			self::$resp->setError( $this->err_msg )
				->setData( $this->err_data );

			OZone::sayJson( self::$resp->getResponse() );
			exit;
		}

		protected function showCustomErrorPage() {
			$back_url = OZ_APP_MAIN_URL; //SILO::TODO find last url or go home
			$hstr = $this->getHeaderString();
			$err_title = str_replace( 'HTTP/1.1 ', '', $hstr );
			$err_msg = $this->err_msg;//SILO::TODO translate/replace err_msg with phrases
			$err_data = $this->err_data;//<-- utile pour la traduction

			$url = OZ_APP_TEMPLATES_DIR . 'error.otpl';

			$tpl_data = array(
				'oz_error_title'    => $err_title,
				'oz_error_desc'     => $err_msg,
				'oz_error_url'      => $_SERVER[ 'REQUEST_URI' ],
				'oz_error_back_url' => $back_url
			);

			$o = new OTpl();

			header( $hstr );
			$o->parse( $url )
				->runWith( $tpl_data );
			exit;//<--- exit
		}

		public function __toString() {
			$e_data = json_encode( $this->getErrorData() );
			$e_msg = ''
				. "\n\tFile    : {$this->getFile()}"
				. "\n\tLine    : {$this->getLine()}"
				. "\n\tCode    : {$this->getCode()}"
				. "\n\tMessage : {$this->getMessage()}"
				. "\n\tData    : $e_data"
				. "\n\tTrace   :	{$this->getTraceAsString()}";

			return $e_msg;
		}

		abstract public function procedure();
	}