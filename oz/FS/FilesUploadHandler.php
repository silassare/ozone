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
	use OZONE\OZ\Exceptions\RuntimeException;

	class FilesUploadHandler
	{
		/**
		 * the latest file error message
		 *
		 * @var string
		 */
		private $message = null;

		/**
		 * the last uploaded file info at which the error occur
		 *
		 * @var array
		 */
		private $computed_file = null;

		public function __construct() { }

		/**
		 * Move uploaded file to a given directory
		 *
		 * @param array  $uploaded_file the uploaded file info
		 * @param string $to            the destination directory
		 *
		 * @return bool|\OZONE\OZ\Db\OZFile file object when successful, false otherwise
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 * @throws \Exception
		 */
		public function moveUploadedFile(array $uploaded_file, $to)
		{
			$this->computed_file = $uploaded_file;

			if (( int )$uploaded_file['error'] != UPLOAD_ERR_OK) {
				$this->setLastError($uploaded_file['error']);

				return false;
			}

			if (!is_uploaded_file($uploaded_file['tmp_name'])) {
				$this->message = 'OZ_FILE_UPLOAD_NOT_VALID';

				return false;
			}

			$file_name = $uploaded_file['name'];// file name at client side
			$file_type = $uploaded_file['type'];// file mime type

			// live recorded blob files as audio/video/image don't have a valid name
			if (!strlen($file_name) OR $file_name === 'blob') {
				$file_name = $uploaded_file['name'] = $this->genOptionalFileName($file_type);
			}

			$gen_info          = FilesUtils::genNewFileInfo($to, $file_name, $file_type);
			$destination       = $gen_info['path'];
			$thumb_destination = $gen_info['thumbnail'];

			if ($this->isFileAlias($uploaded_file)) {
				$result = FilesUtils::getFileFromAlias($uploaded_file['tmp_name']);

				$file_obj = $result->cloneFile();

				// move_uploaded_file($uploaded_file['tmp_name'], $destination);

				return $file_obj;
			}

			$file_obj = new OZFile();

			$this->safeDelete($file_obj);

			// try move the uploaded file
			$result = move_uploaded_file($uploaded_file['tmp_name'], $destination);

			if ($result) {
				$file_obj->setName($file_name)
						 ->setPath($destination)
						 ->setUploadTime(time())
						 ->setType($file_type)
						 ->setSize(filesize($destination));

				// make thumbnail if possible
				$success = FilesUtils::makeThumb($file_obj, $thumb_destination);
				if ($success) {
					$file_obj->setThumb($thumb_destination);
				}

				$this->message = 'OZ_FILE_UPLOAD_SAVE_SUCCESS';

				return $file_obj;
			} else {
				$this->message = 'OZ_FILE_UPLOAD_SAVE_FAIL';

				return false;
			}
		}

		/**
		 * Move multiple uploaded files to a given directory
		 *
		 * @param array  $uploaded_files the files list
		 * @param string $to             the destination directory
		 *
		 * @return bool|\OZONE\OZ\Db\OZFile[] array of file object when successful, false otherwise
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public function moveMultipleFilesUpload(array $uploaded_files, $to)
		{
			/** @var \OZONE\OZ\Db\OZFile[] $file_obj_list */
			$file_obj_list = [];
			$tmp_file_list = [];
			$error         = false;

			if (!isset($uploaded_files["name"]) OR !is_array($uploaded_files["name"])) {
				// this is not a valid tree dimensional array
				// may be it is not a multiple file upload

				throw new RuntimeException("OZ_MULTIPLE_FILES_UPLOAD_REQUIRED");
			}
			// multiple upload files
			// transforming a three dimensional array into a two dimension array
			foreach ($uploaded_files as $key => $values) {
				foreach ($values as $i => $v) {
					$tmp_file_list[$i][$key] = $v;
				}
			}

			foreach ($tmp_file_list as $pos => $file) {
				$file_obj = $this->moveUploadedFile($file, $to);

				if (!$file_obj) {
					$error = true;
					break;
				}
				$file_obj_list[$pos] = $file_obj;
			}

			if ($error) {
				foreach ($file_obj_list as $f) {
					$this->safeDelete($f);
				}

				return false;
			}

			return $file_obj_list;
		}

		/**
		 * Generate optional file name with a given mime type
		 *
		 * @param string $type the file mime type
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
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
		 * @param array $file the file info
		 *
		 * @return bool true
		 */
		private function isFileAlias(array $file)
		{
			// check file name and extension
			if (!preg_match('#^[a-z0-9_]+\.ofa$#i', $file['name'])) return false;
			// the correct mime type
			if ($file['type'] !== 'text/x-ozone-file-alias') return false;
			// check alias file size 4ko
			if ($file['size'] > 4 * 1024) return false;

			return true;
		}

		/**
		 * Maps upload error code to error message
		 *
		 * @param string $code the upload error code
		 */
		private function setLastError($code)
		{
			switch ($code) {
				case UPLOAD_ERR_INI_SIZE:
					// 'The uploaded file exceeds the upload_max_filesize directive in php.ini'
				case UPLOAD_ERR_FORM_SIZE:
					// 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'
					$this->message = 'OZ_FILE_UPLOAD_TOO_BIG';
					break;

				case UPLOAD_ERR_NO_FILE:
					// 'No file was uploaded'
					$this->message = 'OZ_FILE_UPLOAD_IS_EMPTY';
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
		 * Returns the last uploaded file info at which the error occur
		 *
		 * @return array
		 */
		public function lastErrorFile()
		{
			return $this->computed_file;
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

			$path  = $file->getPath();
			$thumb = $file->getThumb();
			if (file_exists($path)) {
				unlink($path);
			}

			if (file_exists($thumb)) {
				unlink($thumb);
			}
		}
	}