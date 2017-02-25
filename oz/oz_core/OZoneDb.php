<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneDb {

		private static $db;

		function __construct() {
		}

		public function __destruct() {
			if ( isset( self::$db ) ) {
				self::$db = null;
			}
		}

		private function connect() {
			// INFORMATIONS DE CONNEXION
			$host = OZ_DB_HOST;
			$dbname = OZ_DB_NAME;
			$user = OZ_DB_USER;
			$password = OZ_DB_PASS;

			try {

				$pdo_options[ PDO::ATTR_ERRMODE ] = PDO::ERRMODE_EXCEPTION;

				$db = new PDO( 'mysql:host=' . $host . ';dbname=' . $dbname, $user, $password, $pdo_options );

				return $db;

			} catch ( Exception $e ) {
				//die( 'Erreur de connexion : ' . $e->getMessage());
				throw new OZoneErrorInternalError( 'OZ_DB_IS_DOWN', $e->getMessage() );
			}
		}

		private function getDb() {
			if ( empty( self::$db ) ) {
				self::$db = $this->connect();
			}

			return self::$db;
		}

		public function execute( $sql, array $params = null ) {
			$stmt = $this->getDb()->prepare( $sql );

			if ( $params !== null ) {
				foreach ( $params as $key => $value ) {
					$param_type = PDO::PARAM_STR;

					if ( is_int( $value ) ) {
						$param_type = PDO::PARAM_INT;
					} elseif ( is_bool( $value ) ) {
						$param_type = PDO::PARAM_BOOL;
					}

					$stmt->bindValue( is_int( $key ) ? $key + 1 : $key, $value, $param_type );
				}
			}

			$stmt->execute();

			return $stmt;
		}

		private function query( $sql, array $params = null ) {
			$stmt = $this->execute( $sql, $params );
			$row_count = $stmt->rowCount();

			$stmt->closeCursor();

			return $row_count;
		}

		public function select( $sql, array $params = null ) {
			return $this->execute( $sql, $params );
		}

		public function delete( $sql, array $params = null ) {
			return $this->query( $sql, $params );
		}

		public function insert( $sql, array $params = null ) {
			$stmt = $this->execute( $sql, $params );
			$last_id = self::$db->lastInsertId();

			$stmt->closeCursor();

			return $last_id;
		}

		public function update( $sql, array $params = null ) {
			return $this->query( $sql, $params );
		}

		public static function mapDbFieldsToExtern( array $data, array $fields, array $map_extend = array() ) {
			$out = array();

			$map_default = OZoneSettings::get( 'oz.db.fields.map' );

			$map = array_merge( $map_default, $map_extend );

			foreach ( $fields as $field ) {
				if ( !array_key_exists( $field, $map ) ) {
					throw new OZoneErrorInternalError( "`$field` db field is missing in db fields map" );
				}

				$field_out_name = $map[ $field ];

				if ( array_key_exists( $field, $data ) ) {
					$out[ $field_out_name ] = $data[ $field ];
				}
			}

			return $out;
		}
	}
