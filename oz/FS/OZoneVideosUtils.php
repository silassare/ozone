<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class OZoneVideosUtils
	{
		/**
		 * the video source file path
		 *
		 * @var string
		 */
		private $source_path;

		/**
		 * we use ffmpeg for video
		 *
		 * @var string
		 */
		private $ffmpeg = "ffmpeg";

		/**
		 * OZoneVideosUtils constructor.
		 *
		 * @param string $video_source_path the video file source path
		 */
		public function __construct($video_source_path)
		{
			$this->source_path = $video_source_path;
		}

		/**
		 * load the current video file
		 *
		 * @return bool
		 */
		public function load()
		{
			return $this->canLoadVideo();
		}

		/**
		 * make thumbnail of the current video
		 *
		 * @param string $destination_path the destination thumbnail path
		 *
		 * @return bool true if successful, false otherwise
		 */
		public function makeVideoThumb($destination_path)
		{
			if (file_exists($destination_path)) {
				unlink($destination_path);
			}

			$ffmpeg = $this->ffmpeg;
			$src    = $this->source_path;
			$sec    = $this->getRandFrameSec();

			// windows platform
			// $cmd = "$ffmpeg -i $src -an -ss 00:00:$sec -r 1 -vframes 1 -f mjpeg -y $dest";

			// linux platform
			// TODO make it run in background because php wait
			// use this to run in background
			// $cmd = "$ffmpeg -i $src -an -ss 00:00:$sec -r 1 -vframes 1 -f mjpeg -y $dest </dev/null >/dev/null 2>/dev/null &";
			// we want a result now, so we do it now and wait until job end
			$cmd = "$ffmpeg -i $src -an -ss 00:00:$sec -r 1 -vframes 1 -f mjpeg -y $destination_path";

			$this->execute($cmd);

			return file_exists($destination_path);
		}

		/**
		 * get random video frame second for thumbnail
		 *
		 * @return string
		 */
		private function getRandFrameSec()
		{
			return "02";
		}

		/**
		 * run a command line
		 *
		 * @param string $cmd the command to execute
		 */
		private function execute($cmd)
		{
			@shell_exec($cmd);
		}

		/**
		 * check if the current video is loadable
		 *
		 * @return bool
		 */
		public function canLoadVideo()
		{
			$src = $this->source_path;

			return file_exists($src) AND is_file($src) AND is_readable($src);
		}
	}
