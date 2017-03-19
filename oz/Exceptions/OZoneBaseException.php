<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Exceptions;

	use OZONE\OZ\OZone;
	use OZONE\OZ\Core\OZoneResponsesHolder;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	abstract class OZoneBaseException extends \Exception {

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

		/**
		 * @var array
		 */
		protected $data;

		/**
		 * @var \OZONE\OZ\Core\OZoneResponsesHolder
		 */
		private static $resp;

		/**
		 * OZoneBaseException constructor.
		 *
		 * @param string     $message the exception message
		 * @param int        $code    the exception code
		 * @param array|null $data    additional error data
		 */
		public function __construct( $message, $code, array $data = null ) {

			parent::__construct( $message, $code );

			$this->data = $data;

			self::$resp = new OZoneResponsesHolder( get_class( $this ) );
		}

		/**
		 * get exception data
		 * @return array
		 */
		public function getData() {
			return $this->data;
		}

		/**
		 * get a http header string that corresponds to this exception
		 *
		 * @return string
		 */
		private function getHeaderString() {
			$code = $this->getCode();;

			if ( array_key_exists( $code, self::$ERROR_HEADER_MAP ) ) {
				return self::$ERROR_HEADER_MAP[ $code ];
			}

			return self::$ERROR_HEADER_MAP[ OZoneBaseException::UNKNOWN_ERROR ];
		}

		/**
		 * show exception as json file
		 */
		protected function showJson() {

			self::$resp
				->setError( $this->getMessage() )
				->setData( $this->getData() );

			OZone::sayJson( self::$resp->getResponse() );
			exit;
		}

		/**
		 * show exception in a custom error page
		 */
		protected function showCustomErrorPage() {
			$back_url = OZ_APP_MAIN_URL; //SILO::TODO find last url or go home
			$http_response_header = $this->getHeaderString();
			$err_title = str_replace( 'HTTP/1.1 ', '', $http_response_header );
			$err_msg = $this->getMessage();//SILO::TODO translate/replace err_msg with phrases
			$err_data = $this->getData();//<-- utile pour la traduction

			$url = OZ_APP_TEMPLATES_DIR . 'error.otpl';

			$tpl_data = array(
				'oz_error_title'    => $err_title,
				'oz_error_desc'     => $err_msg,
				'oz_error_data'     => $err_data,
				'oz_error_url'      => $_SERVER[ 'REQUEST_URI' ],
				'oz_error_back_url' => $back_url
			);

			$o = new \OTpl();

			header( $http_response_header );
			$o->parse( $url )
				->runWith( $tpl_data );
			exit;//<--- exit
		}

		/**
		 * ozone exception to string formatter
		 *
		 * @return string
		 */
		public function __toString() {
			$e_data = json_encode( $this->getData() );
			$e_msg = ''
				. "\n\tFile    : {$this->getFile()}"
				. "\n\tLine    : {$this->getLine()}"
				. "\n\tCode    : {$this->getCode()}"
				. "\n\tMessage : {$this->getMessage()}"
				. "\n\tData    : $e_data"
				. "\n\tTrace   : {$this->getTraceAsString()}";

			return $e_msg;
		}

		/**
		 * custom procedure for each ozone exception type
		 *
		 * @return void;
		 */
		abstract public function procedure();
	}