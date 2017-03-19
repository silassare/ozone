<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Exceptions\OZoneInternalError;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneDb {
		/**
		 * PDO database connection instance
		 *
		 * @var \PDO
		 */
		private static $db;

		/**
		 * OZoneDb constructor.
		 */
		private function __construct() {
			if ( empty( self::$db ) ) {
				self::$db = $this->connect();
			}
		}

		/**
		 * OZoneDb destructor.
		 */
		public function __destruct() {
			if ( isset( self::$db ) ) {
				self::$db = null;
			}
		}

		/**
		 * get instance of \OZONE\OZ\Core\OZoneDb
		 *
		 * @return \OZONE\OZ\Core\OZoneDb
		 */
		public static function getInstance() {
			return new self();
		}

		/**
		 * connect to the db
		 *
		 * @return \PDO
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError    When database connection fails.
		 */
		private function connect() {

			$host = OZ_DB_HOST;
			$dbname = OZ_DB_NAME;
			$user = OZ_DB_USER;
			$password = OZ_DB_PASS;

			try {

				$pdo_options[ \PDO::ATTR_ERRMODE ] = \PDO::ERRMODE_EXCEPTION;

				$db = new \PDO( 'mysql:host=' . $host . ';dbname=' . $dbname, $user, $password, $pdo_options );

				return $db;

			} catch ( \Exception $e ) {
				//die( 'Erreur de connexion : ' . $e->getMessage());
				throw new OZoneInternalError( 'OZ_DB_IS_DOWN', $e->getMessage() );
			}
		}

		/**
		 * Execute a given sql query and params
		 *
		 * @param string     $sql    Your sql query
		 * @param array|null $params Your sql params
		 *
		 * @return \PDOStatement
		 */
		public function execute( $sql, array $params = null ) {
			$stmt = self::$db->prepare( $sql );

			if ( $params !== null ) {
				foreach ( $params as $key => $value ) {
					$param_type = \PDO::PARAM_STR;

					if ( is_int( $value ) ) {
						$param_type = \PDO::PARAM_INT;
					} elseif ( is_bool( $value ) ) {
						$param_type = \PDO::PARAM_BOOL;
					}

					$stmt->bindValue( is_int( $key ) ? $key + 1 : $key, $value, $param_type );
				}
			}

			$stmt->execute();

			return $stmt;
		}

		/**
		 * execute a query and return affected row count.
		 *
		 * @param string     $sql    Your sql query
		 * @param array|null $params Your sql params
		 *
		 * @return int    Affected row count.
		 */
		private function query( $sql, array $params = null ) {
			$stmt = $this->execute( $sql, $params );

			return $stmt->rowCount();
		}

		/**
		 * execute selection queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return \PDOStatement
		 */
		public function select( $sql, array $params = null ) {
			return $this->execute( $sql, $params );
		}

		/**
		 * execute deletion queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return int    Affected row count
		 */
		public function delete( $sql, array $params = null ) {
			return $this->query( $sql, $params );
		}

		/**
		 * execute insertion queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return int    The last inserted row id
		 */
		public function insert( $sql, array $params = null ) {
			$stmt = $this->execute( $sql, $params );
			$last_id = self::$db->lastInsertId();

			$stmt->closeCursor();

			return $last_id;
		}

		/**
		 * execute update queries.
		 *
		 * @param string     $sql    Your sql select query
		 * @param array|null $params Your sql select params
		 *
		 * @return int    Affected row count
		 */
		public function update( $sql, array $params = null ) {
			return $this->query( $sql, $params );
		}

		/**
		 * map database fields name to external fields name.\
		 *
		 * @param array $data       Database row object
		 * @param array $fields     Fields to conserve
		 * @param array $map_extend Extends 'oz.db.fields.map' settings
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		public static function mapDbFieldsToExtern( array $data, array $fields, array $map_extend = array() ) {
			$out = array();

			$map_default = OZoneSettings::get( 'oz.db.fields.map' );

			$map = array_merge( $map_default, $map_extend );

			foreach ( $fields as $field ) {
				if ( !array_key_exists( $field, $map ) ) {
					throw new OZoneInternalError( "'$field' db field is missing in your oz.db.fields.map" );
				}

				$field_out_name = $map[ $field ];

				if ( array_key_exists( $field, $data ) ) {
					$out[ $field_out_name ] = $data[ $field ];
				}
			}

			return $out;
		}
	}
