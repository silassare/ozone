<?php

	define( 'OTPL_ROOT_DIR', __DIR__ . DIRECTORY_SEPARATOR );
	define( 'OTPL_SRC_DIR', OTPL_ROOT_DIR . 'src' . DIRECTORY_SEPARATOR );

	include OTPL_SRC_DIR . 'include.php';

	//CORE::INCLUDES

	final class OTpl {
		const OTPL_VERSION = "php-1.1.4";
		const OTPL_VERSION_NAME = "OTpl php-1.1.4";

		const OTPL_COMPILE_DIR_NAME = "otpl_done";

		const OTPL_TAG_REG = "#<%(.+?)?%>#";
		const OTPL_SCRIPT_REG = "#^(\s*)?(if|else|for|foreach|while|break|continue|switch|case|default|})#";
		const OTPL_CLEAN_LEFT_REG = "#([\r\n]+)[\t ]*(<%.*?[}{]\s*%>)#";
		const OTPL_PRESERVE_NEWLINE_REG = "#(<%.*?%>)(\s+)(?!<%.*?[}{]\s*%>)#";

		private $input = "";
		private $output = null;
		private $is_url = false;
		private $src_path = "";
		private $dest_path = "";
		private $func_name = null;
		private $compile_time = 0;

		private static $checked_func = array();

		public function __construct() {
		}

		public function export( $dest ) {
			if ( file_exists( $dest ) ) {
				unlink( $dest );
			}

			copy( $this->dest_path, $dest );

			return $this;
		}

		private function save() {
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
				'{otpl_file_content}'
			), array(
				self::OTPL_VERSION,
				self::OTPL_VERSION_NAME,
				OTplUtils::OTPL_ROOT_REF,
				OTplUtils::OTPL_DATA_REF,
				$this->src_path,
				$this->compile_time,
				$this->func_name,
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

		public function parse( $tpl, $force_new_compile = false, $timed_func_name = false ) {

			$this->is_url = OTplUtils::isTplFile( $tpl );

			if ( $this->is_url ) {

				$tpl = OPathResolver::resolve( OTPL_ROOT_DIR, $tpl );
				$this->input = OTplUtils::loadFile( $tpl );
				$this->src_path = $tpl;

				$pinfos = pathinfo( $tpl );
				$dest_dir = $pinfos[ 'dirname' ];
				$out_fname = $pinfos[ 'filename' ] . '_' . md5_file( $tpl );

			} else {

				$this->input = $tpl;

				$dest_dir = OTPL_ROOT_DIR;
				$out_fname = 'otpl_' . md5( $tpl );
			}

			$dest_dir .= DIRECTORY_SEPARATOR . self::OTPL_COMPILE_DIR_NAME;

			if ( !file_exists( $dest_dir ) ) {
				mkdir( $dest_dir, 0777 );
			}

			$this->dest_path = $dest_dir . DIRECTORY_SEPARATOR . $out_fname . '.php';

			if ( !$timed_func_name ) {
				$this->func_name = 'otpl_func_' . md5( $out_fname );
			} else {
				$this->func_name = 'otpl_func_' . md5( $out_fname . microtime() );
			}

			if ( !file_exists( $this->dest_path ) OR $force_new_compile ) {
				$this->output = $this->Engine();
				$this->save();
			}

			return $this;
		}

		public function getSrcPath() {
			return $this->src_path;
		}

		public function getSrcDir() {
			$pinfos = pathinfo( $this->getSrcPath() );

			return $pinfos[ 'dirname' ];
		}

		public function getOutputUrl() {
			return $this->dest_path;
		}

		public function getFuncName() {
			return $this->func_name;
		}

		public static function register( $desc ) {

			if ( self::check( $desc ) ) {
				$func_name = $desc[ 'func_name' ];
				self::$checked_func[ $func_name ] = true;

				//make sure func_name is not already defined
				return !is_callable( $desc[ 'func_name' ] );
			}

			return false;
		}

		private static function check( array $desc = array() ) {

			if ( !isset( $desc[ 'func_name' ] ) ) {
				return false;
			}

			if ( !isset( $desc[ 'version' ] ) OR $desc[ 'version' ] != OTpl::OTPL_VERSION ) {
				return false;
			}

			return true;
		}

		private function checkPassed() {
			return array_key_exists( $this->func_name, self::$checked_func );
		}

		public function runWith( $data ) {

			if ( file_exists( $this->dest_path ) ) {
				require $this->dest_path;
			}

			if ( $this->checkPassed() AND is_callable( $this->func_name ) ) {
				call_user_func( $this->func_name, new OTplData( $data, $this ) );
			} else {
				@unlink( $this->dest_path );

				$tpl = $this->is_url ? $this->src_path : $this->input;
				$o = new OTpl();

				//let's parse again with a timed func_name: just for this use 
				$o->parse( $tpl, true, true )
					->runWith( $data );

				@unlink( $this->dest_path );
			}
		}

		public function runSave( $data, $out_url ) {
			$out_url = OPathResolver::resolve( __DIR__, $out_url );

			ob_start();
			$this->runWith( $data );

			$this->_write_file( $out_url, ob_get_clean() );
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

		private static function _clean( $tpl ) {
			$tpl = preg_replace( self::OTPL_CLEAN_LEFT_REG, "$1$2", $tpl );
			$tpl = preg_replace( self::OTPL_PRESERVE_NEWLINE_REG, "$1<?php echo '$2';?>", $tpl );

			return $tpl;
		}

		private function Engine() {
			$tpl = self::_clean( $this->input );

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