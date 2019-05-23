<?php
	/**
	 * Auto generated file, please don't edit.
	 *
	 * With: Gobl v1.0.0
	 * Time: 1508868493
	 */

	namespace OZONE\OZ\FS;

	use OZONE\OZ\Core\Hasher;
	use OZONE\OZ\Db\OZFile;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Http\UploadedFile;

	class FilesUploadHandler
	{
		/**
		 * the latest file error message
		 *
		 * @var string
		 */
		private $message = null;

		public function __construct() { }

		/**
		 * Move uploaded file to a given directory
		 *
		 * @param \OZONE\OZ\Http\UploadedFile $upload the uploaded file info
		 * @param string                      $to     the destination directory
		 *
		 * @return bool|\OZONE\OZ\Db\OZFile file object when successful, false otherwise
		 * @throws \Exception
		 */
		public function moveUploadedFile(UploadedFile $upload, $to)
		{
			if ((int)$upload->getError() != UPLOAD_ERR_OK) {
				$this->message = self::uploadErrorMessage($upload->getError());

				return false;
			}

			$file_name = $upload->getClientFilename();// file name at client side
			$file_type = $upload->getClientMediaType();// file mime type

			// live recorded blob files as audio/video/image don't have a valid name
			if (!strlen($file_name) OR $file_name === 'blob') {
				$file_name = $this->genOptionalFileName($file_type);
			}

			$gen_info          = FilesUtils::genNewFileInfo($to, $file_name, $file_type);
			$destination       = $gen_info['path'];
			$thumb_destination = $gen_info['thumbnail'];

			if ($this->isFileAlias($upload)) {
				$result = FilesUtils::getFileFromAlias($upload->getStream());

				return $result->cloneFile();
			}

			$fo = new OZFile();

			try {
				$upload->moveTo($destination);

				$safe_file_size = filesize($destination);

				$fo->setName($file_name)
				   ->setPath($destination)
				   ->setType($file_type)
				   ->setSize($safe_file_size)
				   ->setAddTime(time())
				   ->setData(json_encode([]));

				// make thumbnail if possible
				if (FilesUtils::makeThumb($fo, $thumb_destination)) {
					$fo->setThumb($thumb_destination);
				}
			} catch (\Exception $e) {
				$this->safeDelete($fo);
				throw new InternalErrorException('OZ_FILE_UPLOAD_MOVE_FAIL', null, $e);
			}

			$this->message = 'OZ_FILE_UPLOAD_SAVE_SUCCESS';

			return $fo;
		}

		/**
		 * Generate optional file name with a given mime type
		 *
		 * @param string $type the file mime type
		 *
		 * @return string
		 */
		private function genOptionalFileName($type)
		{
			switch ($type) {
				case "image/png":
				case "image/jpeg":
				case "image/jpg":
				case "audio/wav":
					$ext = strtolower(substr($type, (strrpos($type, '/') + 1)));
					break;
				default:
					$ext = FilesUtils::mimeTypeToExtension($type);
					break;
			}

			$name = time() . '_' . Hasher::genRandomString(10, Hasher::CHARS_ALPHA_NUM) . '.' . $ext;

			return $name;
		}

		/**
		 * Checks if a file is an ozone file alias
		 *
		 * @param \OZONE\OZ\Http\UploadedFile $file the file info
		 *
		 * @return bool true
		 */
		private function isFileAlias(UploadedFile $file)
		{
			// checks file name and extension
			if (!preg_match('~^[a-z0-9_]+\.ofa$~i', $file->getClientFilename())) return false;
			// the correct mime type
			if ($file->getClientMediaType() !== 'text/x-ozone-file-alias') return false;
			// checks alias file size 4ko
			if ($file->getSize() > 4 * 1024) return false;

			return true;
		}

		/**
		 * Convert php upload error code to message.
		 *
		 * @param int $error
		 *
		 * @return string
		 */
		public static function uploadErrorMessage($error)
		{
			switch ($error) {
				case UPLOAD_ERR_INI_SIZE:
					// 'The uploaded file exceeds the upload_max_filesize directive in php.ini'
				case UPLOAD_ERR_FORM_SIZE:
					// 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'
					$message = 'OZ_FILE_UPLOAD_TOO_BIG';
					break;
				case UPLOAD_ERR_NO_FILE:
					// 'No file was uploaded'
					$message = 'OZ_FILE_UPLOAD_IS_EMPTY';
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
					$message = 'OZ_FILE_UPLOAD_FAIL';
			}

			return $message;
		}

		/**
		 * Returns the latest error message
		 *
		 * @return string
		 */
		public function lastErrorMessage()
		{
			if (!empty($this->message)) return $this->message;

			return 'OZ_FILE_UPLOAD_UNKNOWN_ERROR';
		}

		/**
		 * Safe delete of an uploaded file
		 *
		 * @param \OZONE\OZ\Db\OZFile $file
		 */
		public function safeDelete(OZFile $file)
		{
			if ($file->getId() OR $file->getClone()) {
				return;
			}

			if ($path = $file->getPath() AND file_exists($path)) {
				unlink($path);
			}

			if ($thumb = $file->getThumb() AND file_exists($thumb)) {
				unlink($thumb);
			}
		}
	}