<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneVideosUtils {
		private $src;
		private $ffmpeg = "ffmpeg";

		public function __construct( $src ) {
			$this->src = $src;
		}

		public function load() {
			$src = $this->src;

			if ( !file_exists( $src ) || !is_file( $src ) )
				return false;
			if ( !self::isValideVideo( $src ) )
				return false;

			return true;
		}

		public function makeVideoThumb( $dest ) {
			if ( file_exists( $dest ) ) {
				unlink( $dest );
			}

			$ffmpeg = $this->ffmpeg;
			$src = $this->src;
			$sec = $this->getRandFrameSec();

			//windows plateform
			//$cmd = "$ffmpeg -i $src -an -ss 00:00:$sec -r 1 -vframes 1 -f mjpeg -y $dest";

			//linux plateform
			//SILO::TODO make it run in background because php wait
			//use this to run in background
			//$cmd = "$ffmpeg -i $src -an -ss 00:00:$sec -r 1 -vframes 1 -f mjpeg -y $dest </dev/null >/dev/null 2>/dev/null &";
			//we want a result now, so we do it now and wait until job end
			$cmd = "$ffmpeg -i $src -an -ss 00:00:$sec -r 1 -vframes 1 -f mjpeg -y $dest";

			$this->execute( $cmd );

			return file_exists( $dest );
		}

		private function getRandFrameSec() {
			return "02";
		}

		private function execute( $cmd ) {
			@shell_exec( $cmd );
		}

		public static function isValideVideo( $src ) {
			return true;
		}
	}
