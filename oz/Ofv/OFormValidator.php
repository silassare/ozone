<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	use OZONE\OZ\Exceptions\OZoneBaseException;
	use OZONE\OZ\Exceptions\OZoneInternalError;
	use OZONE\OZ\Exceptions\OZoneInvalidFormException;

	final class OFormValidator {
		/**
		 * @var bool
		 */
		private static $ofv_validators_loaded = false;

		/**
		 * the form to validate
		 *
		 * @var array
		 */
		private $form = array();

		/**
		 * the form validation rules
		 *
		 * @var array
		 */
		private $rules_list = array();

		/**
		 * should we log errors
		 *
		 * @var bool
		 */
		private $log_error = false;

		/**
		 * errors cache list
		 *
		 * @var array
		 */
		private $errors = array();

		/**
		 * OFormValidator constructor.
		 *
		 * @param array $form      the form to validate
		 * @param bool  $log_error should we log error?
		 */
		public function __construct( array $form, $log_error = false ) {
			$this->form = $form;
			$this->log_error = !!$log_error;

			if ( !self::$ofv_validators_loaded ) {

				self::$ofv_validators_loaded = true;

				@OFormUtils::loadValidators( OZ_OZONE_SETTINGS_DIR . 'ofv_validators' );
				@OFormUtils::loadValidators( OZ_APP_SETTINGS_DIR . 'ofv_validators' );
			}
		}

		/**
		 * validate the form with a given rules list
		 *
		 * @param array $rules_list the rules to use for each field
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 */
		public function checkForm( array $rules_list ) {
			$this->rules_list = $rules_list;

			foreach ( $rules_list as $field_name => $rules ) {

				$ofv_func_name = __NAMESPACE__ . '\ofv_' . $field_name;

				//does this field validator exists?
				if ( function_exists( $ofv_func_name ) ) {

					call_user_func( $ofv_func_name, $this );

				} else {
					$this->addError( new OZoneInternalError( 'OZ_FIELD_UNKNOWN', array( $field_name ) ) );
				}
			}

			return ( 0 === count( $this->errors ) );
		}

		/**
		 * get the value of a given field name
		 *
		 * @param string $name the field name
		 *
		 * @return mixed|null
		 */
		public function getField( $name ) {

			if ( isset( $this->form[ $name ] ) ) {
				return $this->form[ $name ];
			}

			return null;
		}

		/**
		 * set the value of a given field name
		 *
		 * @param string $name  the field name
		 * @param mixed  $value the field value
		 */
		public function setField( $name, $value ) {
			$this->form[ $name ] = $value;
		}

		/**
		 * get the rules of a given field name
		 *
		 * @param string $name the field name
		 *
		 * @return mixed|null
		 */
		public function getRules( $name ) {

			if ( isset( $this->rules_list[ $name ] ) ) {
				return $this->rules_list[ $name ];
			}

			return null;
		}

		/**
		 * get the current form
		 *
		 * @return array
		 */
		public function getForm() {
			return $this->form;
		}

		/**
		 * get form errors
		 *
		 * @return array
		 */
		public function getErrors() {
			return $this->errors;
		}

		/**
		 * adds errors to invalidate this form
		 *
		 * @param mixed $e_msg  the error message
		 * @param mixed $e_data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 */
		public function addError( $e_msg, $e_data = null ) {
			$e = null;

			if ( $e_msg instanceof OZoneBaseException ) {

				$e = $e_msg;
				$e_msg = $e->getMessage();
				$e_data = $e->getData() || $e_data;

			} else {
				$e = new OZoneInvalidFormException( $e_msg, $e_data );
			}

			if ( $this->log_error ) {

				$this->errors[] = array( $e_msg, $e_data );

			} else {
				throw $e;
			}
		}
	}