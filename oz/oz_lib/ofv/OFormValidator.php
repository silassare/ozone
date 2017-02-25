<?php

	final class OFormValidator {
		private static $ofv_validators_loaded = false;
		private $form = array();
		private $rules_list = array();
		private $log_error = false;
		private $errors = array();

		public function __construct( array $form, $log_error = false ) {
			$this->form = $form;
			$this->log_error = !!$log_error;

			if ( !self::$ofv_validators_loaded ) {

				self::$ofv_validators_loaded = true;

				@OFormUtils::loadValidators( OZ_OZONE_SETTINGS_DIR . 'ofv_validators' );
				@OFormUtils::loadValidators( OZ_APP_SETTINGS_DIR . 'ofv_validators' );
			}
		}

		public function checkForm( array $rules_list ) {
			$this->rules_list = $rules_list;

			foreach ( $rules_list as $field_name => $rules ) {

				//verifions si la methode identifiee existe
				$ofv_func_name = 'ofv_' . $field_name;

				if ( is_callable( $ofv_func_name ) ) {

					//effectuons la verification
					call_user_func( $ofv_func_name, $this );

				} else {

					//si non formulaire invalide/ou le plus probable erreur interne
					$this->addError( 'OZ_FIELD_UKNOWN', array( $field_name ) );

				}
			}

			return ( count( $this->errors ) == 0 );
		}

		public function getField( $name ) {

			if ( isset( $this->form[ $name ] ) ) {
				return $this->form[ $name ];
			}

			return null;
		}

		public function getRules( $name ) {

			if ( isset( $this->rules_list[ $name ] ) ) {
				return $this->rules_list[ $name ];
			}

			return null;
		}

		public function getForm() {
			return $this->form;
		}

		public function setField( $name, $value ) {
			$this->form[ $name ] = $value;
		}

		public function updateForm( $name, $value ) {
			$this->form[ $name ] = $value;
		}

		public function getErrors() {
			return $this->errors;
		}

		public function addError( $e_msg, $e_data = null ) {
			$e = null;

			if ( $e_msg instanceof OZoneError ) {

				$e = $e_msg;
				$e_msg = $e->getMessage();
				$e_data = $e->getErrorData() || $e_data;

			} else {
				$e = new OZoneErrorInvalidForm( $e_msg, $e_data );
			}

			if ( $this->log_error ) {

				$this->errors[] = array( $e_msg, $e_data );

			} else {
				throw $e;
			}
		}
	}