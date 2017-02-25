<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneResponsesHolder {
		const CODE_DONE = 0;
		const CODE_ERROR = 1;

		private static $responses = array();

		private $label;

		public function __construct( $label ) {
			$this->label = $label;
			self::$responses[ $this->label ] = array( 'error' => OZoneResponsesHolder::CODE_DONE );
		}

		public static function getInstance( $label ) {
			return new self( $label );
		}

		private function setMessage( $msg, $code ) {
			return $this->setKey( 'error', $code )
				->setKey( 'msg', $msg );
		}

		public function setError( $msg = 'OZ_ERROR_INTERNAL' ) {
			return $this->setMessage( $msg, OZoneResponsesHolder::CODE_ERROR );
		}

		public function setDone( $msg = 'OK' ) {
			return $this->setMessage( $msg, OZoneResponsesHolder::CODE_DONE );
		}

		public function setData( $data ) {
			return $this->setKey( 'data', $data );
		}

		public function setKey( $key, $value ) {
			if ( !empty( $key ) ) {
				self::$responses[ $this->label ][ $key ] = $value;
			}

			return $this;
		}

		public function getResponse() {
			return self::$responses[ $this->label ];
		}

		public static function getResponses( $label = null ) {
			if ( !empty( $label ) ) {
				if ( array_key_exists( $label, self::$responses ) ) {
					return self::$responses[ $label ];
				} else {
					return null;
				}
			}

			return self::$responses;
		}
	}