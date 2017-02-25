<?php

	define( 'OTPL_ROOT_DIR', __DIR__ . DIRECTORY_SEPARATOR );
	define( 'OTPL_SRC_DIR', OTPL_ROOT_DIR . 'src' . DIRECTORY_SEPARATOR );

	include OTPL_SRC_DIR . 'include.php';

	//CORE::INCLUDES

	final class OTpl {
		const OTPL_VERSION = "php-1.1.2";
		const OTPL_VERSION_NAME = "OTpl php-1.1.2";

		const OTPL_COMPILE_DIR_NAME = "otpl_done";

		const OTPL_TAG_REG = "#<%(.+?)?%>#";
		const OTPL_SCRIPT_REG = "#^(\s*)?(if|else|for|foreach|while|break|continue|switch|case|default|})#";

		private $src_path = "";
		private $dest_path = "";
		private $compile_time = 0;
		private $func_name = "otpl_nope_";
		private $data_key = null;
		private $input = "";
		private $output = null;

		private static $otpl_root = array();

		public function __construct() {
		}

		public function export( $dest ) {
			if ( file_exists( $dest ) ) {
				unlink( $dest );
			}

			copy( $this->dest_path, $dest );

			return $this;
		}

		private function save( $dest = null ) {
			$dest = $this->dest_path;

			$code = OTplUtils::loadFile( OTPL_SRC_DIR . "output.php.sample" );

			$code = str_replace( array(
				'{otpl_version}',
				'{otpl_version_name}',
				'{otpl_root_ref}',
				'{otpl_data_ref}',
				'{otpl_src_path}',
				'{otpl_compile_time}',
				'{otpl_func_name}',
				'{otpl_data_key}',
				'{otpl_file_content}'
			), array(
				self::OTPL_VERSION,
				self::OTPL_VERSION_NAME,
				OTplUtils::OTPL_ROOT_REF,
				OTplUtils::OTPL_DATA_REF,
				$this->src_path,
				$this->compile_time,
				$this->func_name,
				$this->data_key,
				$this->output
			), $code );

			$this->_write_file( $dest, $code );

			return $this;
		}

		private function _write_file( $path, $content ) {
			//SILO:: make sure that file is writeable at this location,
			if ( !file_exists( dirname( $path ) ) OR !is_writeable( dirname( $path ) ) ) {
				throw new Exception( "OTpl: '$path' is not writeable." );
			}

			$f = fopen( $path, 'w' );
			fwrite( $f, $content );
			fclose( $f );
		}

		public function parse( $tpl, $force_new_compile = false ) {
			$rand_str = "";
			$dest_dir = OTPL_ROOT_DIR;
			$is_url = OTplUtils::isTplFile( $tpl );
			$fname_prefixe = "otpl_";
			$DS = DIRECTORY_SEPARATOR;

			if ( $is_url ) {
				$tpl = OPathResolver::resolve( OTPL_ROOT_DIR, $tpl );
				$this->input = OTplUtils::loadFile( $tpl );
				$this->src_path = $tpl;
				$rand_str = md5_file( $tpl );

				$finfos = pathinfo( $tpl );
				$dest_dir = $finfos[ 'dirname' ];
				$fname_prefixe = $finfos[ 'filename' ];
			} else {
				$this->input = $tpl;
				$rand_str = md5( $tpl );
			}

			$this->output = $this->Engine();
			$dest_dir .= $DS . self::OTPL_COMPILE_DIR_NAME;
			$this->dest_path = $dest_dir . $DS . $fname_prefixe . '_' . $rand_str . '.php';

			if ( !file_exists( $dest_dir ) ) {
				mkdir( $dest_dir, 0777 );
			}

			$this->func_name = "otpl_executor_$rand_str";
			$this->data_key = $rand_str;

			if ( !file_exists( $this->dest_path ) OR $force_new_compile ) {
				$this->output = $this->Engine();
				$this->save();
			}

			return $this;
		}

		public function getSrcPath() {
			return $this->src_path;
		}

		public function getOutputUrl() {
			return $this->dest_path;
		}

		public function getDataKey() {
			return $this->data_key;
		}

		public function getFuncName() {
			return $this->func_name;
		}

		private function setExecData( $key, $data ) {
			self::$otpl_root[ $key ] = new OTplData( $data, $this );
		}

		public function runWith( $data ) {
			$this->setExecData( $this->data_key, $data );

			require_once $this->dest_path;
		}

		public function runSave( $data, $out_url ) {
			$out_url = OPathResolver::resolve( __DIR__, $out_url );
			ob_start();
			$this->runWith( $data );
			$content = ob_get_contents();
			ob_get_clean();

			$this->_write_file( $out_url, $content );
		}

		public static function register( $compile_desc ) {
			$func_name = $compile_desc[ 'func_name' ];
			$data_key = $compile_desc[ 'data_key' ];

			if ( is_callable( $func_name ) ) {
				//SILO::
				//on verifie s'il s'agit du template principale
				//si oui on execute
				//si non alors il s'agit d'une dependence/inclusion
				if ( isset( self::$otpl_root[ $data_key ] ) ) {
					call_user_func( $func_name, self::$otpl_root[ $data_key ] );

					return true;
				}
			}

			return false;
		}

		private function runner( $workers, $code ) {

			foreach ( $workers as $worker ) {

				$in = array();
				$reg = $worker[ 0 ];
				$fn = $worker[ 1 ];

				if ( is_callable( $fn ) ) {
					while ( preg_match( $reg, $code, $in ) ) {
						$found = $in[ 0 ];
						$args = array( $in, $this );
						$res = call_user_func_array( $fn, $args );
						$code = str_replace( $found, $res, $code );
					}
				}
			}

			return $code;
		}

		private function Engine() {
			$tpl = $this->input;

			$in = array();

			while ( preg_match( self::OTPL_TAG_REG, $tpl, $in ) ) {
				@list( $found, $code ) = $in;

				$code = $this->runner( OTplUtils::getCleaners(), $code );
				$code = $this->runner( OTplUtils::getReplacers(), $code );

				if ( preg_match( self::OTPL_SCRIPT_REG, $code ) ) {
					$result = "<?php $code ?>";
				} else {
					$result = "<?php echo ($code); ?>";
				}

				$tpl = str_replace( $found, $result, $tpl );
			}

			$this->compile_time = time();

			return $tpl;
		}
	}