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

	use OZONE\OZ\Core\Hasher;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\OZFile;
	use OZONE\OZ\Db\OZFilesQuery;
	use OZONE\OZ\Exceptions\RuntimeException;
	use OZONE\OZ\Utils\StringUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class FilesUtils
	{
		/**
		 * the default mime type for any file
		 *
		 * @var string
		 */
		const DEFAULT_FILE_MIME_TYPE = 'application/octet-stream';

		/**
		 * the default extension for any file
		 *
		 * @var string
		 */
		const DEFAULT_FILE_EXTENSION = 'ext';

		/**
		 * the default category for any file
		 *
		 * @var string
		 */
		const DEFAULT_FILE_CATEGORY = 'unknown';

		/**
		 * Gets users files directory root path.
		 *
		 * @return string
		 */
		public static function getUsersFilesRootDirectory()
		{
			return OZ_APP_DIR . 'oz_users_files' . DS;
		}

		/**
		 * Gets a user directory root path with a given user id.
		 *
		 * @param int|string $uid the user id
		 *
		 * @return string
		 * @throws \Exception
		 */
		public static function getUserRootDirectory($uid)
		{
			$root = self::getUsersFilesRootDirectory();
			$fm   = new FilesManager($root);

			$user_dir = $fm->cd($uid, true)
						   ->getRoot();

			return $user_dir;
		}

		/**
		 * Generate a file info: file name, file destination ...
		 *
		 * @param string $destination the destination directory path
		 * @param string $real_name   the real file name
		 * @param string $real_type   the real file mime type
		 *
		 * @return array the generated file info
		 * @throws \Exception
		 */
		public static function genNewFileInfo($destination, $real_name, $real_type)
		{
			$fm = new FilesManager($destination);

			$dir       = $fm->getRoot();
			$thumb_dir = $fm->cd('_thumb', true)
							->getRoot();

			$ext       = self::getExtension($real_name, $real_type);
			$name      = time() . '_' . Hasher::genRandomString(10, Hasher::CHARS_NUM);
			$full_name = $name . '.' . $ext;
			$path      = $dir . DS . $full_name;
			$thumb     = $thumb_dir . DS . $name . '.jpg';

			return [
				'name'      => $name,
				'full_name' => $full_name,
				'path'      => $path,
				'thumbnail' => $thumb
			];
		}

		/**
		 * Gets extension from a file with a given file path and mime type
		 *
		 * @param string $path the file path
		 * @param string $type the file mime type
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function getExtension($path, $type)
		{
			$ext = strtolower(substr($path, (strrpos($path, '.') + 1)));

			if (empty($ext)) {
				$ext = FilesUtils::mimeTypeToExtension($type);
			}

			return $ext;
		}

		/**
		 * Guess mime type that match to a given file extension
		 *
		 * when none found, default to \OZONE\OZ\FS\FilesUtils::DEFAULT_FILE_MIME_TYPE
		 *
		 * @param string $ext the file extension
		 *
		 * @return string the mime type
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function extensionToMimeType($ext)
		{
			$map = SettingsManager::get('oz.map.ext.to.mime');
			$ext = strtolower($ext);

			if (isset($map[$ext])) {
				return $map[$ext];
			}

			return FilesUtils::DEFAULT_FILE_MIME_TYPE;
		}

		/**
		 * Guess file extension that match to a given file mime type
		 *
		 * when none found, default to \OZONE\OZ\FS\FilesUtils::DEFAULT_FILE_EXTENSION
		 *
		 * @param string $type the file mime type
		 *
		 * @return string    the file extension that match to this file mime type
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function mimeTypeToExtension($type)
		{
			$map  = SettingsManager::get('oz.map.mime.to.ext');
			$type = strtolower($type);

			if (isset($map[$type])) {
				return $map[$type];
			}

			return FilesUtils::DEFAULT_FILE_EXTENSION;
		}

		/**
		 * Guess the file category with a given file mime type (video/audio/image ...)
		 *
		 * when none found, default to \OZONE\OZ\FS\FilesUtils::DEFAULT_FILE_CATEGORY
		 *
		 * @param string $mime the file mime type
		 *
		 * @return string
		 */
		public static function mimeTypeToCategory($mime)
		{
			if (!empty($mime)) {
				return strtolower(substr($mime, 0, strrpos($mime, '/')));
			}

			return FilesUtils::DEFAULT_FILE_CATEGORY;
		}

		/**
		 * Checks if a given string sequence is like a file key
		 *
		 * @param string $str the file key
		 *
		 * @return bool
		 */
		public static function isFileKeyLike($str)
		{
			$file_key_reg = "#^[a-zA-Z0-9]{32}$#";

			return is_string($str) AND preg_match($file_key_reg, $str);
		}

		/**
		 * Generated a thumbnail for the file
		 *
		 * @param \OZONE\OZ\Db\OZFile $file        the file object
		 * @param string              $destination the thumbnail destination path
		 *
		 * @return bool true if successful, false if fails
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function makeThumb(OZFile $file, $destination)
		{
			$quality         = 50;
			$max_thumb_width = $max_thumb_height = SettingsManager::get('oz.users', 'OZ_THUMB_MAX_SIZE');
			$done            = false;
			$file_category   = FilesUtils::mimeTypeToCategory($file->getType());

			$source = $file->getPath();

			switch ($file_category) {
				case 'image':
					$img_utils_obj = new ImagesUtils($source);

					if ($img_utils_obj->load()) {
						$advice = $img_utils_obj->adviceBestSize($max_thumb_width, $max_thumb_height);

						$img_utils_obj->resizeImage($advice['w'], $advice['h'], $advice['crop'])
									  ->saveImage($destination, $quality);

						$done = true;
					}
					break;
				case 'video':

					$vid_utils_obj = new VideosUtils($source);
					if ($vid_utils_obj->load()) {
						$done = $vid_utils_obj->makeVideoThumb($destination);
						if ($done) {
							$img_utils_obj = new ImagesUtils($destination);

							if ($img_utils_obj->load()) {
								$advice = $img_utils_obj->adviceBestSize($max_thumb_width, $max_thumb_height);

								$img_utils_obj->resizeImage($advice['w'], $advice['h'], $advice['crop'])
											  ->saveImage($destination, $quality);
							}
						}
					}
					break;
				default:
					// other files
			}

			return $done;
		}

		/**
		 * Gets file from database with a given file id and file key
		 *
		 * @param string|int $id  the file id
		 * @param string     $key the file key
		 *
		 * @return null|\OZONE\OZ\Db\OZFile
		 */
		public static function getFileWithId($id, $key)
		{
			if (empty($id) OR !FilesUtils::isFileKeyLike($key)) {
				return null;
			}

			$f_table = new OZFilesQuery();
			$result  = $f_table->filterById($id)
							   ->filterByKey($key)
							   ->find(1);

			return $result->fetchClass();
		}

		/**
		 * Parse an alias file
		 *
		 * @param string $alias_src the alias file source path
		 *
		 * @return \OZONE\OZ\Db\OZFile
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function getFileFromAlias($alias_src)
		{
			if (empty($alias_src) || !file_exists($alias_src) || !is_file($alias_src) || !is_readable($alias_src)) {
				throw new RuntimeException('OZ_FILE_ALIAS_UNKNOWN');
			}

			$desc = file_get_contents($alias_src);
			$data = json_decode($desc, true);

			if (!is_array($data) OR !array_key_exists('file_id', $data) OR !array_key_exists('file_key', $data)) {
				throw new RuntimeException('OZ_FILE_ALIAS_PARSE_ERROR');
			}

			$f = FilesUtils::getFileWithId($data['file_id'], $data['file_key']);

			if (!$f) {
				throw new RuntimeException('OZ_FILE_ALIAS_NOT_FOUND', $data);
			}

			return $f;
		}

		/**
		 * Format a given file size
		 *
		 * @param double $size the file size in byte
		 *
		 * @return string    the formatted file size
		 */
		public static function formatFileSize($size)
		{
			$unites        = ['byte', 'Kb', 'Mb', 'Gb', 'Tb'];
			$max_i         = count($unites);
			$i             = 0;
			$result        = 0;
			$decimal_point = '.';// ',' for french
			$sep           = ' ';

			while ($size >= 1 AND $i < $max_i) {
				$result = $size;
				$size   /= 1024;
				$i++;
			}

			if (!$i) $i = 1;

			$parts = explode('.', $result);

			if ($parts[0] != $result) {
				$result = number_format($result, 2, $decimal_point, $sep);
			}

			return $result . ' ' . $unites[$i - 1];
		}

		/**
		 * Generate regexp used to match file URI.
		 *
		 * @param array &$fields
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function genFileURIRegExp(array &$fields)
		{
			$format = SettingsManager::get("oz.files", "OZ_GET_FILE_URI_EXTRA_FORMAT");

			$parts = [
				"oz_file_id"        => "([0-9]+)",
				"oz_file_key"       => "([a-z0-9]+)",
				"oz_file_quality"   => "(0|1|2|3)",
				"oz_file_extension" => "([a-zA-Z0-9]{1,10})"
			];

			return StringUtils::stringFormatToRegExp($format, $parts, $fields);
		}
	}