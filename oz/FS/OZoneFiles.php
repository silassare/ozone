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

	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneKeyGen;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Utils\OZoneOmlTextHelper;
	use OZONE\OZ\Utils\OZoneStr;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class OZoneFiles
	{

		/**
		 * the user id
		 *
		 * @var string|int
		 */
		private $uid;

		/**
		 * user directory path
		 *
		 * @var string
		 */
		private static $oz_user_files = OZ_APP_DIR . 'oz_userfiles' . DS;

		/**
		 * user directory path
		 *
		 * @var string
		 */
		private $user_dir;

		/**
		 * user files thumbnails directory path
		 *
		 * @var string
		 */
		private $user_thumb_dir;

		/**
		 * the latest file error message
		 *
		 * @var string
		 */
		private $message = null;

		/**
		 * current ozone file info
		 *
		 * @var array
		 */
		private $oz_file_info = [
			'fid'    => null,
			'uid'    => null,
			'fkey'   => null,
			'fclone' => null,
			'ftype'  => null,
			'fname'  => null,
			'flabel' => null,
			'fpath'  => null,
			'fthumb' => null,
			'ftime'  => null
		];

		/**
		 * OZoneFiles constructor.
		 *
		 * @param string|int $uid the user id
		 */
		function __construct($uid)
		{
			$fs                        = new OZoneFS(self::$oz_user_files);
			$this->oz_file_info['uid'] = $this->uid = $uid;
			$this->user_dir            = self::$oz_user_files . $uid;
			$this->user_thumb_dir      = $this->user_dir . DS . '_thumb';

			// create this user files directory if not done
			$fs->mkdir($this->user_thumb_dir);
		}

		/**
		 * make clone of an ozone file
		 *
		 * @param array $info the ozone file to clone info
		 */
		private function cloneFile(array $info)
		{
			$this->oz_file_info = $info;

			// don't clone cloned file, go clone the source file > we could then easily trace the file
			if (!$this->isClone()) {
				$this->oz_file_info['fclone'] = $this->oz_file_info['fid'];
			}

			// force new file
			$this->oz_file_info['fid'] = null;
			// this clone belong to the user
			$this->oz_file_info['uid'] = $this->uid;
		}

		/**
		 * set a file from a given already saved file id
		 *
		 * @param string $fid   the file id
		 * @param string $key   the file key
		 * @param bool   $clone the file is a clone or not
		 *
		 * @return bool
		 */
		public function setFromFid($fid, $key, $clone = false)
		{
			$info = OZoneFilesUtils::getFileFromFid($fid, $key);
			if (!$info) {
				$this->message = 'OZ_FILE_NOT_FOUND';

				return false;
			}

			if ($clone) {
				$this->cloneFile($info);
			} else {
				$this->oz_file_info = $info;
			}

			return true;
		}

		/**
		 * check if a file is an ozone file alias
		 *
		 * @param array $file the file info
		 *
		 * @return bool true
		 */
		private function isUploadedFileAlias($file)
		{
			// check file name and extension
			if (!preg_match("#^[a-z0-9_]+\.ofa$#i", $file['name'])) return false;
			// the correct mime type
			if ($file['type'] !== "text/x-ozone-file-alias") return false;
			// check alias file size 4ko
			if ($file['size'] > 4 * 1024) return false;

			return true;
		}

		/**
		 * parse an alias file
		 *
		 * @param string $alias_src the alias file source path
		 *
		 * @return array|bool the file original info or false if parsing fail
		 */
		private function parseFileAlias($alias_src)
		{
			if (empty($alias_src) || !file_exists($alias_src) || !is_file($alias_src) || !is_readable($alias_src)) {
				OZoneAssert::assertAuthorizeAction(false, 'OZ_FILE_ALIAS_UNKNOWN');
			}

			$desc = file_get_contents($alias_src);
			$data = json_decode($desc, true);

			if (!is_array($data) OR !array_key_exists('fid', $data) OR !array_key_exists('fkey', $data)) {
				OZoneAssert::assertAuthorizeAction(false, 'OZ_FILE_ALIAS_PARSE_ERROR');
			}

			$info = OZoneFilesUtils::getFileFromFid($data['fid'], $data['fkey']);

			if (!$info) {
				OZoneAssert::assertAuthorizeAction(false, 'OZ_FILE_ALIAS_NOT_FOUND');//SILO::TODO MAYBE FORBIDDEN
			}

			move_uploaded_file($alias_src, $this->user_dir . DS . $data['fid'] . ".ofa");

			return $info;
		}

		/**
		 * set file from upload
		 *
		 * @param array $uploaded_file the uploaded file info
		 *
		 * @return bool true if successful, false if fails
		 */
		public function setFileFromUpload($uploaded_file)
		{
			if (( int )$uploaded_file['error'] != UPLOAD_ERR_OK) {
				$this->codeToMessage($uploaded_file['error']);

				return false;
			}

			if (!is_uploaded_file($uploaded_file['tmp_name'])) {
				$this->message = 'OZ_FILE_NOT_VALID';

				return false;
			}

			if ($this->isUploadedFileAlias($uploaded_file)) {
				$result = $this->parseFileAlias($uploaded_file['tmp_name']);
				$this->cloneFile($result);

				return true;
			}

			$fname = $uploaded_file['name'];//nom du fichier sur le mobile/ordinateur de user
			$ftype = $uploaded_file['type'];//mime type ex: image/png

			// live recorded blob files as audio/video/image don't have a valid name
			if ($fname === 'blob') {
				$fname = $uploaded_file['name'] = $this->getOptionalFileName($ftype);
			}

			$gen_info    = $this->genFileInfo($fname, $ftype);
			$destination = $gen_info['dest_path'];
			$thumb_dest  = $gen_info['thumb_path'];

			$this->safeDelete($destination);

			// try move the uploaded file
			$result = move_uploaded_file($uploaded_file['tmp_name'], $destination);

			if ($result) {
				$this->oz_file_info['fname'] = $fname;
				$this->oz_file_info['fpath'] = $destination;
				$this->oz_file_info['ftype'] = $ftype;
				$this->oz_file_info['fsize'] = filesize($destination);

				// make thumbnail if possible
				$this->makeThumb($ftype, $destination, $thumb_dest);

				$this->message = 'OZ_FILE_SAVE_SUCCESS';

				return true;
			} else {
				$this->message = 'OZ_FILE_SAVE_FAIL';

				return false;
			}
		}

		/**
		 * generate a file info: file name, file destination ...
		 *
		 * @param string $source_name the original file name
		 * @param string $source_type the original file mime type
		 *
		 * @return array    the generated file info
		 */
		public function genFileInfo($source_name, $source_type)
		{
			$uid = $this->uid;

			$ext       = $this->getExtension($source_name, $source_type);//extension du fichier
			$name      = $uid . '_' . time() . '_' . rand(111111, 999999);
			$name_full = $name . '.' . $ext;
			$dest      = $this->user_dir . DS . $name_full;
			$thumb     = $this->user_thumb_dir . DS . $name . '.jpg';

			return ['name' => $name, 'name_full' => $name_full, 'dest_path' => $dest, 'thumb_path' => $thumb];
		}

		/**
		 * make a thumbnail of the current file with a given crop zone coordinates, for profile pic
		 *
		 * @param array $coordinates the crop zone coordinates
		 *
		 * @return bool        true if successful, false if fails
		 */
		public function makeProfilePic(array $coordinates)
		{
			$img_utils_obj = new OZoneImagesUtils($this->oz_file_info['fpath']);
			$sizex         = $sizey = OZoneSettings::get('oz.user', 'OZ_PPIC_MIN_SIZE');
			$quality       = 100;//jpeg image quality: 0 to 100
			$safe_coords   = null;

			// each file clone has its own thumbnail
			// because crop zone coordinates may be different from a clone to another
			if ($this->isClone()) {
				$gen_info                     = $this->genFileInfo($this->oz_file_info['fname'], $this->oz_file_info['ftype']);
				$this->oz_file_info['fthumb'] = $gen_info['thumb_path'];
			}

			$thumb = $this->oz_file_info['fthumb'];

			if (!empty($coordinates) AND isset($coordinates['x']) AND isset($coordinates['y']) AND isset($coordinates['w']) AND isset($coordinates['h'])) {
				$safe_coords = [
					'x' => intval($coordinates['x']),
					'y' => intval($coordinates['y']),
					'w' => intval($coordinates['w']),
					'h' => intval($coordinates['h'])
				];
			}

			if ($img_utils_obj->load()) {
				if (is_null($safe_coords)) {
					$img_utils_obj->cropAndSave($thumb, $quality, $sizex, $sizey);
				} else {
					$img_utils_obj->cropAndSave($thumb, $quality, $sizex, $sizey, $coordinates, false);
				}
			} else { /*this file is not a valid image*/

				$this->message = 'OZ_IMAGE_NOT_VALID';
				// be aware don't delete logged file, as they are already in database
				$this->safeDelete($this->oz_file_info['fpath']);
				$this->safeDelete($thumb);

				return false;
			}

			return true;
		}

		/**
		 * check if this file is a cloned file
		 *
		 * @return bool
		 */
		public function isClone()
		{
			return !empty($this->oz_file_info['fclone']);
		}

		/**
		 * check if this file has a thumbnail
		 *
		 * @return bool
		 */
		public function hasThumb()
		{
			$thumb = $this->oz_file_info['fthumb'];

			return !empty($thumb) AND file_exists($thumb);
		}

		/**
		 * generated a thumbnail for the file
		 *
		 * @param string $type   the file mime type
		 * @param string $source the file source path
		 * @param string $dest   the thumbnail destination path
		 *
		 * @return bool true if successful, false if fails
		 */
		private function makeThumb($type, $source, $dest)
		{
			$quality         = 50;
			$max_thumb_width = $max_thumb_height = OZoneSettings::get('oz.user', 'OZ_THUMB_MAX_SIZE');
			$done            = false;
			$file_category   = OZoneFilesUtils::mimeToCategory($type);

			if ($this->hasThumb()) {
				return true;
			}

			switch ($file_category) {
				case 'image':
					$img_utils_obj = new OZoneImagesUtils($source);

					if ($img_utils_obj->load()) {
						$advice = $img_utils_obj->adviceBestSize($max_thumb_width, $max_thumb_height);

						$img_utils_obj->resizeImage($advice['w'], $advice['h'], $advice['crop'])
									  ->saveImage($dest, $quality);

						$done = true;
					}
					break;
				case 'video':

					$vid_utils_obj = new OZoneVideosUtils($source);
					if ($vid_utils_obj->load()) {
						$done = $vid_utils_obj->makeVideoThumb($dest);
						if ($done) {
							$img_utils_obj = new OZoneImagesUtils($dest);

							if ($img_utils_obj->load()) {
								$advice = $img_utils_obj->adviceBestSize($max_thumb_width, $max_thumb_height);

								$img_utils_obj->resizeImage($advice['w'], $advice['h'], $advice['crop'])
											  ->saveImage($dest, $quality);
							}
						}
					}
					break;
				default:
					// other files
			}

			if ($done) {
				$this->oz_file_info['fthumb'] = $dest;
			}

			return $done;
		}

		/**
		 * get extension from a file with a given file path and mime type
		 *
		 * @param string $path the file path
		 * @param string $type the file mime type
		 *
		 * @return string
		 */
		public function getExtension($path, $type)
		{
			$ext = strtolower(substr($path, (strrpos($path, '.') + 1)));

			if (empty($ext)) {
				$ext = OZoneFilesUtils::mimeTypeToExtension($type);
			}

			return $ext;
		}

		/**
		 * generate a file name with a given mime type
		 *
		 * @param string $type the file mime type
		 *
		 * @return string
		 */
		public function getOptionalFileName($type)
		{
			$ext = 'ext';
			switch ($type) {
				case "image/png":
				case "image/jpeg":
				case "image/jpg":
				case "audio/wav":
					$ext = strtolower(substr($type, (strrpos($type, '/') + 1)));
					break;
			}

			$name = $this->uid . "-" . time() . "." . $ext;

			return $name;
		}

		/**
		 * log the file in the database to have an unique file id
		 *
		 * @param string $label the file log label
		 *
		 * @return array the ozone file info with additional properties
		 */
		public function logFile($label)
		{
			if (empty($this->oz_file_info['fid'])) {
				$this->oz_file_info['fname']  = OZoneStr::clean($this->oz_file_info['fname']);
				$this->oz_file_info['flabel'] = OZoneStr::clean($label);
				$this->oz_file_info['ftime']  = time();
				$this->oz_file_info['fkey']   = OZoneKeyGen::genFileKey($this->oz_file_info['fpath']);

				$sql = "
					INSERT INTO oz_files( file_id,user_id,file_key,file_clone,file_type,file_size,file_name,file_label,file_path,file_thumb,file_upload_time )
					VALUES( :fid,:uid,:fkey,:fclone,:ftype,:fsize,:fname,:flabel,:fpath,:fthumb,:ftime )";

				$insert = OZoneDb::getInstance()
								 ->insert($sql, [
									 'fid'    => null,
									 'uid'    => $this->oz_file_info['uid'],
									 'fkey'   => $this->oz_file_info['fkey'],
									 'fclone' => $this->oz_file_info['fclone'],
									 'ftype'  => $this->oz_file_info['ftype'],
									 'fsize'  => $this->oz_file_info['fsize'],
									 'fname'  => $this->oz_file_info['fname'],
									 'flabel' => $this->oz_file_info['flabel'],
									 'fpath'  => self::toRelative($this->oz_file_info['fpath']),
									 'fthumb' => self::toRelative($this->oz_file_info['fthumb']),
									 'ftime'  => $this->oz_file_info['ftime']
								 ]);

				$this->oz_file_info['fid'] = $insert;
			}

			$this->oz_file_info['ftext'] = OZoneOmlTextHelper::formatText('file', $this->oz_file_info);

			return $this->oz_file_info;
		}

		/**
		 * returns file relative path: relative to the current app users files directory
		 *
		 * @param string $path the absolute file path
		 *
		 * @return string
		 */
		public static function toRelative($path)
		{
			return OZoneStr::removePrefix(OZonePath::normalize($path), self::$oz_user_files);
		}

		/**
		 * returns file absolute path
		 *
		 * @param string $path the relative file path
		 *
		 * @return string
		 */
		public static function toAbsolute($path)
		{
			$path = OZonePath::normalize($path);

			if (!empty($path) AND !is_int(strpos($path, self::$oz_user_files))) {
				return self::$oz_user_files . $path;
			}

			return $path;
		}

		/**
		 * safe delete a file at a given path, make sure it has no file id or is not a clone
		 *
		 * @param string $path
		 */
		private function safeDelete($path)
		{
			if (!empty($this->oz_file_info['fid']) AND empty($this->oz_file_info['fclone']) AND file_exists($path)) {
				unlink($path);
			}
		}

		/**
		 * map upload error code to ozone file error message
		 *
		 * @param string $code the upload error code
		 */
		public function codeToMessage($code)
		{
			switch ($code) {
				case UPLOAD_ERR_INI_SIZE:
					// 'The uploaded file exceeds the upload_max_filesize directive in php.ini'
				case UPLOAD_ERR_FORM_SIZE:
					// 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'
					$this->message = 'OZ_FILE_TOO_BIG';
					break;

				case UPLOAD_ERR_NO_FILE:
					// 'No file was uploaded'
					$this->message = 'OZ_FILE_IS_EMPTY';
					break;

				case UPLOAD_ERR_PARTIAL:
					// 'The uploaded file was only partially uploaded'
				case UPLOAD_ERR_NO_TMP_DIR:
					// 'Missing a temporary folder'
				case UPLOAD_ERR_CANT_WRITE:
					// 'Failed to write file to disk'
				case UPLOAD_ERR_EXTENSION:
					// 'File upload stopped by extension'
				default:
					// 'Unknown upload error'
					$this->message = 'OZ_FILE_UPLOAD_FAIL';
			}
		}

		/**
		 * get the latest file error message
		 *
		 * @return string
		 */
		public function getMessage()
		{
			if (!empty($this->message)) return $this->message;

			return 'OZ_FILE_UNKNOWN_FILE_ERROR';
		}

		/**
		 * get this file info
		 *
		 * @return array
		 */
		public function getFileInfo()
		{
			return $this->oz_file_info;
		}
	}